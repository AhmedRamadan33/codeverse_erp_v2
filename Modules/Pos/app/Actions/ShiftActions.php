<?php

namespace Modules\Pos\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\JournalType;
use Modules\Accounting\Mappings\AccountResolver;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Accounting\Posting\EntryBuilder;
use Modules\Accounting\Posting\JournalEntryData;
use Modules\Accounting\Posting\PostJournalEntry;
use Modules\Core\Sequences\NextNumber;
use Modules\Inventory\Stock\Actions\PostDeferredValuation;
use Modules\Pos\Enums\ReceiptKind;
use Modules\Pos\Enums\ShiftStatus;
use Modules\Pos\Models\Receipt;
use Modules\Pos\Models\Register;
use Modules\Pos\Models\Shift;
use Modules\Pos\Support\ShiftSummary;

/**
 * Cashier shifts (core-design.md §7.1): paid receipts post nothing on their own; closing the
 * shift posts one entry for them, the cash over/short, and one valuation entry for their stock.
 */
class ShiftActions
{
    public function __construct(
        private readonly AccountResolver $accounts,
        private readonly PostJournalEntry $postEntry,
        private readonly PostDeferredValuation $postValuation,
        private readonly NextNumber $numbers,
    ) {}

    public function open(User $actor, Register $register, string $openingFloat): Shift
    {
        Gate::forUser($actor)->authorize('pos.terminal.sell');

        if (! $register->is_active || ! $actor->canAccessBranch($register->branch_id)) {
            throw ValidationException::withMessages(['register_id' => __('pos::shifts.register_not_allowed')]);
        }

        return DB::transaction(function () use ($actor, $register, $openingFloat) {
            Register::whereKey($register->id)->lockForUpdate()->first();

            if (Shift::where('register_id', $register->id)->where('status', ShiftStatus::Open)->exists()) {
                throw ValidationException::withMessages(['register_id' => __('pos::shifts.register_busy')]);
            }

            if (Shift::where('user_id', $actor->id)->where('status', ShiftStatus::Open)->exists()) {
                throw ValidationException::withMessages(['register_id' => __('pos::shifts.already_open')]);
            }

            $now = CarbonImmutable::now();

            return Shift::create([
                'register_id' => $register->id,
                'branch_id' => $register->branch_id,
                'user_id' => $actor->id,
                'status' => ShiftStatus::Open,
                'opened_at' => $now,
                'opening_float' => BigDecimal::of($openingFloat)->toScale(4),
                'number' => $this->numbers->handle(Shift::SEQUENCE, $register->branch_id, $now),
            ]);
        });
    }

    /**
     * The open shift of a cashier, if any.
     */
    public function current(User $user): ?Shift
    {
        return Shift::where('user_id', $user->id)->where('status', ShiftStatus::Open)->first();
    }

    public function close(User $actor, Shift $shift, string $countedCash, ?string $notes = null): Shift
    {
        if ($shift->user_id !== $actor->id) {
            Gate::forUser($actor)->authorize('pos.shifts.manage');
        } else {
            Gate::forUser($actor)->authorize('pos.terminal.sell');
        }

        return DB::transaction(function () use ($actor, $shift, $countedCash, $notes) {
            // Receipts lock the shift too, so none can be added while it closes.
            $shift = Shift::whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if (! $shift->isOpen()) {
                throw ValidationException::withMessages(['shift' => __('pos::shifts.not_open')]);
            }

            $shift->load(['register.cashMethod', 'branch']);
            $date = CarbonImmutable::today();
            $summary = ShiftSummary::of($shift);
            $counted = BigDecimal::of($countedCash)->toScale(4);
            $difference = $counted->minus($summary->expectedCash);
            $description = __('pos::shifts.entry_description', ['number' => $shift->number]);

            // Paid receipts and returns of them; credit receipts posted their own entries.
            $receipts = $shift->receipts()->where('is_credit', false)->with(['lines.product.category', 'lines.tax', 'payments', 'partner'])->get();
            $journal = $this->postShiftEntry($shift, $receipts->all(), $difference, $date, $description, $actor);

            $valuation = $receipts->isEmpty() ? null : $this->postValuation->handle(
                [(new Receipt)->getMorphClass() => $receipts->modelKeys()],
                $shift,
                $date,
                $shift->branch_id,
                'inventory.cogs',
                $actor,
            );

            $shift->update([
                'status' => ShiftStatus::Closed,
                'closed_at' => now(),
                'closed_by' => $actor->id,
                'expected_cash' => $summary->expectedCash,
                'counted_cash' => $counted,
                'cash_difference' => $difference->toScale(4),
                'journal_entry_id' => $journal?->id,
                'valuation_entry_id' => $valuation?->id,
                'notes' => $notes,
            ]);

            return $shift;
        });
    }

    /**
     * Dr payment accounts / Cr revenue / Dr sales returns / Cr-Dr output tax, netted per account,
     * plus the cash over/short against the drawer.
     *
     * @param  Receipt[]  $receipts
     */
    private function postShiftEntry(Shift $shift, array $receipts, BigDecimal $difference, CarbonImmutable $date, string $description, User $actor)
    {
        /** @var array<int, BigDecimal> $net account id => debit (positive) or credit (negative) */
        $net = [];
        $add = function (int $accountId, BigDecimal $debit) use (&$net) {
            $net[$accountId] = ($net[$accountId] ?? BigDecimal::zero())->plus($debit);
        };
        $methodAccounts = PaymentMethod::pluck('account_id', 'id');

        foreach ($receipts as $receipt) {
            $sign = $receipt->kind === ReceiptKind::Sale ? 1 : -1;

            foreach ($receipt->payments as $payment) {
                $add($methodAccounts[$payment->payment_method_id], $payment->amount->multipliedBy($sign));
            }

            foreach ($receipt->lines as $line) {
                $scopes = [$line->product, $line->product->category, $receipt->partner, $shift->branch];
                $key = $sign === 1 ? 'sales.revenue' : 'sales.returns';
                $add($this->accounts->resolve($key, $scopes)->id, $line->net->multipliedBy(-$sign));

                if ($line->tax_amount->isPositive()) {
                    $add($this->accounts->resolve('tax.output', [$line->tax, $shift->branch])->id, $line->tax_amount->multipliedBy(-$sign));
                }
            }
        }

        if (! $difference->isZero()) {
            // Over: more cash than expected (Dr drawer / Cr difference); short: the other way.
            $add($shift->register->cashMethod->account_id, $difference);
            $add($this->accounts->resolve('pos.cash_difference', [$shift->register, $shift->branch])->id, $difference->negated());
        }

        $entry = new EntryBuilder;
        foreach ($net as $accountId => $amount) {
            if ($amount->isPositive()) {
                $entry->debit($accountId, $amount->toScale(4));
            } elseif ($amount->isNegative()) {
                $entry->credit($accountId, $amount->negated()->toScale(4));
            }
        }

        if ($entry->totalDebit()->isZero()) {
            return null;
        }

        return $this->postEntry->handle(new JournalEntryData(
            date: $date,
            branchId: $shift->branch_id,
            journalType: JournalType::Sales,
            lines: $entry->lines(),
            description: $description,
            source: $shift,
            postedBy: $actor,
        ));
    }
}
