<?php

namespace Modules\Accounting\Vouchers\Actions;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\CashVoucher;
use Modules\Accounting\Posting\ReverseJournalEntry;
use Modules\Accounting\Reconciliation\Reconciler;
use Modules\Core\Documents\DocumentStatus;

/**
 * Cancels a posted voucher: its allocations are released and its entry is reversed.
 * The voucher keeps its number and stays visible as cancelled.
 */
class CancelVoucher
{
    public function __construct(
        private readonly Reconciler $reconciler,
        private readonly ReverseJournalEntry $reverse,
    ) {}

    public function handle(User $actor, CashVoucher $voucher, string $reason, ?CarbonImmutable $date = null): CashVoucher
    {
        Gate::forUser($actor)->authorize('accounting.vouchers.cancel');

        return DB::transaction(function () use ($actor, $voucher, $reason, $date) {
            $voucher = $voucher->kind()->model()::whereKey($voucher->id)->lockForUpdate()->firstOrFail();

            if ($voucher->status !== DocumentStatus::Posted) {
                throw ValidationException::withMessages(['voucher' => __('core::documents.not_posted')]);
            }

            $this->reconciler->unreconcile($voucher->partnerLine());
            $this->reverse->handle($voucher->journalEntry, $date ?? CarbonImmutable::today()->max($voucher->date), $reason, $actor);

            $voucher->update([
                'status' => DocumentStatus::Cancelled,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            return $voucher;
        });
    }
}
