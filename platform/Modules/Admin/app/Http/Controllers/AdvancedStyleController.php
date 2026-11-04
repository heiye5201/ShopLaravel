<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Admin\Http\Requests\AdvancedStyleRequest;
use Modules\Admin\Models\AdvancedOfferStyle;

class AdvancedStyleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $styles = AdvancedOfferStyle::query()->when($data['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->latest('updated_at')->latest('id')->paginate(10)->withQueryString();

        return response()->json($styles);
    }

    public function options(): JsonResponse
    {
        return response()->json(['data' => ['defaults' => AdvancedOfferStyle::defaults(), 'badge_shapes' => AdvancedOfferStyle::BADGES]]);
    }

    public function show(AdvancedOfferStyle $style): JsonResponse
    {
        $products = Product::query()->with('variants')->whereIn('id', $style->product_ids)
            ->orWhereHas('variants', fn ($query) => $query->whereIn('id', $style->variant_ids))->get();

        return response()->json(['data' => $style, 'products' => ProductResource::collection($products)]);
    }

    public function store(AdvancedStyleRequest $request): JsonResponse
    {
        return response()->json(['data' => AdvancedOfferStyle::create($request->styleData())->refresh()], 201);
    }

    public function update(AdvancedStyleRequest $request, AdvancedOfferStyle $style): JsonResponse
    {
        $style->update($request->styleData());

        return response()->json(['data' => $style->refresh()]);
    }

    public function enabled(Request $request, AdvancedOfferStyle $style): JsonResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $style->update($data);

        return response()->json(['data' => $style->refresh()]);
    }
}
