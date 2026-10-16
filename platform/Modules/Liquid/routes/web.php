<?php

use Illuminate\Support\Facades\Route;
use Modules\Liquid\Http\Controllers\ArchitectureDocumentController;
use Modules\Liquid\Http\Controllers\LiquidDemoController;
use Modules\Liquid\Http\Controllers\LiquidFiltersDemoController;
use Modules\Liquid\Http\Controllers\LiquidTagsDemoController;
use Modules\Liquid\Http\Controllers\LiquidThemeDemoController;

Route::prefix('liquid')->name('liquid.')->group(function (): void {
    Route::view('/', 'liquid::liquid-index')->name('home');

    Route::get('/demo', LiquidDemoController::class)->name('demo');
    Route::get('/tags-demo', LiquidTagsDemoController::class)->name('tags-demo');
    Route::get('/filters-demo', LiquidFiltersDemoController::class)->name('filters-demo');
    Route::get('/theme-demo', [LiquidThemeDemoController::class, 'home'])->name('theme-demo');
    Route::get('/theme-demo/product', [LiquidThemeDemoController::class, 'product'])->name('theme-demo.product');

    Route::get(
        '/architecture/admin-graphql-data-access-foundation',
        [ArchitectureDocumentController::class, 'adminGraphqlDataAccess'],
    )->name('architecture.admin-graphql-data-access');
});
