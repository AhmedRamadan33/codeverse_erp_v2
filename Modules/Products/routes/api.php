<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\EnsureUserIsActive;
use Modules\Products\Http\Controllers\Api\ProductController;

Route::prefix('v1')->middleware(['auth:sanctum', EnsureUserIsActive::class])->name('products.')->group(function () {
    Route::get('/products', [ProductController::class, 'index'])->name('index');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('show')->whereNumber('product');
    Route::get('/products/barcode/{barcode}', [ProductController::class, 'barcode'])->name('barcode');
});
