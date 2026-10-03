<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Livewire;

// Every page component also authorizes in mount() and in each action.

Route::middleware('auth')->prefix('inventory')->name('inventory.')->group(function () {
    Route::livewire('/stock', Livewire\Stock\OnHand::class)->name('stock.index')->middleware('can:inventory.stock.view');
    Route::livewire('/moves', Livewire\Stock\Moves::class)->name('moves.index')->middleware('can:inventory.stock.view');
    Route::livewire('/warehouses', Livewire\Warehouses\Index::class)->name('warehouses.index')->middleware('can:inventory.warehouses.manage');

    Route::prefix('adjustments')->name('adjustments.')->group(function () {
        Route::livewire('/', Livewire\Adjustments\Index::class)->name('index')->middleware('can:inventory.adjustments.view');
        Route::livewire('/create', Livewire\Adjustments\Form::class)->name('create')->middleware('can:inventory.adjustments.create');
        Route::livewire('/{id}', Livewire\Adjustments\Show::class)->name('show')->middleware('can:inventory.adjustments.view')->whereNumber('id');
        Route::livewire('/{id}/edit', Livewire\Adjustments\Form::class)->name('edit')->middleware('can:inventory.adjustments.create')->whereNumber('id');
    });

    Route::prefix('transfers')->name('transfers.')->group(function () {
        Route::livewire('/', Livewire\Transfers\Index::class)->name('index')->middleware('can:inventory.transfers.view');
        Route::livewire('/create', Livewire\Transfers\Form::class)->name('create')->middleware('can:inventory.transfers.create');
        Route::livewire('/{id}', Livewire\Transfers\Show::class)->name('show')->middleware('can:inventory.transfers.view')->whereNumber('id');
        Route::livewire('/{id}/edit', Livewire\Transfers\Form::class)->name('edit')->middleware('can:inventory.transfers.create')->whereNumber('id');
    });
});
