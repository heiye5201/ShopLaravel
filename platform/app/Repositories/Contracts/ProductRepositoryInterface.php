<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

interface ProductRepositoryInterface
{
    /**
     * @param  list<string>  $relations
     */
    public function findBySlugOrFail(string $slug, array $relations = []): Product;

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(int $limit = 3): Collection;
}
