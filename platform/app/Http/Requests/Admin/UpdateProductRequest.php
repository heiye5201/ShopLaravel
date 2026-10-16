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
            'image_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'badge' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_available' => ['sometimes', 'boolean'],
            'tags' => ['sometimes', 'required', 'array'],
            'tags.*' => ['string', 'max:50', 'distinct'],
        ];
    }
}
