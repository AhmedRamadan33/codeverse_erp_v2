<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\EnsureUserIsActive;
use Modules\Pos\Http\Controllers\Api\PosController;

// Every endpoint authorizes through the POS actions or Gate checks in the controller.

Route::prefix('v1/pos')->middleware(['auth:sanctum', EnsureUserIsActive::class])->name('pos.')->controller(PosController::class)->group(function () {
    Route::get('/setup', 'setup')->name('setup');
    Route::get('/products', 'products')->name('products');

    Route::get('/shifts/current', 'currentShift')->name('shifts.current');
    Route::post('/shifts', 'openShift')->name('shifts.open');
    Route::get('/shifts/{shift}', 'showShift')->name('shifts.show')->whereNumber('shift');
    Route::post('/shifts/{shift}/close', 'closeShift')->name('shifts.close')->whereNumber('shift');

    Route::post('/receipts', 'sell')->name('receipts.store');
    Route::get('/receipts/{receipt}', 'showReceipt')->name('receipts.show')->whereNumber('receipt');
    Route::post('/receipts/{receipt}/returns', 'returnReceipt')->name('receipts.return')->whereNumber('receipt');
});
