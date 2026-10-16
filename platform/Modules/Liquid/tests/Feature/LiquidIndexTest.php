<?php

namespace Modules\Liquid\Tests\Feature;

use Tests\TestCase;

class LiquidIndexTest extends TestCase
{
    public function test_root_redirects_to_the_liquid_home_page(): void
    {
        $this->get('/')->assertRedirect('/liquid');
    }

    public function test_home_page_links_to_every_liquid_demo(): void
    {
        $this->assertSame('/liquid', route('liquid.home', [], false));
        $this->assertSame('/liquid/demo', route('liquid.demo', [], false));
        $this->assertSame('/liquid/tags-demo', route('liquid.tags-demo', [], false));
        $this->assertSame('/liquid/filters-demo', route('liquid.filters-demo', [], false));
        $this->assertSame('/liquid/theme-demo', route('liquid.theme-demo', [], false));
        $this->assertSame('/liquid/theme-demo/product', route('liquid.theme-demo.product', [], false));

        $response = $this->get(route('liquid.home'));

        $response
            ->assertOk()
            ->assertSeeText('Liquid Lab')
            ->assertSee(route('liquid.demo'), false)
            ->assertSee(route('liquid.tags-demo'), false)
            ->assertSee(route('liquid.filters-demo'), false)
            ->assertSee(route('liquid.theme-demo'), false)
            ->assertSee(route('liquid.theme-demo.product'), false)
            ->assertSee(route('liquid.architecture.admin-graphql-data-access'), false);
    }
}
