<?php

namespace Modules\Accounting\Livewire\PaymentMethods;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Enums\PaymentMethodType;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Accounting\PaymentMethods\Actions\SavePaymentMethod;

#[Layout('core::layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        Gate::authorize('accounting.payment_methods.manage');
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = ['name_ar' => '', 'name_en' => '', 'type' => PaymentMethodType::Cash->value, 'account_id' => null, 'sort' => 0, 'is_active' => true];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $method = PaymentMethod::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $method->id;
        $this->form = [
            'name_ar' => $method->getTranslation('name', 'ar', false),
            'name_en' => $method->getTranslation('name', 'en', false),
            'type' => $method->type->value,
            'account_id' => $method->account_id,
            'sort' => $method->sort,
            'is_active' => $method->is_active,
        ];
        $this->showForm = true;
    }

    public function save(SavePaymentMethod $action): void
    {
        $rules = collect(SavePaymentMethod::rules())->mapWithKeys(fn ($r, $k) => ["form.{$k}" => $r])->all();

        $action->handle(auth()->user(), $this->validate($rules)['form'], $this->editingId ? PaymentMethod::findOrFail($this->editingId) : null);

        $this->showForm = false;
        session()->flash('status', __('core::ui.saved'));
    }

    public function render()
    {
        return view('accounting::livewire.payment-methods.index', [
            'methods' => PaymentMethod::with('account')->orderBy('sort')->get(),
            'types' => PaymentMethodType::cases(),
            'accounts' => Account::where('is_active', true)->whereIn('subtype', [AccountSubtype::Cash, AccountSubtype::Bank])->orderBy('code')->get(),
        ])->title(__('accounting::menu.payment_methods'));
    }
}
