<?php

namespace Modules\Pos\Livewire\Registers;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Accounting\Enums\PaymentMethodType;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Inventory\Models\Warehouse;
use Modules\Pos\Actions\RegisterActions;
use Modules\Pos\Enums\ShiftStatus;
use Modules\Pos\Models\Register;
use Modules\Sales\Models\PriceList;

#[Layout('core::layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        Gate::authorize('pos.registers.manage');
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = [
            'code' => '', 'name_ar' => '', 'name_en' => '', 'warehouse_id' => Warehouse::where('is_active', true)->orderBy('code')->value('id'),
            'cash_payment_method_id' => PaymentMethod::where('type', PaymentMethodType::Cash)->where('is_active', true)->orderBy('sort')->value('id'),
            'price_list_id' => null, 'is_active' => true,
        ];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $register = Register::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $register->id;
        $this->form = [
            'code' => $register->code,
            'name_ar' => $register->getTranslation('name', 'ar', false),
            'name_en' => $register->getTranslation('name', 'en', false),
            'warehouse_id' => $register->warehouse_id,
            'cash_payment_method_id' => $register->cash_payment_method_id,
            'price_list_id' => $register->price_list_id,
            'is_active' => $register->is_active,
        ];
        $this->showForm = true;
    }

    public function save(RegisterActions $actions): void
    {
        $register = $this->editingId ? Register::findOrFail($this->editingId) : null;
        $form = $this->form;
        $form['price_list_id'] = ($form['price_list_id'] ?? '') === '' ? null : $form['price_list_id'];
        $rules = collect(RegisterActions::rules($register))->mapWithKeys(fn ($r, $k) => ["form.{$k}" => $r])->all();

        $actions->save(auth()->user(), validator(['form' => $form], $rules)->validate()['form'], $register);

        $this->showForm = false;
        session()->flash('status', __('core::ui.saved'));
    }

    public function render()
    {
        return view('pos::livewire.registers.index', [
            'registers' => Register::with(['warehouse', 'cashMethod', 'priceList', 'shifts' => fn ($q) => $q->where('status', ShiftStatus::Open)->with('cashier')])->orderBy('code')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('code')->get(),
            'methods' => PaymentMethod::where('type', PaymentMethodType::Cash)->where('is_active', true)->orderBy('sort')->get(),
            'priceLists' => PriceList::where('is_active', true)->get(),
        ])->title(__('pos::registers.title'));
    }
}
