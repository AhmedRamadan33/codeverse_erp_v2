<?php

namespace Modules\Accounting\Livewire\Vouchers;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Accounting\Models\CashVoucher;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Accounting\Vouchers\Actions\SaveVoucher;
use Modules\Accounting\Vouchers\VoucherKind;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Partner;
use Throwable;

#[Layout('core::layouts.app')]
class Form extends Component
{
    public string $kind;

    public ?int $voucherId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(Currencies $currencies, string $kind, ?int $id = null): void
    {
        Gate::authorize('accounting.vouchers.create');

        $this->kind = VoucherKind::from($kind)->value;
        $user = auth()->user();

        if ($id === null) {
            $this->form = [
                'date' => now()->toDateString(),
                'branch_id' => $user->branches()->wherePivot('is_default', true)->value('branches.id') ?? $user->branches()->value('branches.id'),
                'partner_id' => null,
                'payment_method_id' => PaymentMethod::where('is_active', true)->orderBy('sort')->value('id'),
                'currency_id' => $currencies->base()->id,
                'exchange_rate' => '1',
                'amount' => '',
                'reference' => '',
                'description' => '',
            ];

            return;
        }

        $voucher = $this->model()::findOrFail($id);
        abort_unless($voucher->status === DocumentStatus::Draft && $user->canAccessBranch($voucher->branch_id), 404);

        $this->voucherId = $voucher->id;
        $this->form = [
            'date' => $voucher->date->toDateString(),
            'branch_id' => $voucher->branch_id,
            'partner_id' => $voucher->partner_id,
            'payment_method_id' => $voucher->payment_method_id,
            'currency_id' => $voucher->currency_id,
            'exchange_rate' => (string) $voucher->exchange_rate->strippedOfTrailingZeros(),
            'amount' => (string) $voucher->amount->strippedOfTrailingZeros(),
            'reference' => $voucher->reference,
            'description' => $voucher->description,
        ];
    }

    /**
     * @return class-string<CashVoucher>
     */
    private function model(): string
    {
        return VoucherKind::from($this->kind)->model();
    }

    /**
     * Suggest the latest rate when a foreign currency is chosen.
     */
    public function updatedFormCurrencyId($currencyId, Currencies $currencies): void
    {
        try {
            $this->form['exchange_rate'] = (string) $currencies->rate(Currency::findOrFail($currencyId), now())->strippedOfTrailingZeros();
        } catch (Throwable) {
            $this->form['exchange_rate'] = '';
        }
    }

    public function save(SaveVoucher $action): void
    {
        $rules = collect(SaveVoucher::rules())->mapWithKeys(fn ($r, $k) => ["form.{$k}" => $r])->all();
        $voucher = $this->voucherId ? $this->model()::findOrFail($this->voucherId) : null;

        $voucher = $action->handle(auth()->user(), VoucherKind::from($this->kind), $this->validate($rules)['form'], $voucher);

        session()->flash('status', __('core::ui.saved'));
        $this->redirectRoute(VoucherKind::from($this->kind)->routePrefix().'show', $voucher->id);
    }

    public function render(Currencies $currencies)
    {
        $kind = VoucherKind::from($this->kind);
        $user = auth()->user();

        return view('accounting::livewire.vouchers.form', [
            'kindEnum' => $kind,
            'partners' => Partner::visibleTo($user)->where('is_active', true)
                ->when($kind === VoucherKind::Receipt, fn ($q) => $q->orderByDesc('is_customer'))
                ->when($kind === VoucherKind::Payment, fn ($q) => $q->orderByDesc('is_supplier'))
                ->orderBy('name')->get(['id', 'name']),
            'methods' => PaymentMethod::where('is_active', true)->orderBy('sort')->get(),
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(),
            'branches' => $user->can('core.branches.all_access') ? Branch::where('is_active', true)->get() : $user->branches,
            'isBase' => (int) $this->form['currency_id'] === $currencies->base()->id,
        ])->title(__('accounting::vouchers.new.'.$kind->value));
    }
}
