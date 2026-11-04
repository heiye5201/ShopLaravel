<?php

namespace Modules\Liquid\Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LiquidThemeDemoControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Product::factory()->sequence(
            [
                'title' => 'Aurora 随行音箱',
                'slug' => 'aurora-speaker',
                'price' => 699.5,
                'badge' => '主题推荐',
            ],
            [
                'title' => 'Aurora Studio 音箱',
                'slug' => 'aurora-studio-speaker',
                'price' => 1299,
                'badge' => '新品',
            ],
            [
                'title' => 'Aurora Mini 音箱',
                'slug' => 'aurora-mini-speaker',
                'price' => 399,
                'badge' => '轻量款',
            ],
        )->count(3)->create();
    }

    public function test_renders_a_page_from_reusable_theme_files(): void
    {
        $response = $this->get(route('liquid.theme-demo'));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSeeText('Aurora Objects')
            ->assertSeeText('Liquid 首页')
            ->assertSeeText('主题首页')
            ->assertSeeText('Aurora 随行音箱')
            ->assertSeeText('Aurora Studio 音箱')
            ->assertSeeText('Aurora Mini 音箱')
            ->assertSeeText('页面公共骨架')
            ->assertSee('class="site-header"', false)
            ->assertSee('class="site-footer"', false)
            ->assertSee('class="product-card"', false);
    }

    public function test_renders_a_second_page_with_the_same_layout_and_partials(): void
    {
        $response = $this->get(route('liquid.theme-demo.product'));

        $response
            ->assertOk()
            ->assertSeeText('Liquid 首页')
            ->assertSeeText('商品详情')
            ->assertSeeText('Aurora 随行音箱')
            ->assertDontSeeText('选购组合')->assertDontSee('class="purchase-preview"', false)->assertSeeText('加入购物车')->assertDontSeeText('这个页面复用了什么？')
            ->assertSee('class="site-header"', false)
            ->assertSee('class="site-footer"', false)
            ->assertSee('class="product-detail"', false);
    }

    public function test_details_show_only_requested_products_variants_in_configured_order(): void
    {
        $product = Product::where('slug', 'aurora-studio-speaker')->firstOrFail();
        ProductVariant::factory()->for($product)->create(['title' => '白色款', 'price' => '219.50', 'position' => 2]);
        ProductVariant::factory()->for($product)->unavailable()->create(['title' => '黑色款', 'price' => '209.50', 'position' => 1]);
        ProductVariant::factory()->for(Product::where('slug', 'aurora-speaker')->firstOrFail())->create(['title' => '其他商品的款式']);

        $this->get(route('liquid.theme-demo.product', ['slug' => $product->slug]))->assertOk()
            ->assertSeeText('款式（2）')->assertSeeTextInOrder(['黑色款', '209.50', '暂不可选', '白色款', '219.50', '可选'])
            ->assertDontSeeText('其他商品的款式');
    }

    public function test_variants_escape_titles_and_respect_product_availability(): void
    {
        $product = Product::where('slug', 'aurora-speaker')->firstOrFail();
        $product->update(['is_available' => false]);
        ProductVariant::factory()->for($product)->create(['title' => '<script>alert(1)</script>', 'is_available' => true]);
        $this->get(route('liquid.theme-demo.product'))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertSeeText('暂不可选');
    }

    public function test_details_without_variants_show_empty_state(): void
    {
        $this->get(route('liquid.theme-demo.product'))->assertOk()
            ->assertSeeText('款式（0）')->assertSeeText('暂无可展示的款式。');
    }
}
