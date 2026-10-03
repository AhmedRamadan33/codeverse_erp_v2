<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', Modules\Core\Http\Middleware\EnsureUserIsActive::class])->name('sales.')->group(function () {
    //
});
