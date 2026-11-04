<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Models\ShopMenu;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_navigation_requires_authentication(): void
    {
        $this->getJson('/api/v1/admin/navigation')->assertUnauthorized();
        $this->putJson('/api/v1/admin/navigation', [])->assertUnauthorized();
    }

    public function test_existing_columns_are_defaults_and_changes_persist_separately_from_storefront_menus(): void
    {
        $this->actingAs(User::factory()->create());
        $groups = $this->getJson('/api/v1/admin/navigation')->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.1.items.0.title', '商品管理')->json('data');
        $groups[1]['label'] = '商城设置';
        $groups[1]['items'][0]['title'] = '商品中心';
        $groups[1]['items'][0]['children'] = array_reverse($groups[1]['items'][0]['children']);
        $groups[1]['items'][2]['enabled'] = false;
        $groups[1]['items'] = array_reverse($groups[1]['items']);
        $this->putJson('/api/v1/admin/navigation', ['groups' => $groups])->assertOk()->assertJsonPath('data.1.label', '商城设置');
        $this->getJson('/api/v1/admin/navigation')->assertOk()->assertJsonPath('data', fn ($actual) => $actual == $groups);
        $this->assertDatabaseCount('shop_menus', 1);
        $sidebar = ShopMenu::where('placement', 'sidebar')->firstOrFail();
        $this->getJson('/api/v1/admin/menus')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson('/api/v1/admin/menus/'.$sidebar->id, ['name' => '破坏配置', 'is_primary' => true, 'items' => []])->assertNotFound();
        $this->deleteJson('/api/v1/admin/menus/'.$sidebar->id)->assertNotFound();
        $this->postJson('/api/v1/admin/menus', ['name' => '前台', 'is_primary' => true, 'items' => []])->assertCreated();
        $this->getJson('/api/v1/admin/navigation')->assertJsonPath('data', fn ($actual) => $actual == $groups);
        $this->putJson('/api/v1/admin/navigation', ['groups' => config('admin.navigation')])->assertOk();
        $this->getJson('/api/v1/admin/navigation')->assertJsonPath('data', fn ($actual) => $actual == config('admin.navigation'));
    }

    public function test_invalid_structure_or_hiding_management_entry_does_not_save(): void
    {
        $this->actingAs(User::factory()->create());
        $defaults = config('admin.navigation');
        $invalid = [];
        $groups = $defaults;
        $groups[1]['items'][3]['enabled'] = false;
        $invalid[] = $groups;
        $groups = $defaults;
        $groups[1]['items'][3]['children'][1]['enabled'] = false;
        $invalid[] = $groups;
        $groups = $defaults;
        $groups[1]['items'][0]['title'] = ' ';
        $invalid[] = $groups;
        $groups = $defaults;
        $groups[1]['items'][0]['path'] = '/invalid-route';
        $invalid[] = $groups;
        $groups = $defaults;
        array_pop($groups[1]['items']);
        $invalid[] = $groups;
        $groups = $defaults;
        $groups[1]['items'][0]['children'][] = $groups[1]['items'][0]['children'][0];
        $invalid[] = $groups;
        $groups = $defaults;
        $groups[0]['id'] = 'operations';
        $invalid[] = $groups;
        foreach ($invalid as $groups) {
            $this->putJson('/api/v1/admin/navigation', ['groups' => $groups])->assertUnprocessable();
        }
        $this->assertDatabaseCount('shop_menus', 0);
    }
}
