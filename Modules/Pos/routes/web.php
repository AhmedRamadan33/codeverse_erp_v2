<?php

use Illuminate\Support\Facades\Route;
use Modules\Pos\Http\Controllers\ReceiptPrintController;
use Modules\Pos\Livewire;

// Every page component also authorizes in mount() and in each action.

Route::middleware('auth')->prefix('pos')->name('pos.')->group(function () {
    Route::livewire('/', Livewire\Terminal::class)->name('terminal')->middleware('can:pos.terminal.sell');

    Route::prefix('receipts')->name('receipts.')->group(function () {
        Route::livewire('/', Livewire\Receipts\Index::class)->name('index')->middleware('can:pos.receipts.view');
        Route::livewire('/{id}', Livewire\Receipts\Show::class)->name('show')->middleware('can:pos.receipts.view')->whereNumber('id');
        // The cashier may print their own receipts without pos.receipts.view (checked in the controller).
        Route::get('/{id}/print', ReceiptPrintController::class)->name('print')->whereNumber('id');
    });

    // ?receipt={number}
    Route::livewire('/returns/create', Livewire\Returns\Form::class)->name('returns.create')->middleware('can:pos.returns.create');

    Route::prefix('shifts')->name('shifts.')->group(function () {
        Route::livewire('/', Livewire\Shifts\Index::class)->name('index')->middleware('can:pos.shifts.view');
        // Own shift, or pos.shifts.view (checked in the component).
        Route::livewire('/{id}', Livewire\Shifts\Show::class)->name('show')->whereNumber('id');
    });

    Route::livewire('/registers', Livewire\Registers\Index::class)->name('registers.index')->middleware('can:pos.registers.manage');
});
