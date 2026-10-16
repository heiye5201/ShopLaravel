<?php

namespace App\Repositories;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentProductRepository implements ProductRepositoryInterface
{
    public function findBySlugOrFail(string $slug, array $relations = []): Product
    {
        return Product::query()
            ->with($relations)
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public function getProducts(int $limit = 3): Collection
    {
        return Product::query()
            ->oldest('id')
            ->limit($limit)
            ->get();
    }
}
