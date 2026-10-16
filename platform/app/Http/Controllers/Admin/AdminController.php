<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginAdminRequest;
use App\Http\Requests\Admin\RegisterAdminRequest;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\UserResource;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function register(RegisterAdminRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return UserResource::make($user)
            ->additional(['message' => '注册成功'])
            ->response()
            ->setStatusCode(201);
    }

    public function login(LoginAdminRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->validated())) {
            throw ValidationException::withMessages([
                'email' => ['邮箱或密码错误。'],
            ]);
        }

        $request->session()->regenerate();

        return UserResource::make($request->user())
            ->additional(['message' => '登录成功'])
            ->response();
    }

    public function storeProduct(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return ProductResource::make($product)
            ->additional(['message' => '商品创建成功'])
            ->response()
            ->setStatusCode(201);
    }

    public function updateProduct(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        return ProductResource::make($product->refresh())
            ->additional(['message' => '商品更新成功'])
            ->response();
    }
}
