<?php

namespace Modules\Accounting\Vouchers\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\CashVoucher;
use Modules\Accounting\Vouchers\VoucherKind;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Partner;

/**
 * Creates or edits a draft receipt or payment voucher.
 */
class SaveVoucher
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
            'partner_id' => ['required', 'integer', Rule::exists('partners', 'id')->where('is_active', true)],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('is_active', true)],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
            'exchange_rate' => ['nullable', 'decimal:0,6', 'gt:0'],
            'amount' => ['required', 'decimal:0,4', 'gt:0', 'max:99999999999999'],
            'reference' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function handle(User $actor, VoucherKind $kind, array $data, ?CashVoucher $voucher = null): CashVoucher
    {
        Gate::forUser($actor)->authorize('accounting.vouchers.create');

        if ($voucher !== null && $voucher->status !== DocumentStatus::Draft) {
            throw ValidationException::withMessages(['voucher' => __('core::documents.not_draft')]);
        }

        if (! $actor->canAccessBranch((int) $data['branch_id'])) {
            throw ValidationException::withMessages(['branch_id' => __('accounting::entries.branch_not_allowed')]);
        }

        $partner = Partner::visibleTo($actor)->find($data['partner_id'])
            ?? throw ValidationException::withMessages(['partner_id' => __('validation.exists', ['attribute' => 'partner'])]);

        $currency = Currency::findOrFail($data['currency_id']);
        $isBase = $this->currencies->isBase($currency);

        if (! $isBase && empty($data['exchange_rate'])) {
            throw ValidationException::withMessages(['exchange_rate' => __('accounting::vouchers.rate_required')]);
        }

        try {
            $amount = BigDecimal::of((string) $data['amount'])->toScale($currency->decimal_places, RoundingMode::Unnecessary);
        } catch (RoundingNecessaryException) {
            throw ValidationException::withMessages(['amount' => __('accounting::vouchers.too_many_decimals', ['places' => $currency->decimal_places])]);
        }

        return DB::transaction(function () use ($actor, $kind, $data, $voucher, $partner, $isBase, $amount) {
            $model = $kind->model();
            $voucher ??= new $model(['status' => DocumentStatus::Draft, 'created_by' => $actor->id]);

            $voucher->fill([
                'date' => $data['date'],
                'branch_id' => $data['branch_id'],
                'partner_id' => $partner->id,
                'payment_method_id' => $data['payment_method_id'],
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $isBase ? '1' : BigDecimal::of((string) $data['exchange_rate'])->toScale(6),
                'amount' => $amount->toScale(4),
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
            ])->save();

            return $voucher;
        });
    }

    public function delete(User $actor, CashVoucher $voucher): void
    {
        Gate::forUser($actor)->authorize('accounting.vouchers.create');

        if ($voucher->status !== DocumentStatus::Draft) {
            throw ValidationException::withMessages(['voucher' => __('core::documents.not_draft')]);
        }

        DB::transaction(fn () => $voucher->delete());
    }
}
