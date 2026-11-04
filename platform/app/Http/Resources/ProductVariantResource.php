<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'product_id' => $this->product_id, 'title' => $this->title,
            'sku' => $this->sku,
            'image_path' => $this->image_path,
            'compare_at_price' => $this->compare_at_price,
            'track_inventory' => $this->track_inventory,
            'option_values' => $this->option_values ?? [],
            'price' => $this->price, 'is_available' => $this->is_available, 'position' => $this->position,
        ];
    }
}
