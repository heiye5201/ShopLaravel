<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash:ascii', 'unique:products,slug'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'description' => ['required', 'string'],
            'image_path' => ['nullable', 'string', 'max:2048'],
            'badge' => ['nullable', 'string', 'max:100'],
            'is_available' => ['sometimes', 'boolean'],
            'tags' => ['required', 'array'],
            'tags.*' => ['string', 'max:50', 'distinct'],
        ];
    }
}
