<?php

namespace Modules\Accounting\Expenses\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Enums\TaxScope;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\ExpenseVoucher;
use Modules\Accounting\Models\Tax;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Models\Currency;

/**
 * Creates or edits a draft expense voucher; line taxes and totals are computed here
 * and stored, so the printed voucher never changes.
 */
class SaveExpenseVoucher
{
    public function __construct(private readonly Currencies $currencies) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('is_active', true)],
            'partner_id' => ['nullable', 'integer', Rule::exists('partners', 'id')],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
            'exchange_rate' => ['nullable', 'decimal:0,6', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('is_group', false)->where('is_active', true)],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.amount' => ['required', 'decimal:0,4', 'gt:0', 'max:99999999999999'],
            'lines.*.tax_id' => ['nullable', 'integer', Rule::exists('taxes', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function handle(User $actor, array $data, ?ExpenseVoucher $voucher = null): ExpenseVoucher
    {
        Gate::forUser($actor)->authorize('accounting.vouchers.create');

        if ($voucher !== null && $voucher->status !== DocumentStatus::Draft) {
            throw ValidationException::withMessages(['voucher' => __('core::documents.not_draft')]);
        }

        if (! $actor->canAccessBranch((int) $data['branch_id'])) {
            throw ValidationException::withMessages(['branch_id' => __('accounting::entries.branch_not_allowed')]);
        }

        $currency = Currency::findOrFail($data['currency_id']);
        $isBase = $this->currencies->isBase($currency);
        $scale = $currency->decimal_places;

        if (! $isBase && empty($data['exchange_rate'])) {
            throw ValidationException::withMessages(['exchange_rate' => __('accounting::vouchers.rate_required')]);
        }

        $accounts = Account::whereKey(array_column($data['lines'], 'account_id'))->get()->keyBy('id');
        $taxes = Tax::whereKey(array_filter(array_column($data['lines'], 'tax_id')))->get()->keyBy('id');
        $lines = [];
        $subtotal = BigDecimal::zero();
        $taxTotal = BigDecimal::zero();

        foreach (array_values($data['lines']) as $i => $line) {
            $account = $accounts[$line['account_id']];

            // Expense vouchers pay costs and prepaid/asset items, never revenue or partner balances.
            if (in_array($account->type, [AccountType::Income, AccountType::Equity], true) || $account->requires_partner) {
                throw ValidationException::withMessages(["lines.{$i}.account_id" => __('accounting::expenses.account_not_allowed')]);
            }

            try {
                $amount = BigDecimal::of((string) $line['amount'])->toScale($scale, RoundingMode::Unnecessary);
            } catch (RoundingNecessaryException) {
                throw ValidationException::withMessages(["lines.{$i}.amount" => __('accounting::vouchers.too_many_decimals', ['places' => $scale])]);
            }

            $tax = ! empty($line['tax_id']) ? $taxes[$line['tax_id']] : null;

            if ($tax && $tax->scope === TaxScope::Sales) {
                throw ValidationException::withMessages(["lines.{$i}.tax_id" => __('accounting::expenses.sales_tax')]);
            }

            $taxAmount = $tax ? $tax->amountOn($amount, $scale) : BigDecimal::zero()->toScale($scale);

            $lines[] = [
                'line_no' => $i + 1,
                'account_id' => $account->id,
                'description' => $line['description'] ?? null,
                'amount' => $amount->toScale(4),
                'tax_id' => $tax?->id,
                'tax_rate' => $tax?->rate,
                'tax_amount' => $taxAmount->toScale(4),
            ];

            $subtotal = $subtotal->plus($amount);
            $taxTotal = $taxTotal->plus($taxAmount);
        }

        return DB::transaction(function () use ($actor, $data, $voucher, $isBase, $lines, $subtotal, $taxTotal) {
            $voucher ??= new ExpenseVoucher(['status' => DocumentStatus::Draft, 'created_by' => $actor->id]);
            $voucher->fill([
                'date' => $data['date'],
                'branch_id' => $data['branch_id'],
                'payment_method_id' => $data['payment_method_id'],
                'partner_id' => $data['partner_id'] ?? null,
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $isBase ? '1' : BigDecimal::of((string) $data['exchange_rate'])->toScale(6),
                'subtotal' => $subtotal->toScale(4),
                'tax_total' => $taxTotal->toScale(4),
                'total' => $subtotal->plus($taxTotal)->toScale(4),
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
            ])->save();

            $voucher->lines()->delete();
            $voucher->lines()->createMany($lines);

            return $voucher;
        });
    }
}
