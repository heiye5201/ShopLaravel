<?php

namespace App\Liquid\Drops;

use App\Models\Product;
use App\Models\ProductVariant;
use Liquid\Drop;

final class ProductDrop extends Drop
{
    public function __construct(private readonly Product $product) {}

    public function title(): string
    {
        return $this->product->title;
    }

    public function price(): string
    {
        return '¥'.number_format((float) $this->product->price, 2);
    }

    public function description(): string
    {
        return $this->product->description;
    }

    public function available(): bool
    {
        return $this->product->is_available;
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return $this->product->tags;
    }

    /**
     * @return list<ProductVariantDrop>
     */
    public function variants(): array
    {
        return $this->product->variants
            ->map(fn (ProductVariant $variant): ProductVariantDrop => new ProductVariantDrop($variant))
            ->values()
            ->all();
    }

    public function hasKey($name): bool
    {
        return in_array($name, [
            'title',
            'price',
            'description',
            'available',
            'tags',
            'variants',
        ], true);
    }
}
