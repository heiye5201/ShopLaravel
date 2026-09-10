<?php

namespace Tests\Feature;

use Tests\TestCase;

class LiquidIndexTest extends TestCase
{
    public function test_home_page_links_to_every_liquid_demo(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSeeText('Liquid Lab')
            ->assertSee(route('liquid.demo'), false)
            ->assertSee(route('liquid.tags-demo'), false)
            ->assertSee(route('liquid.filters-demo'), false)
            ->assertSee(route('liquid.theme-demo'), false)
            ->assertSee(route('liquid.theme-demo.product'), false);
    }
}
