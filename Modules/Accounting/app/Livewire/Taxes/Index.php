<?php

namespace Modules\Accounting\Livewire\Taxes;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Accounting\Enums\TaxScope;
use Modules\Accounting\Enums\TaxType;
use Modules\Accounting\Models\Tax;
use Modules\Accounting\Taxes\Actions\SaveTax;

#[Layout('core::layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        Gate::authorize('accounting.taxes.manage');
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = ['code' => '', 'name_ar' => '', 'name_en' => '', 'rate' => '', 'type' => TaxType::Percent->value,
            'scope' => TaxScope::Both->value, 'included_in_price' => false, 'is_active' => true];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $tax = Tax::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $tax->id;
        $this->form = [
            'code' => $tax->code,
            'name_ar' => $tax->getTranslation('name', 'ar', false),
            'name_en' => $tax->getTranslation('name', 'en', false),
            'rate' => (string) $tax->rate->strippedOfTrailingZeros(),
            'type' => $tax->type->value,
            'scope' => $tax->scope->value,
            'included_in_price' => $tax->included_in_price,
            'is_active' => $tax->is_active,
        ];
        $this->showForm = true;
    }

    public function save(SaveTax $action): void
    {
        $tax = $this->editingId ? Tax::findOrFail($this->editingId) : null;
        $rules = collect(SaveTax::rules($tax))->mapWithKeys(fn ($r, $k) => ["form.{$k}" => $r])->all();

        $action->handle(auth()->user(), $this->validate($rules)['form'], $tax);

        $this->showForm = false;
        session()->flash('status', __('core::ui.saved'));
    }

    public function render()
    {
        return view('accounting::livewire.taxes.index', [
            'taxes' => Tax::orderBy('code')->get(),
            'types' => TaxType::cases(),
            'scopes' => TaxScope::cases(),
        ])->title(__('accounting::menu.taxes'));
    }
}
