<?php

use Illuminate\Support\Facades\Route;
use Modules\Purchases\Http\Controllers\PrintController;
use Modules\Purchases\Livewire;

// Every page component also authorizes in mount() and in each action.

Route::middleware('auth')->prefix('purchases')->name('purchases.')->group(function () {
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::livewire('/', Livewire\Invoices\Index::class)->name('index')->middleware('can:purchases.invoices.view');
        Route::livewire('/create', Livewire\Invoices\Form::class)->name('create')->middleware('can:purchases.invoices.create');
        Route::livewire('/{id}', Livewire\Invoices\Show::class)->name('show')->middleware('can:purchases.invoices.view')->whereNumber('id');
        Route::livewire('/{id}/edit', Livewire\Invoices\Form::class)->name('edit')->middleware('can:purchases.invoices.create')->whereNumber('id');
        Route::get('/{id}/print', [PrintController::class, 'invoice'])->name('print')->middleware('can:purchases.invoices.view')->whereNumber('id');
    });

    Route::livewire('/reports/analysis', Livewire\Reports\PurchasesAnalysis::class)->name('reports.analysis')->middleware('can:purchases.reports.view');

    Route::prefix('returns')->name('returns.')->group(function () {
        Route::livewire('/', Livewire\Returns\Index::class)->name('index')->middleware('can:purchases.returns.view');
        // ?invoice={id}
        Route::livewire('/create', Livewire\Returns\Form::class)->name('create')->middleware('can:purchases.returns.create');
        Route::livewire('/{id}', Livewire\Returns\Show::class)->name('show')->middleware('can:purchases.returns.view')->whereNumber('id');
        Route::livewire('/{id}/edit', Livewire\Returns\Form::class)->name('edit')->middleware('can:purchases.returns.create')->whereNumber('id');
        Route::get('/{id}/print', [PrintController::class, 'return'])->name('print')->middleware('can:purchases.returns.view')->whereNumber('id');
    });
});
