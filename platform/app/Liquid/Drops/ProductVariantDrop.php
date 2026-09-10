<?php

namespace App\Liquid\Drops;

use App\Models\ProductVariant;
use Liquid\Drop;

final class ProductVariantDrop extends Drop
{
    public function __construct(private readonly ProductVariant $variant) {}

    public function title(): string
    {
        return $this->variant->title;
    }

    public function price(): string
    {
        return '¥'.number_format((float) $this->variant->price, 2);
    }

    public function available(): bool
    {
        return $this->variant->is_available;
    }

    public function hasKey($name): bool
    {
        return in_array($name, [
            'title',
            'price',
            'available',
        ], true);
    }
}
