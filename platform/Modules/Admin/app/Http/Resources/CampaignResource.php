<?php

namespace Modules\Admin\Http\Resources;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $products = Product::query()->whereIn('id', $this->product_ids)->get()->keyBy('id');

        return [
            'id' => $this->id, 'name' => $this->name, 'headline' => $this->headline,
            'body' => $this->body, 'cta' => $this->cta, 'theme' => $this->theme,
            'position' => $this->position, 'product_ids' => $this->product_ids,
            'products' => ProductResource::collection(collect($this->product_ids)->map(fn ($id) => $products->get($id))->filter()->values()),
            'status' => $this->status, 'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(), 'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
