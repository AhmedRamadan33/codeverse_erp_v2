<?php

namespace Modules\Inventory\Livewire\Warehouses;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Models\Branch;
use Modules\Core\Settings\Settings;
use Modules\Inventory\Documents\Actions\SaveWarehouse;
use Modules\Inventory\Models\Warehouse;

#[Layout('core::layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public bool $allowNegative = false;

    public function mount(Settings $settings): void
    {
        Gate::authorize('inventory.warehouses.manage');

        $this->allowNegative = (bool) $settings->get('inventory.allow_negative_stock');
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = ['name_ar' => '', 'name_en' => '', 'code' => '', 'branch_id' => Branch::value('id'), 'is_active' => true];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $warehouse = Warehouse::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $warehouse->id;
        $this->form = [
            'name_ar' => $warehouse->getTranslation('name', 'ar', false),
            'name_en' => $warehouse->getTranslation('name', 'en', false),
            'code' => $warehouse->code,
            'branch_id' => $warehouse->branch_id,
            'is_active' => $warehouse->is_active,
        ];
        $this->showForm = true;
    }

    public function save(SaveWarehouse $action): void
    {
        $warehouse = $this->editingId ? Warehouse::findOrFail($this->editingId) : null;
        $rules = collect(SaveWarehouse::rules($warehouse))->mapWithKeys(fn ($r, $k) => ["form.{$k}" => $r])->all();

        $action->handle(auth()->user(), $this->validate($rules)['form'], $warehouse);

        $this->showForm = false;
        session()->flash('status', __('core::ui.saved'));
    }

    public function updatedAllowNegative(bool $value, SaveWarehouse $action): void
    {
        $action->setNegativeStock(auth()->user(), $value);
    }

    public function render()
    {
        return view('inventory::livewire.warehouses.index', [
            'warehouses' => Warehouse::with('branch')->orderBy('code')->get(),
            'branches' => Branch::where('is_active', true)->orderBy('code')->get(),
        ])->title(__('inventory::stock.warehouses'));
    }
}
