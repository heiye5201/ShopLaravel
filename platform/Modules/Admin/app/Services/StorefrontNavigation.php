<?php

namespace Modules\Admin\Services;

use App\Models\Category;
use App\Models\Product;
use Modules\Admin\Models\ShopMenu;

class StorefrontNavigation
{
    public function links(): ?array
    {
        $menu = ShopMenu::query()->where('placement', 'header')->first();
        if (! $menu) {
            return null;
        }
        $items = collect($menu->items);
        $products = Product::query()->whereIn('id', $items->where('type', 'product')->pluck('target_id'))->where('is_available', true)->get()->keyBy('id');
        $categories = Category::query()->whereIn('id', $items->where('type', 'category')->pluck('target_id'))->get()->keyBy('id');

        return $items->map(function (array $item) use ($products, $categories): ?array {
            $url = match ($item['type']) {
                'product' => isset($products[$item['target_id']]) ? route('liquid.theme-demo.product', ['slug' => $products[$item['target_id']]->slug]) : null,
                'category' => isset($categories[$item['target_id']]) ? route('liquid.theme-demo.category', ['category' => $item['target_id']]) : null,
                default => $item['url'],
            };

            return $url === null ? null : ['title' => $item['title'], 'url' => $url];
        })->filter()->values()->all();
    }
}
