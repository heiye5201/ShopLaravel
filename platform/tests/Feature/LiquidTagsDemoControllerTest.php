<?php

namespace Tests\Feature;

use Tests\TestCase;

class LiquidTagsDemoControllerTest extends TestCase
{
    public function test_liquid_tags_demo_renders_supported_and_custom_tags(): void
    {
        $response = $this->get(route('liquid.tags-demo'));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSeeText('LIQUID TAGS DEMO')
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
