<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Models\ShopMenu;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/admin/menus')->assertUnauthorized();
        $this->postJson('/api/v1/admin/menus', [])->assertUnauthorized();
        $this->patchJson('/api/v1/admin/menus/1', [])->assertUnauthorized();
        $this->deleteJson('/api/v1/admin/menus/1')->assertUnauthorized();
    }

    public function test_menu_crud_order_and_primary_switch_sync_to_theme(): void
    {
        $product = Product::factory()->create(['is_available' => true, 'slug' => 'menu-product']);
        $category = Category::factory()->create(['name' => '分类精选', 'type' => 'manual']);
        $category->products()->attach($product);
        $this->actingAs(User::factory()->create());
        $items = [
            ['title' => '导航商品', 'type' => 'product', 'target_id' => $product->id],
            ['title' => '导航分类', 'type' => 'category', 'target_id' => $category->id],
            ['title' => '品牌故事', 'type' => 'custom', 'url' => 'https://example.com/about'],
        ];
        $id = $this->postJson('/api/v1/admin/menus', ['name' => '主菜单', 'is_primary' => true, 'items' => $items])
            ->assertCreated()->assertJsonPath('data.is_primary', true)->json('data.id');
        $this->get('/liquid/theme-demo')->assertOk()->assertSeeInOrder(['导航商品', '导航分类', '品牌故事'])
            ->assertSee(route('liquid.theme-demo.product', ['slug' => $product->slug]), false)
            ->assertSee(route('liquid.theme-demo.category', ['category' => $category->id]), false);
        $this->get('/liquid/theme-demo/category/'.$category->id)->assertOk()->assertSee('分类精选')->assertSee($product->title);
        $this->get('/liquid/theme-demo/product?slug=menu-product')->assertOk()->assertSee('品牌故事');
        $this->patchJson('/api/v1/admin/menus/'.$id, ['name' => '新菜单', 'is_primary' => true, 'items' => array_reverse($items)])
            ->assertOk()->assertJsonPath('data.items.0.title', '品牌故事');
        $this->getJson('/api/v1/admin/menus')->assertOk()->assertJsonPath('data.0.name', '新菜单');
        $this->get('/liquid/theme-demo')->assertSeeInOrder(['品牌故事', '导航分类', '导航商品']);
        $other = $this->postJson('/api/v1/admin/menus', ['name' => '替换', 'is_primary' => true, 'items' => []])->assertCreated()->json('data.id');
        $this->assertSame(1, ShopMenu::where('placement', 'header')->count());
        $this->assertNull(ShopMenu::find($id)->placement);
        $this->get('/liquid/theme-demo')->assertOk()->assertDontSee('品牌故事')->assertDontSee('Liquid 首页');
        $this->deleteJson('/api/v1/admin/menus/'.$other)->assertOk();
        $this->get('/liquid/theme-demo')->assertOk()->assertSee('Liquid 首页');
        $this->deleteJson('/api/v1/admin/menus/'.$id)->assertOk();
        $this->assertDatabaseCount('shop_menus', 0);
        $this->deleteJson('/api/v1/admin/menus/'.$id)->assertNotFound();
    }

    public function test_invalid_links_and_targets_are_rejected_atomically(): void
    {
        $this->actingAs(User::factory()->create());
        $base = ['name' => '菜单', 'is_primary' => true, 'items' => []];
        $id = $this->postJson('/api/v1/admin/menus', $base)->assertCreated()->json('data.id');
        foreach (['javascript:alert(1)', '//evil.test', '/\\evil.test', "https://example.com/\nattack", 'data:text/html,hello', 'relative-path'] as $url) {
            $this->patchJson('/api/v1/admin/menus/'.$id, array_replace($base, ['name' => '错误', 'items' => [['title' => '链接', 'type' => 'custom', 'url' => $url]]]))->assertUnprocessable();
        }
        foreach ([['title' => '商品', 'type' => 'product', 'target_id' => 99999], ['title' => '分类', 'type' => 'category'], ['title' => ' ', 'type' => 'custom', 'url' => '/'], ['title' => '未知', 'type' => 'unknown']] as $item) {
            $this->postJson('/api/v1/admin/menus', array_replace($base, ['items' => [$item]]))->assertUnprocessable();
        }
        $this->postJson('/api/v1/admin/menus', array_replace($base, ['name' => ' ']))->assertUnprocessable();
        $this->assertDatabaseCount('shop_menus', 1);
        $this->assertSame('菜单', ShopMenu::find($id)->name);
        $this->assertSame([], ShopMenu::find($id)->items);
    }

    public function test_navigation_tracks_slug_and_skips_unavailable_or_deleted_targets(): void
    {
        $product = Product::factory()->create(['is_available' => true]);
        $category = Category::factory()->create();
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/v1/admin/menus', ['name' => '菜单', 'is_primary' => true, 'items' => [
            ['title' => '商品入口', 'type' => 'product', 'target_id' => $product->id],
            ['title' => '分类入口', 'type' => 'category', 'target_id' => $category->id],
            ['title' => '<script>alert(1)</script>', 'type' => 'custom', 'url' => '/liquid/theme-demo'],
        ]])->assertCreated();
        $product->update(['slug' => 'updated-slug']);
        $this->get('/liquid/theme-demo')->assertOk()->assertSee('slug=updated-slug', false)->assertDontSee('<script>alert(1)</script>', false);
        $product->update(['is_available' => false]);
        $category->delete();
        $this->get('/liquid/theme-demo')->assertOk()->assertDontSee('商品入口')->assertDontSee('分类入口');
        $this->get('/liquid/theme-demo/category/'.$category->id)->assertNotFound();
    }
}
