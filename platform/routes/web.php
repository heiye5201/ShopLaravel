<?php

use App\Http\Controllers\Admin\AdminController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/liquid');

Route::prefix('api/v1/admin')->name('admin.')->group(function (): void {
    Route::post('/register', [AdminController::class, 'register'])->name('register');
    Route::post('/login', [AdminController::class, 'login'])->name('login');

    Route::middleware('auth')->group(function (): void {
        Route::post('/products', [AdminController::class, 'storeProduct'])->name('products.store');
        Route::match(['put', 'patch'], '/products/{product}', [AdminController::class, 'updateProduct'])
            ->name('products.update');
    });
});
