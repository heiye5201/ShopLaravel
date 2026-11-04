<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $fillable = ['name', 'type', 'match_tag', 'pinned_at'];

    protected function casts(): array
    {
        return ['pinned_at' => 'datetime'];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function matchingProducts(): Builder
    {
        if ($this->type === 'automatic') {
            return Product::query()->whereJsonContains('tags', $this->match_tag);
        }

        return Product::query()->whereIn('id', $this->products()->select('products.id'));
    }
}
