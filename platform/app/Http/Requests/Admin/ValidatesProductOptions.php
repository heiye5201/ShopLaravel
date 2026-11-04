<?php

namespace App\Http\Requests\Admin;

trait ValidatesProductOptions
{
    private function optionRules(): array
    {
        return [
            'variant_options' => ['sometimes', 'array', 'list', 'max:3'],
            'variant_options.*' => ['array:name,values'],
            'variant_options.*.name' => ['required', 'string', 'max:50', 'distinct:strict'],
            'variant_options.*.values' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'variant_options.*.values.*' => ['required', 'string', 'max:50'],
            'variants.*.sku' => ['sometimes', 'nullable', 'string', 'max:100', 'distinct:strict'],
            'variants.*.image_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'variants.*.compare_at_price' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'variants.*.track_inventory' => ['sometimes', 'boolean'],
            'variants.*.option_values' => ['sometimes', 'array', 'list', 'max:3'],
            'variants.*.option_values.*' => ['required', 'string', 'max:50'],
        ];
    }
}
