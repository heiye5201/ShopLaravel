<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $items = ProductVariant::query()->with('product')
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search): void {
                $query->where('sku', 'like', '%'.$search.'%')->orWhere('title', 'like', '%'.$search.'%')
                    ->orWhereHas('product', fn ($product) => $product->where('title', 'like', '%'.$search.'%'));
            }))
            ->orderByDesc('product_id')->orderBy('position')->orderBy('id')
            ->paginate($data['per_page'] ?? 10)->withQueryString();

        return response()->json([
            'data' => $items->getCollection()->map(fn (ProductVariant $variant): array => $this->item($variant)),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'total' => $items->total()],
        ]);
    }

    public function update(Request $request, ProductVariant $variant): JsonResponse
    {
        $data = $request->validate([
            'continue_selling' => ['sometimes', 'required', 'boolean'],
            'adjustment' => ['required_without:continue_selling', 'integer', 'between:-2147483647,2147483647'],
            'expected_quantity' => ['required_with:adjustment', 'integer', 'between:0,2147483647'],
        ]);

        return DB::transaction(function () use ($variant, $data): JsonResponse {
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail($variant->id);
            if (array_key_exists('adjustment', $data)) {
                abort_if($variant->inventory_quantity !== (int) $data['expected_quantity'], 409, '库存已发生变化，请刷新后重试。');
                $quantity = $variant->inventory_quantity + (int) $data['adjustment'];
                if ($quantity < 0 || $quantity > 2147483647) {
                    throw ValidationException::withMessages(['adjustment' => '调整后的库存数量须在 0 到 2147483647 之间。']);
                }
                $variant->inventory_quantity = $quantity;
            }
            if (array_key_exists('continue_selling', $data)) {
                $variant->continue_selling = $data['continue_selling'];
            }
            $variant->save();

            return response()->json(['data' => $this->item($variant->load('product'))]);
        });
    }

    private function item(ProductVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'product_id' => $variant->product_id,
            'product_title' => $variant->product->title,
            'title' => $variant->title,
            'sku' => $variant->sku,
            'inventory_quantity' => $variant->inventory_quantity,
            'continue_selling' => $variant->continue_selling,
            'track_inventory' => $variant->track_inventory,
        ];
    }
}
