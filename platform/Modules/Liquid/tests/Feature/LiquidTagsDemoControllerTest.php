<?php

namespace Modules\Liquid\Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LiquidTagsDemoControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_liquid_tags_demo_renders_supported_and_custom_tags(): void
    {
        Product::factory()->sequence(
            ['title' => 'Aurora 随行音箱', 'price' => 699],
            ['title' => 'Nova 桌面灯', 'price' => 299],
            ['title' => 'Orbit 无线充', 'price' => 199],
        )->count(3)->create();

        $response = $this->get(route('liquid.tags-demo'));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSeeText('LIQUID TAGS DEMO')
            ->assertSee('href="/liquid"', false)
            ->assertSeeText('欢迎来到 北辰商店')
            ->assertSeeText('促销进行中：全场 20% 优惠。')
            ->assertSeeText('活动状态：进行中')
            ->assertSeeText('注册 VIP 后可以获得额外权益。')
            ->assertSeeText('1. Aurora 随行音箱 · ¥699.00')
            ->assertSeeText('2. Nova 桌面灯 · ¥299.00')
            ->assertDontSeeText('Orbit 无线充')
            ->assertSee('class="demo-panel"', false)
            ->assertSee('{% panel %}...{% endpanel %}', false);
    }
}
