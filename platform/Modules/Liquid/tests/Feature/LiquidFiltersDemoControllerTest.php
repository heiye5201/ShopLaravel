<?php

namespace Modules\Liquid\Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LiquidFiltersDemoControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Product::factory()->create([
            'title' => 'Aurora 随行音箱',
            'slug' => 'aurora-speaker',
            'price' => 699.5,
            'image_path' => '/images/aurora-speaker.svg',
            'is_available' => true,
            'created_at' => '2026-09-10T02:30:00+00:00',
        ]);
    }

    public function test_renders_the_filter_demo_in_chinese(): void
    {
        $response = $this->get(route('liquid.filters-demo'));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSeeText('Liquid Filter 数据转换演示')
            ->assertSee('href="/liquid"', false)
            ->assertSeeText('金额格式化')
            ->assertSeeText('2026-09-10 10:30')
            ->assertSeeText('现货，可立即发出')
            ->assertSee('images/aurora-speaker.svg?width=720&amp;height=420&amp;fit=crop', false);
    }

    public function test_switches_filters_and_translations_to_english(): void
    {
        $response = $this->get(route('liquid.filters-demo', ['locale' => 'en']));

        $response
            ->assertOk()
            ->assertSeeText('Liquid Filter Data Transformation Demo')
            ->assertSeeText('Money formatting')
            ->assertSeeText('2026-09-09 22:30')
            ->assertSeeText('In stock and ready to ship');
    }
}
