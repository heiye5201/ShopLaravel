<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    use ValidatesProductOptions;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique('products', 'slug')->ignore($this->route('product')),
            ],
            'price' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'description' => ['sometimes', 'required', 'string'],
            'content' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'image_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'badge' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_available' => ['sometimes', 'boolean'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50', 'distinct'],
            ...$this->optionRules(),
            'variants' => ['sometimes', 'array', 'min:1', 'max:100', 'list'],
            'variants.*' => ['array:id,title,price,is_available,position,sku,image_path,compare_at_price,track_inventory,option_values'],
            'variants.*.id' => ['sometimes', 'integer', 'min:1', 'distinct', Rule::exists('product_variants', 'id')->where('product_id', $this->route('product')->id)],
            'variants.*.title' => ['required', 'string', 'max:255'],
            'variants.*.price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'variants.*.is_available' => ['sometimes', 'boolean'],
            'variants.*.position' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
