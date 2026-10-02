<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Livewire;

// Every page component also authorizes in mount() and in each action.

Route::middleware('auth')->prefix('accounting')->name('accounting.')->group(function () {
    Route::livewire('/accounts', Livewire\Accounts\Index::class)->name('accounts.index')->middleware('can:accounting.accounts.view');

    Route::livewire('/entries', Livewire\Entries\Index::class)->name('entries.index')->middleware('can:accounting.entries.view');
    Route::livewire('/entries/create', Livewire\Entries\Form::class)->name('entries.create')->middleware('can:accounting.entries.create');
    Route::livewire('/entries/{id}', Livewire\Entries\Show::class)->name('entries.show')->middleware('can:accounting.entries.view')->whereNumber('id');
    Route::livewire('/entries/{id}/edit', Livewire\Entries\Form::class)->name('entries.edit')->middleware('can:accounting.entries.create')->whereNumber('id');

    // Receipts and payments share components; the kind comes from the route.
    foreach (['receipt' => 'receipts', 'payment' => 'payments'] as $kind => $path) {
        Route::prefix($path)->name("{$path}.")->group(function () use ($kind) {
            Route::livewire('/', Livewire\Vouchers\Index::class)->name('index')->defaults('kind', $kind)->middleware('can:accounting.vouchers.view');
            Route::livewire('/create', Livewire\Vouchers\Form::class)->name('create')->defaults('kind', $kind)->middleware('can:accounting.vouchers.create');
            Route::livewire('/{id}', Livewire\Vouchers\Show::class)->name('show')->defaults('kind', $kind)->middleware('can:accounting.vouchers.view')->whereNumber('id');
            Route::livewire('/{id}/edit', Livewire\Vouchers\Form::class)->name('edit')->defaults('kind', $kind)->middleware('can:accounting.vouchers.create')->whereNumber('id');
        });
    }

    Route::livewire('/fiscal-years',Livewire\FiscalYears\Index::class)->name('fiscal-years.index')->middleware('can:accounting.fiscal_years.manage');
    Route::livewire('/mappings', Livewire\Mappings\Index::class)->name('mappings.index')->middleware('can:accounting.mappings.manage');
    Route::livewire('/taxes', Livewire\Taxes\Index::class)->name('taxes.index')->middleware('can:accounting.taxes.manage');
    Route::livewire('/payment-methods', Livewire\PaymentMethods\Index::class)->name('payment-methods.index')->middleware('can:accounting.payment_methods.manage');
});
