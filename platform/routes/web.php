<?php

use App\Http\Controllers\LiquidDemoController;
use App\Http\Controllers\LiquidFiltersDemoController;
use App\Http\Controllers\LiquidTagsDemoController;
use App\Http\Controllers\LiquidThemeDemoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/liquid-demo', LiquidDemoController::class)->name('liquid.demo');
Route::get('/liquid-tags-demo', LiquidTagsDemoController::class)->name('liquid.tags-demo');
Route::get('/liquid-filters-demo', LiquidFiltersDemoController::class)->name('liquid.filters-demo');
Route::get('/liquid-theme-demo', [LiquidThemeDemoController::class, 'home'])->name('liquid.theme-demo');
Route::get('/liquid-theme-demo/product', [LiquidThemeDemoController::class, 'product'])->name('liquid.theme-demo.product');
