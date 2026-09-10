<?php

namespace Tests\Unit\Liquid\Drops;

use App\Liquid\Drops\ProductVariantDrop;
use App\Models\ProductVariant;
use Tests\TestCase;

class ProductVariantDropTest extends TestCase
{
    public function test_returns_template_ready_variant_values(): void
    {
        $drop = new ProductVariantDrop(new ProductVariant([
            'title' => '晨雾白',
            'price' => 729,
            'is_available' => false,
            'position' => 1,
        ]));

        $this->assertSame('晨雾白', $drop->title());
        $this->assertSame('¥729.00', $drop->price());
        $this->assertFalse($drop->available());
    }

    public function test_exposes_only_whitelisted_variant_properties(): void
    {
        $drop = new ProductVariantDrop(new ProductVariant);

        $this->assertTrue($drop->hasKey('title'));
        $this->assertFalse($drop->hasKey('delete'));
        $this->assertFalse($drop->hasKey('setContext'));
    }
}
