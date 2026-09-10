<?php

namespace Tests\Unit\Liquid\Drops;

use App\Liquid\Drops\ProductDrop;
use App\Liquid\Drops\ProductVariantDrop;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class ProductDropTest extends TestCase
{
    public function test_returns_template_ready_product_values(): void
    {
        $product = new Product([
            'title' => 'Aurora 随行音箱',
            'slug' => 'aurora-speaker',
            'price' => 699,
            'description' => '测试描述',
            'is_available' => true,
            'tags' => ['蓝牙', '防水'],
        ]);
        $product->setRelation('variants', new Collection([
            new ProductVariant([
                'title' => '午夜蓝',
                'price' => 699,
                'is_available' => true,
                'position' => 1,
            ]),
        ]));

        $drop = new ProductDrop($product);
        $variants = $drop->variants();

        $this->assertSame('Aurora 随行音箱', $drop->title());
        $this->assertSame('¥699.00', $drop->price());
        $this->assertSame('测试描述', $drop->description());
        $this->assertTrue($drop->available());
        $this->assertSame(['蓝牙', '防水'], $drop->tags());
        $this->assertCount(1, $variants);
        $this->assertInstanceOf(ProductVariantDrop::class, $variants[0]);
    }

    public function test_exposes_only_whitelisted_product_properties(): void
    {
        $drop = new ProductDrop(new Product);

        $this->assertTrue($drop->hasKey('title'));
        $this->assertTrue($drop->hasKey('variants'));
        $this->assertFalse($drop->hasKey('save'));
        $this->assertFalse($drop->hasKey('setContext'));
    }
}
