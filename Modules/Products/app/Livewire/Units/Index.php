<?php

namespace Modules\Products\Livewire\Units;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Products\Actions\SaveCatalogEntry;
use Modules\Products\Models\Unit;

#[Layout('core::layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        Gate::authorize('products.catalog.manage');
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = ['name_ar' => '', 'name_en' => '', 'symbol_ar' => '', 'symbol_en' => '', 'is_active' => true];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $unit = Unit::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $unit->id;
        $this->form = [
            'name_ar' => $unit->getTranslation('name', 'ar', false),
            'name_en' => $unit->getTranslation('name', 'en', false),
            'symbol_ar' => $unit->getTranslation('symbol', 'ar', false),
            'symbol_en' => $unit->getTranslation('symbol', 'en', false),
            'is_active' => $unit->is_active,
        ];
        $this->showForm = true;
    }

    public function save(SaveCatalogEntry $action): void
    {
        $rules = collect(SaveCatalogEntry::unitRules())->mapWithKeys(fn ($r, $k) => ["form.{$k}" => $r])->all();
        $action->unit(auth()->user(), $this->validate($rules)['form'], $this->editingId ? Unit::findOrFail($this->editingId) : null);

        $this->showForm = false;
        session()->flash('status', __('core::ui.saved'));
    }

    public function render()
    {
        return view('products::livewire.units.index', ['units' => Unit::orderBy('id')->get()])
            ->title(__('products::catalog.units'));
    }
}
