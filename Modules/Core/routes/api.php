<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Api\AuthController;
use Modules\Core\Http\Controllers\Api\LookupController;
use Modules\Core\Http\Controllers\Api\PartnerController;
use Modules\Core\Http\Middleware\EnsureUserIsActive;

Route::prefix('v1')->group(function () {
    Route::post('/auth/token', [AuthController::class, 'token'])->name('core.auth.token');

    Route::middleware(['auth:sanctum', EnsureUserIsActive::class])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('core.auth.me');
        Route::delete('/auth/token', [AuthController::class, 'logout'])->name('core.auth.logout');

        Route::get('/branches', [LookupController::class, 'branches'])->name('core.branches.index');
        Route::get('/currencies', [LookupController::class, 'currencies'])->name('core.currencies.index');

        Route::apiResource('partners', PartnerController::class)
            ->names('core.partners')
            ->whereNumber('partner');
    });
});
