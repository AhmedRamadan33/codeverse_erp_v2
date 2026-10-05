<?php

use Illuminate\Support\Facades\Route;
use Modules\EgyptTax\Livewire;

// Every page component also authorizes in mount() and in each action.

Route::middleware('auth')->prefix('egypt-tax')->name('egypttax.')->group(function () {
    Route::livewire('/receipts', Livewire\Receipts\Index::class)->name('receipts.index')->middleware('can:egypttax.receipts.view');
    Route::livewire('/codes', Livewire\Codes::class)->name('codes')->middleware('can:egypttax.settings.manage');
    Route::livewire('/devices', Livewire\Devices::class)->name('devices')->middleware('can:egypttax.settings.manage');
    Route::livewire('/settings', Livewire\Settings::class)->name('settings')->middleware('can:egypttax.settings.manage');
});
