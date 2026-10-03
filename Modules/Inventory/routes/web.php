<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('inventory')->name('inventory.')->group(function () {
    //
});
