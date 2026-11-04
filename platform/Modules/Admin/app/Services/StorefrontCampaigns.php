<?php

namespace Modules\Admin\Services;

use App\Models\Product;
use Modules\Admin\Models\MarketingCampaign;

class StorefrontCampaigns
{
    public function forPage(string $position, ?int $currentProductId = null): array
    {
        return MarketingCampaign::query()->where('status', 'published')
            ->whereIn('position', [$position, 'all'])
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->latest('id')->limit(3)->get()->map(function (MarketingCampaign $campaign) use ($currentProductId): array {
                $products = Product::query()->whereIn('id', $campaign->product_ids)->where('is_available', true)
                    ->when($currentProductId, fn ($query) => $query->where('id', '!=', $currentProductId))->get()->keyBy('id');

                return [
                    'headline' => $campaign->headline, 'body' => $campaign->body, 'cta' => $campaign->cta,
                    'theme' => in_array($campaign->theme, ['lavender', 'mint', 'midnight'], true) ? $campaign->theme : 'lavender',
                    'products' => collect($campaign->product_ids)->map(fn ($id) => $products->get($id))->filter()->map(fn (Product $product): array => [
                        'title' => $product->title, 'price' => (float) $product->price,
                        'url' => route('liquid.theme-demo.product', ['slug' => $product->slug]),
                    ])->values()->all(),
                ];
            })->filter(fn (array $campaign) => count($campaign['products']) > 0)->values()->all();
    }
}
