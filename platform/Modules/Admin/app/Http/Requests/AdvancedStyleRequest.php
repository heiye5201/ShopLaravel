<?php

namespace Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Admin\Models\AdvancedOfferStyle;

class AdvancedStyleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'], 'enabled' => ['required', 'boolean'],
            'scope' => ['required', Rule::in(['all', 'products', 'variants', 'exclude'])],
            'product_ids' => [Rule::requiredIf(in_array($this->input('scope'), ['products', 'exclude'], true)), 'array', 'max:200', 'list'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'variant_ids' => [Rule::requiredIf($this->input('scope') === 'variants'), 'array', 'max:200', 'list'],
            'variant_ids.*' => ['integer', 'distinct', 'exists:product_variants,id'],
            'tiers' => ['required', 'array', 'min:1', 'max:10', 'list'],
            'tiers.*' => ['array:quantity,title,subtitle,label,badge_text'],
            'tiers.*.quantity' => ['required', 'integer', 'min:1', 'max:99', 'distinct'],
            'tiers.*.title' => ['required', 'string', 'max:100'],
            'tiers.*.subtitle' => ['nullable', 'string', 'max:200'],
            'tiers.*.label' => ['nullable', 'string', 'max:50'],
            'tiers.*.badge_text' => ['nullable', 'string', 'max:100'],
            'design' => ['required', 'array:title_mode,title,palette,colors,thumbnails,marquee,radius,badge_shape,badge_mode,badge_text,badge_animation,badge_tilt,hide_quantity'],
            'design.title_mode' => ['required', Rule::in(['default', 'custom'])],
            'design.title' => [Rule::requiredIf($this->input('design.title_mode') === 'custom'), 'nullable', 'string', 'max:100'],
            'design.palette' => ['required', Rule::in(['violet', 'red', 'orange', 'black', 'brown', 'mint', 'custom'])],
            'design.colors' => ['required', 'array:text,surface,active_surface,border,active_border'],
            'design.colors.text' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'design.colors.surface' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'design.colors.active_surface' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'design.colors.border' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'design.colors.active_border' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'design.radius' => ['required', 'integer', 'min:0', 'max:40'],
            'design.badge_shape' => ['required', Rule::in(AdvancedOfferStyle::BADGES)],
            'design.badge_mode' => ['required', Rule::in(['dynamic', 'fixed'])],
            'design.badge_text' => ['nullable', 'string', 'max:100'],
            ...collect(['thumbnails', 'marquee', 'badge_animation', 'badge_tilt', 'hide_quantity'])
                ->mapWithKeys(fn ($key) => ['design.'.$key => ['required', 'boolean']])->all(),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $previous = 0;
            foreach ($this->input('tiers', []) as $index => $tier) {
                if ((int) $tier['quantity'] <= $previous) {
                    $validator->errors()->add("tiers.{$index}.quantity", '层级数量必须从小到大排列。');
                }
                $previous = (int) $tier['quantity'];
            }
        }];
    }

    public function styleData(): array
    {
        $data = $this->validated();
        $data['product_ids'] = in_array($data['scope'], ['products', 'exclude'], true) ? array_map('intval', $data['product_ids']) : [];
        $data['variant_ids'] = $data['scope'] === 'variants' ? array_map('intval', $data['variant_ids']) : [];
        $data['tiers'] = array_map(fn ($tier) => [
            'quantity' => (int) $tier['quantity'], 'title' => $tier['title'],
            'subtitle' => $tier['subtitle'] ?? '', 'label' => $tier['label'] ?? '', 'badge_text' => $tier['badge_text'] ?? '',
        ], $data['tiers']);
        foreach (['thumbnails', 'marquee', 'badge_animation', 'badge_tilt', 'hide_quantity'] as $key) {
            $data['design'][$key] = (bool) $data['design'][$key];
        }
        $data['design']['radius'] = (int) $data['design']['radius'];
        $data['design']['title'] = $data['design']['title'] ?? '';
        $data['design']['badge_text'] = $data['design']['badge_text'] ?? '';

        return $data;
    }
}
