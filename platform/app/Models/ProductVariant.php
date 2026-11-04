<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'title',
        'price',
        'is_available',
        'position',
        'sku',
        'image_path',
        'compare_at_price',
        'track_inventory',
        'inventory_quantity',
        'continue_selling',
        'option_values',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'track_inventory' => 'boolean',
            'inventory_quantity' => 'integer',
            'continue_selling' => 'boolean',
            'option_values' => 'array',
            'is_available' => 'boolean',
            'position' => 'integer',
        ];
    }
}
