<?php

namespace Modules\Admin\Services;

use App\Models\Product;
use Modules\Admin\Models\AdvancedOfferStyle;

class StorefrontAdvancedStyles
{
    public function forProduct(Product $product): array
    {
        $variantIds = $product->variants->pluck('id')->all();

        return AdvancedOfferStyle::query()->where('enabled', true)->latest('updated_at')->latest('id')->get()
            ->filter(fn (AdvancedOfferStyle $style) => match ($style->scope) {
                'all' => true,
                'products' => in_array($product->id, $style->product_ids, true),
                'exclude' => ! in_array($product->id, $style->product_ids, true),
                'variants' => count(array_intersect($variantIds, $style->variant_ids)) > 0,
                default => false,
            })->map(fn (AdvancedOfferStyle $style) => [
                'id' => $style->id, 'scope' => $style->scope, 'variant_ids' => $style->variant_ids,
                'tiers' => $style->tiers, 'design' => $style->design,
            ])->values()->all();
    }
}
