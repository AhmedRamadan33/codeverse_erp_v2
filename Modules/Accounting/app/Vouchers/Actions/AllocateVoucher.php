<?php

namespace Modules\Accounting\Vouchers\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\CashVoucher;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Reconciliation\Reconciler;
use Modules\Core\Documents\DocumentStatus;

/**
 * Settles open items (invoices, opening balances...) of the voucher's partner with the
 * voucher, fully or partially. Can run at posting time or any time later.
 */
class AllocateVoucher
{
    public function __construct(private readonly Reconciler $reconciler) {}

    /**
     * @param  array<int, string>  $allocations  journal line id => amount in base currency
     */
    public function handle(User $actor, CashVoucher $voucher, array $allocations): void
    {
        Gate::forUser($actor)->authorize('accounting.vouchers.post');

        DB::transaction(fn () => $this->apply($actor, $voucher, $allocations));
    }

    /**
     * Inside the caller's transaction (also used by PostVoucher).
     *
     * @param  array<int, string>  $allocations
     */
    public function apply(User $actor, CashVoucher $voucher, array $allocations): void
    {
        if ($voucher->status !== DocumentStatus::Posted) {
            throw ValidationException::withMessages(['allocations' => __('core::documents.not_posted')]);
        }

        $own = $voucher->partnerLine();
        $isCredit = $voucher->kind()->partnerLineIsCredit();

        foreach ($allocations as $lineId => $amount) {
            if ($amount === null || $amount === '' || BigDecimal::of($amount)->isZero()) {
                continue;
            }

            $other = JournalLine::findOrFail($lineId);

            $isCredit
                ? $this->reconciler->reconcile($other, $own, BigDecimal::of($amount), $actor->id)
                : $this->reconciler->reconcile($own, $other, BigDecimal::of($amount), $actor->id);
        }
    }
}
