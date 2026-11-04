<?php

use App\Http\Controllers\Admin\AdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\AdminController as ConsoleController;
use Modules\Admin\Http\Controllers\AdvancedStyleController;
use Modules\Admin\Http\Controllers\CategoryController;
use Modules\Admin\Http\Controllers\InventoryController;
use Modules\Admin\Http\Controllers\MarketingController;
use Modules\Admin\Http\Controllers\MenuController;
use Modules\Admin\Http\Controllers\NavigationController;

Route::get('/admin/{path?}', function () {
    $index = public_path('admin-app/index.html');
    abort_unless(is_file($index), 503, '请先在 platform/Modules/Admin/frontend 目录执行 pnpm install 和 pnpm build。');

    return response()->file($index, ['Cache-Control' => 'no-store']);
})->where('path', '.*')->name('admin.spa');

Route::prefix('api/v1/admin')->name('admin.')->group(function (): void {
    Route::get('/csrf', fn (Request $request) => response()->json(['token' => $request->session()->token()])
        ->header('Cache-Control', 'no-store'))->name('csrf');
    Route::post('/login', [AdminController::class, 'login'])->middleware('throttle:5,1')->name('login');

    Route::middleware('auth')->group(function (): void {
        Route::get('/advanced-styles/options', [AdvancedStyleController::class, 'options'])->name('advanced-styles.options');
        Route::get('/advanced-styles', [AdvancedStyleController::class, 'index'])->name('advanced-styles.index');
        Route::post('/advanced-styles', [AdvancedStyleController::class, 'store'])->name('advanced-styles.store');
        Route::get('/advanced-styles/{style}', [AdvancedStyleController::class, 'show'])->name('advanced-styles.show');
        Route::patch('/advanced-styles/{style}', [AdvancedStyleController::class, 'update'])->name('advanced-styles.update');
        Route::patch('/advanced-styles/{style}/enabled', [AdvancedStyleController::class, 'enabled'])->name('advanced-styles.enabled');
        Route::get('/marketing/config', [MarketingController::class, 'config'])->name('marketing.config');
        Route::get('/marketing/campaigns', [MarketingController::class, 'index'])->name('marketing.index');
        Route::post('/marketing/campaigns', [MarketingController::class, 'store'])->name('marketing.store');
        Route::patch('/marketing/campaigns/{campaign}', [MarketingController::class, 'update'])->name('marketing.update');
        Route::patch('/marketing/campaigns/{campaign}/status', [MarketingController::class, 'status'])->name('marketing.status');
        Route::post('/marketing/generate', [MarketingController::class, 'generate'])->middleware('throttle:10,1')->name('marketing.generate');
        Route::post('/register', [AdminController::class, 'register'])->name('register');
        Route::get('/me', [ConsoleController::class, 'me'])->name('me');
        Route::post('/logout', [ConsoleController::class, 'logout'])->name('logout');
        Route::patch('/profile', [ConsoleController::class, 'updateProfile'])->name('profile');
        Route::get('/dashboard', [ConsoleController::class, 'dashboard'])->name('dashboard');
        Route::post('/product-images', [ConsoleController::class, 'uploadProductImage'])->middleware('throttle:60,1')->name('product-images.store');
        Route::get('/navigation', [NavigationController::class, 'index'])->name('navigation.index');
        Route::put('/navigation', [NavigationController::class, 'update'])->name('navigation.update');
        Route::get('/menus', [MenuController::class, 'index'])->name('menus.index');
        Route::post('/menus', [MenuController::class, 'store'])->name('menus.store');
        Route::patch('/menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
        Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
        Route::patch('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::patch('/categories/{category}/pin', [CategoryController::class, 'pin'])->name('categories.pin');
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::patch('/inventory/{variant}', [InventoryController::class, 'update'])->name('inventory.update');
        Route::get('/products', [ConsoleController::class, 'products'])->name('products.index');
        Route::post('/products', [AdminController::class, 'storeProduct'])->name('products.store');
        Route::match(['put', 'patch'], '/products/{product}', [AdminController::class, 'updateProduct'])
            ->name('products.update');
    });
});
