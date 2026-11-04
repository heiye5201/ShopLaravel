<?php

namespace App\Models;

use App\Services\ProductContentSanitizer;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'price',
        'description',
        'content',
        'image_path',
        'badge',
        'is_available',
        'tags',
        'variant_options',
    ];

    protected function content(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => app(ProductContentSanitizer::class)->sanitize($value),
        );
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
            'tags' => 'array',
            'variant_options' => 'array',
        ];
    }
}
