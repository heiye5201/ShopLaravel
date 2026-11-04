<?php

namespace Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'headline' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:1000'],
            'cta' => ['required', 'string', 'max:30'],
            'theme' => ['required', Rule::in(['lavender', 'mint', 'midnight'])],
            'position' => ['required', Rule::in(['home', 'product', 'all'])],
            'product_ids' => ['required', 'array', 'min:1', 'max:6'],
            'product_ids.*' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', Rule::when($this->filled('starts_at'), 'after:starts_at')],
        ];
    }
}
