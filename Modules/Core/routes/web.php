<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\SessionController;
use Modules\Core\Livewire;

// Every page component also authorizes in mount() and in each action; the middleware here is the first line only.

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Livewire\Auth\Login::class)->name('login');
});

Route::post('/locale', [SessionController::class, 'updateLocale'])->name('core.locale.update');

Route::middleware('auth')->name('core.')->group(function () {
    Route::post('/logout', [SessionController::class, 'logout'])->name('logout');
    Route::livewire('/dashboard', Livewire\Dashboard::class)->name('dashboard');

    Route::prefix('partners')->name('partners.')->group(function () {
        Route::livewire('/', Livewire\Partners\Index::class)->name('index')->middleware('can:core.partners.view');
        Route::livewire('/create', Livewire\Partners\Form::class)->name('create')->middleware('can:core.partners.create');
        Route::livewire('/{id}/edit', Livewire\Partners\Form::class)->name('edit')->middleware('can:core.partners.update')->whereNumber('id');
    });

    Route::prefix('admin')->group(function () {
        Route::livewire('/users', Livewire\Users\Index::class)->name('users.index')->middleware('can:core.users.view');
        Route::livewire('/users/create', Livewire\Users\Form::class)->name('users.create')->middleware('can:core.users.manage');
        Route::livewire('/users/{id}/edit', Livewire\Users\Form::class)->name('users.edit')->middleware('can:core.users.manage')->whereNumber('id');

        Route::livewire('/roles', Livewire\Roles\Index::class)->name('roles.index')->middleware('can:core.roles.manage');
        Route::livewire('/roles/create', Livewire\Roles\Form::class)->name('roles.create')->middleware('can:core.roles.manage');
        Route::livewire('/roles/{id}/edit', Livewire\Roles\Form::class)->name('roles.edit')->middleware('can:core.roles.manage')->whereNumber('id');

        Route::livewire('/branches', Livewire\Branches\Index::class)->name('branches.index')->middleware('can:core.branches.view');
        Route::livewire('/settings', Livewire\Settings\Company::class)->name('settings.edit')->middleware('can:core.settings.manage');
        Route::livewire('/currencies', Livewire\Currencies\Index::class)->name('currencies.index')->middleware('can:core.currencies.manage');
        Route::livewire('/exchange-rates', Livewire\ExchangeRates\Index::class)->name('exchange-rates.index')->middleware('can:core.exchange_rates.manage');
        Route::livewire('/sequences', Livewire\Sequences\Index::class)->name('sequences.index')->middleware('can:core.sequences.manage');
        Route::livewire('/audit', Livewire\Audit\Index::class)->name('audit.index')->middleware('can:core.audit.view');
        Route::livewire('/modules', Livewire\Modules\Index::class)->name('modules.index')->middleware('can:core.modules.manage');
    });
});
