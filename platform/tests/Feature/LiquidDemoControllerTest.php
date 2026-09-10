<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LiquidDemoControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_liquid_demo_renders_product_data_and_template_features(): void
    {
        $product = Product::factory()->create([
            'title' => 'Aurora 随行音箱',
            'slug' => 'aurora-speaker',
            'price' => 699,
            'is_available' => true,
        ]);

        ProductVariant::factory()
            ->count(3)
            ->for($product)
            ->sequence(
                ['title' => '午夜蓝', 'position' => 1, 'is_available' => true],
                ['title' => '晨雾白', 'position' => 2, 'is_available' => false],
                ['title' => '落日橙', 'position' => 3, 'is_available' => true],
            )
            ->create();

        $response = $this->get(route('liquid.demo'));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSeeText('LIQUID LAB')
            ->assertSeeText('Aurora 随行音箱')
            ->assertSeeText('现货，可立即发出')
            ->assertSeeText('1. 午夜蓝')
            ->assertSeeText('晨雾白')
            ->assertSeeText('售罄');
    }

    public function test_liquid_demo_escapes_html_from_rendered_data(): void
    {
        Product::factory()->create([
            'slug' => 'aurora-speaker',
            'description' => '<script>alert("demo")</script>',
        ]);

        $response = $this->get(route('liquid.demo'));

        $response
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("demo")</script>', false);
    }

    public function test_liquid_demo_returns_404_when_demo_product_is_missing(): void
    {
        $this->get(route('liquid.demo'))->assertNotFound();
    }
}
