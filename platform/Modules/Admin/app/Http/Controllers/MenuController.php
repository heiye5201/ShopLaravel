<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Models\ShopMenu;

class MenuController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => ShopMenu::query()->where(fn ($query) => $query->whereNull('placement')->orWhere('placement', 'header'))->orderBy('id')->get()->map(fn (ShopMenu $menu): array => $this->item($menu))]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->save($request);
    }

    public function update(Request $request, ShopMenu $menu): JsonResponse
    {
        abort_if($menu->placement === 'sidebar', 404);

        return $this->save($request, $menu);
    }

    public function destroy(ShopMenu $menu): JsonResponse
    {
        abort_if($menu->placement === 'sidebar', 404);
        $menu->delete();

        return response()->json(['data' => null]);
    }

    private function save(Request $request, ?ShopMenu $menu = null): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/\S/u'],
            'is_primary' => ['required', 'boolean'],
            'items' => ['present', 'array', 'list', 'max:100'],
            'items.*' => ['required', 'array:title,type,target_id,url'],
            'items.*.title' => ['required', 'string', 'max:100', 'regex:/\S/u'],
            'items.*.type' => ['required', Rule::in(['product', 'category', 'custom'])],
            'items.*.target_id' => ['nullable', 'integer', 'min:1'],
            'items.*.url' => ['nullable', 'string', 'max:2048'],
        ]);
        $items = [];
        foreach ($data['items'] as $index => $item) {
            $validator = validator($item, match ($item['type']) {
                'product' => ['target_id' => ['required', Rule::exists('products', 'id')]],
                'category' => ['target_id' => ['required', Rule::exists('categories', 'id')]],
                'custom' => ['url' => ['required', function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || preg_match('/[\x00-\x20\x7f\\\\]/', $value) || ! (preg_match('~^/(?!/)~', $value) || (preg_match('~^https?://~i', $value) && filter_var($value, FILTER_VALIDATE_URL)))) {
                        $fail('链接必须为站内路径（以 / 开头）或完整的 HTTP(S) 地址。');
                    }
                }]],
            });
            if ($validator->fails()) {
                throw ValidationException::withMessages([
                    "items.$index" => $validator->errors()->all(),
                ]);
            }
            $items[] = ['title' => trim($item['title']), 'type' => $item['type'],
                'target_id' => $item['type'] === 'custom' ? null : (int) $item['target_id'],
                'url' => $item['type'] === 'custom' ? $item['url'] : null];
        }

        return DB::transaction(function () use ($data, $items, $menu): JsonResponse {
            $creating = $menu === null;
            ShopMenu::query()->orderBy('id')->lockForUpdate()->get();
            $menu = $creating ? new ShopMenu : ShopMenu::query()->findOrFail($menu->id);
            if ($data['is_primary']) {
                ShopMenu::query()->where('placement', 'header')
                    ->when($menu->exists, fn ($query) => $query->where('id', '!=', $menu->id))
                    ->update(['placement' => null]);
            }
            $menu->fill(['name' => trim($data['name']), 'placement' => $data['is_primary'] ? 'header' : null, 'items' => $items])->save();

            return response()->json(['data' => $this->item($menu)], $creating ? 201 : 200);
        }, 3);
    }

    private function item(ShopMenu $menu): array
    {
        return ['id' => $menu->id, 'name' => $menu->name, 'is_primary' => $menu->placement === 'header',
            'items' => $menu->items, 'updated_at' => $menu->updated_at->toIso8601String()];
    }
}
