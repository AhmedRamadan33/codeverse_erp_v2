<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\EnsureUserIsActive;
use Modules\Sales\Http\Controllers\Api\InvoiceController;
use Modules\Sales\Http\Controllers\Api\ReturnController;

// Every endpoint authorizes through the Sales actions or Gate checks in the controllers.

Route::prefix('v1/sales')->middleware(['auth:sanctum', EnsureUserIsActive::class])->name('sales.')->group(function () {
    Route::apiResource('invoices', InvoiceController::class)->whereNumber('invoice');
    Route::post('/invoices/{invoice}/post', [InvoiceController::class, 'post'])->name('invoices.post')->whereNumber('invoice');
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel')->whereNumber('invoice');
    Route::post('/invoices/{invoice}/returns', [ReturnController::class, 'store'])->name('returns.store')->whereNumber('invoice');

    Route::apiResource('returns', ReturnController::class)->except('store')->whereNumber('return');
    Route::post('/returns/{return}/post', [ReturnController::class, 'post'])->name('returns.post')->whereNumber('return');
    Route::post('/returns/{return}/cancel', [ReturnController::class, 'cancel'])->name('returns.cancel')->whereNumber('return');
});
