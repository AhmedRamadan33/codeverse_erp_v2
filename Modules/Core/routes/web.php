<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\SessionController;
use Modules\Core\Livewire\Auth\Login;
use Modules\Core\Livewire\Dashboard;
use Modules\Core\Livewire\Partners;

// Every page component also authorizes in mount(); the middleware here is the first line only.

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Login::class)->name('login');
});

Route::post('/locale', [SessionController::class, 'updateLocale'])->name('core.locale.update');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionController::class, 'logout'])->name('logout');
    Route::livewire('/dashboard', Dashboard::class)->name('core.dashboard');

    Route::prefix('partners')->name('core.partners.')->group(function () {
        Route::livewire('/', Partners\Index::class)->name('index')->middleware('can:core.partners.view');
        Route::livewire('/create', Partners\Form::class)->name('create')->middleware('can:core.partners.create');
        Route::livewire('/{id}/edit', Partners\Form::class)->name('edit')->middleware('can:core.partners.update')->whereNumber('id');
    });
});
