<?php

namespace Tests\Feature;

use Tests\TestCase;

class LiquidThemeDemoControllerTest extends TestCase
{
    public function test_renders_a_page_from_reusable_theme_files(): void
    {
        $response = $this->get(route('liquid.theme-demo'));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSeeText('Aurora Objects')
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
            ->assertSeeText('商品详情')
            ->assertSeeText('Aurora 随行音箱')
            ->assertSeeText('这个页面复用了什么？')
            ->assertSee('class="site-header"', false)
            ->assertSee('class="site-footer"', false)
            ->assertSee('class="product-detail"', false);
    }
}
