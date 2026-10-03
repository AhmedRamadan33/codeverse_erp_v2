<?php

use Illuminate\Support\Facades\Route;
use Modules\Products\Livewire;

Route::middleware('auth')->prefix('products')->name('products.')->group(function () {
    Route::livewire('/', Livewire\Products\Index::class)->name('products.index')->middleware('can:products.products.view');
    Route::livewire('/create', Livewire\Products\Form::class)->name('products.create')->middleware('can:products.products.create');
    Route::livewire('/{id}/edit', Livewire\Products\Form::class)->name('products.edit')->middleware('can:products.products.update')->whereNumber('id');
    Route::livewire('/categories', Livewire\Categories\Index::class)->name('categories.index')->middleware('can:products.catalog.manage');
    Route::livewire('/units', Livewire\Units\Index::class)->name('units.index')->middleware('can:products.catalog.manage');
});
