<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\UserResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Modules\Admin\Http\Requests\UpdateProfileRequest;

class AdminController extends Controller
{
    public function uploadProductImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ]);
        $path = $request->file('image')->store('products', 'public');
        abort_if($path === false, 500, '图片上传失败，请重试。');

        return response()->json(['data' => ['url' => '/storage/'.$path]], 201);
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => '已退出登录']);
    }

    public function updateProfile(UpdateProfileRequest $request): UserResource
    {
        $data = $request->safe()->only(['name', 'email']);
        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }
        $request->user()->update($data);
        $request->session()->regenerate();

        return UserResource::make($request->user()->refresh());
    }

    public function dashboard(): JsonResponse
    {
        $total = Product::query()->count();
        $available = Product::query()->where('is_available', true)->count();

        return response()->json(['data' => [
            'products' => $total,
            'available' => $available,
            'unavailable' => $total - $available,
            'recent_products' => ProductResource::collection(Product::query()->with('variants')->latest('id')->limit(5)->get()),
        ]]);
    }

    public function products(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['available', 'unavailable'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $products = Product::query()
            ->with('variants')
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', '%'.$search.'%')->orWhere('slug', 'like', '%'.$search.'%');
            }))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('is_available', $status === 'available'))
            ->latest('id')->paginate($data['per_page'] ?? 10)->withQueryString();

        return ProductResource::collection($products);
    }
}
