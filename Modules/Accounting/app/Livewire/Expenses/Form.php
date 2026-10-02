<?php

namespace Modules\Accounting\Livewire\Expenses;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Enums\TaxScope;
use Modules\Accounting\Expenses\Actions\SaveExpenseVoucher;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\ExpenseVoucher;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Accounting\Models\Tax;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Partner;
use Throwable;

#[Layout('core::layouts.app')]
class Form extends Component
{
    public ?int $voucherId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(Currencies $currencies, ?int $id = null): void
    {
        Gate::authorize('accounting.vouchers.create');
        $user = auth()->user();

        if ($id === null) {
            $this->form = [
                'date' => now()->toDateString(),
                'branch_id' => $user->branches()->wherePivot('is_default', true)->value('branches.id') ?? $user->branches()->value('branches.id'),
                'payment_method_id' => PaymentMethod::where('is_active', true)->orderBy('sort')->value('id'),
                'partner_id' => null,
                'currency_id' => $currencies->base()->id,
                'exchange_rate' => '1',
                'reference' => '',
                'description' => '',
                'lines' => [$this->blankLine()],
            ];

            return;
        }

        $voucher = ExpenseVoucher::with('lines')->findOrFail($id);
        abort_unless($voucher->status === DocumentStatus::Draft && $user->canAccessBranch($voucher->branch_id), 404);

        $this->voucherId = $voucher->id;
        $this->form = [
            'date' => $voucher->date->toDateString(),
            'branch_id' => $voucher->branch_id,
            'payment_method_id' => $voucher->payment_method_id,
            'partner_id' => $voucher->partner_id,
            'currency_id' => $voucher->currency_id,
            'exchange_rate' => (string) $voucher->exchange_rate->strippedOfTrailingZeros(),
            'reference' => $voucher->reference,
            'description' => $voucher->description,
            'lines' => $voucher->lines->map(fn ($l) => [
                'account_id' => $l->account_id,
                'description' => $l->description,
                'amount' => (string) $l->amount->strippedOfTrailingZeros(),
                'tax_id' => $l->tax_id,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blankLine(): array
    {
        return ['account_id' => null, 'description' => '', 'amount' => '', 'tax_id' => null];
    }

    public function addLine(): void
    {
        $this->form['lines'][] = $this->blankLine();
    }

    public function removeLine(int $i): void
    {
        unset($this->form['lines'][$i]);
        $this->form['lines'] = array_values($this->form['lines']);
    }

    public function save(SaveExpenseVoucher $action): void
    {
        $rules = collect(SaveExpenseVoucher::rules())->mapWithKeys(fn ($r, $k) => ["form.{$k}" => $r])->all();
        $voucher = $this->voucherId ? ExpenseVoucher::findOrFail($this->voucherId) : null;

        $voucher = $action->handle(auth()->user(), $this->validate($rules)['form'], $voucher);

        session()->flash('status', __('core::ui.saved'));
        $this->redirectRoute('accounting.expenses.show', $voucher->id);
    }

    /**
     * Live preview of totals; the action recomputes them on save.
     *
     * @return array{subtotal: BigDecimal, tax: BigDecimal}
     */
    private function preview(int $scale): array
    {
        $taxes = Tax::whereKey(array_filter(array_column($this->form['lines'], 'tax_id')))->get()->keyBy('id');
        $subtotal = BigDecimal::zero();
        $tax = BigDecimal::zero();

        foreach ($this->form['lines'] as $line) {
            try {
                $amount = BigDecimal::of($line['amount'] !== '' ? $line['amount'] : 0);
            } catch (Throwable) {
                continue;
            }
            $subtotal = $subtotal->plus($amount);
            if (! empty($line['tax_id']) && isset($taxes[$line['tax_id']])) {
                $tax = $tax->plus($taxes[$line['tax_id']]->amountOn($amount, $scale));
            }
        }

        return ['subtotal' => $subtotal, 'tax' => $tax];
    }

    public function render(Currencies $currencies)
    {
        $user = auth()->user();
        $currency = Currency::find($this->form['currency_id']) ?? $currencies->base();

        return view('accounting::livewire.expenses.form', [
            'accounts' => Account::where('is_active', true)->where('is_group', false)->where('requires_partner', false)
                ->whereIn('type', [AccountType::Expense, AccountType::Asset, AccountType::Liability])
                ->orderByRaw("type = 'expense' desc")->orderBy('code')->get(),
            'taxes' => Tax::where('is_active', true)->where('scope', '!=', TaxScope::Sales)->orderBy('code')->get(),
            'methods' => PaymentMethod::where('is_active', true)->orderBy('sort')->get(),
            'partners' => Partner::visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(),
            'branches' => $user->can('core.branches.all_access') ? Branch::where('is_active', true)->get() : $user->branches,
            'isBase' => $currencies->isBase($currency),
            'preview' => $this->preview($currency->decimal_places),
            'scale' => $currency->decimal_places,
        ])->title(__('accounting::expenses.new'));
    }
}
