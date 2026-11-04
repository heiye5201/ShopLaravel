<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['manual', 'automatic'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $categories = Category::query()
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($data['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->orderByDesc('pinned_at')->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($data['per_page'] ?? 10);

        return response()->json([
            'data' => $categories->getCollection()->map(fn (Category $category): array => $this->item($category)),
            'meta' => ['current_page' => $categories->currentPage(), 'last_page' => $categories->lastPage(), 'total' => $categories->total()],
        ]);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json(['data' => [
            ...$this->item($category),
            'product_ids' => $category->products()->pluck('products.id'),
            'products' => $category->products()->get(['products.id', 'products.title']),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->save($request);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        return $this->save($request, $category);
    }

    private function save(Request $request, ?Category $category = null): JsonResponse
    {
        if (is_string($request->input('name'))) {
            $request->merge(['name' => trim($request->input('name'))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['manual', 'automatic'])],
            'match_tag' => ['required_if:type,automatic', 'prohibited_if:type,manual', 'string', 'max:50', 'regex:/\S/u'],
            'product_ids' => ['present_if:type,manual', 'prohibited_if:type,automatic', 'array', 'max:1000'],
            'product_ids.*' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
        ]);

        return DB::transaction(function () use ($category, $data): JsonResponse {
            $creating = $category === null;
            $category = $creating ? new Category : Category::query()->lockForUpdate()->findOrFail($category->id);
            $category->fill(['name' => $data['name'], 'type' => $data['type'], 'match_tag' => $data['type'] === 'automatic' ? trim($data['match_tag']) : null]);
            $category->save();
            $category->products()->sync($data['type'] === 'manual' ? $data['product_ids'] : []);

            return response()->json(['data' => $this->item($category)], $creating ? 201 : 200);
        });
    }

    public function pin(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate(['is_pinned' => ['required', 'boolean']]);

        return DB::transaction(function () use ($category, $data): JsonResponse {
            $category = Category::query()->lockForUpdate()->findOrFail($category->id);
            $category->update(['pinned_at' => $data['is_pinned'] ? ($category->pinned_at ?? now()) : null]);

            return response()->json(['data' => $this->item($category)]);
        });
    }

    private function item(Category $category): array
    {
        return [
            'id' => $category->id, 'name' => $category->name, 'type' => $category->type,
            'match_tag' => $category->match_tag, 'is_pinned' => $category->pinned_at !== null,
            'products_count' => $category->matchingProducts()->count(),
            'available_products_count' => $category->matchingProducts()->where('is_available', true)->count(),
            'updated_at' => $category->updated_at->toIso8601String(),
            'created_at' => $category->created_at->toIso8601String(),
        ];
    }
}
