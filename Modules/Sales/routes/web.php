<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\PrintController;
use Modules\Sales\Livewire;

// Every page component also authorizes in mount() and in each action.

Route::middleware('auth')->prefix('sales')->name('sales.')->group(function () {
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::livewire('/', Livewire\Invoices\Index::class)->name('index')->middleware('can:sales.invoices.view');
        // ?customer={id}
        Route::livewire('/create', Livewire\Invoices\Form::class)->name('create')->middleware('can:sales.invoices.create');
        Route::livewire('/{id}', Livewire\Invoices\Show::class)->name('show')->middleware('can:sales.invoices.view')->whereNumber('id');
        Route::livewire('/{id}/edit', Livewire\Invoices\Form::class)->name('edit')->middleware('can:sales.invoices.create')->whereNumber('id');
        Route::get('/{id}/print', [PrintController::class, 'invoice'])->name('print')->middleware('can:sales.invoices.view')->whereNumber('id');
    });

    Route::prefix('returns')->name('returns.')->group(function () {
        Route::livewire('/', Livewire\Returns\Index::class)->name('index')->middleware('can:sales.returns.view');
        // ?invoice={id}
        Route::livewire('/create', Livewire\Returns\Form::class)->name('create')->middleware('can:sales.returns.create');
        Route::livewire('/{id}', Livewire\Returns\Show::class)->name('show')->middleware('can:sales.returns.view')->whereNumber('id');
        Route::livewire('/{id}/edit', Livewire\Returns\Form::class)->name('edit')->middleware('can:sales.returns.create')->whereNumber('id');
        Route::get('/{id}/print', [PrintController::class, 'return'])->name('print')->middleware('can:sales.returns.view')->whereNumber('id');
    });

    Route::livewire('/reports/analysis', Livewire\Reports\SalesAnalysis::class)->name('reports.analysis')->middleware('can:sales.reports.view');

    Route::prefix('price-lists')->name('price-lists.')->middleware('can:sales.price_lists.manage')->group(function () {
        Route::livewire('/', Livewire\PriceLists\Index::class)->name('index');
        Route::livewire('/create', Livewire\PriceLists\Form::class)->name('create');
        Route::livewire('/{id}/edit', Livewire\PriceLists\Form::class)->name('edit')->whereNumber('id');
    });
});
