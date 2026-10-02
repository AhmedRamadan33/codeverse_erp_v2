<?php

namespace Modules\Accounting\Reconciliation;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\EntryStatus;
use Modules\Accounting\Exceptions\PostingException;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Models\Reconciliation;
use Modules\Core\Support\Attributes\ModuleApi;
use Modules\Core\Support\TransactionGuard;

/**
 * Matches credit lines (payments) with debit lines (invoices) of the same partner and
 * account. Balances never depend on this; it only answers "which items are still open".
 */
#[ModuleApi]
class Reconciler
{
    /**
     * The part of a line not yet matched, in base currency.
     */
    public function residual(JournalLine $line): BigDecimal
    {
        $isDebit = $line->debit->isPositive();
        $matched = Reconciliation::where($isDebit ? 'debit_line_id' : 'credit_line_id', $line->id)->sum('amount');

        return ($isDebit ? $line->debit : $line->credit)->minus(BigDecimal::of($matched ?: 0))->toScale(4);
    }

    /**
     * Posted lines of a partner on the given accounts that still have an open amount.
     *
     * @param  int[]  $accountIds
     * @return Collection<int, array{line: JournalLine, residual: BigDecimal}>
     */
    public function openLines(int $partnerId, array $accountIds, bool $debitSide): Collection
    {
        $side = $debitSide ? 'debit' : 'credit';
        $fk = $debitSide ? 'debit_line_id' : 'credit_line_id';

        return JournalLine::query()
            ->with('entry')
            ->where('partner_id', $partnerId)
            ->whereIn('account_id', $accountIds)
            ->where($side, '>', 0)
            ->whereHas('entry', fn (Builder $q) => $q->where('status', EntryStatus::Posted))
            ->addSelect(['matched' => Reconciliation::selectRaw('coalesce(sum(amount), 0)')->whereColumn($fk, 'journal_lines.id')])
            ->orderBy('journal_entry_id')
            ->get()
            ->map(fn (JournalLine $line) => [
                'line' => $line,
                'residual' => ($debitSide ? $line->debit : $line->credit)->minus(BigDecimal::of($line->matched))->toScale(4),
            ])
            ->filter(fn (array $row) => $row['residual']->isPositive())
            ->values();
    }

    public function reconcile(JournalLine $debit, JournalLine $credit, BigDecimal $amount, ?int $userId = null): Reconciliation
    {
        TransactionGuard::assertActive('Reconciler::reconcile');

        // Lock both lines so concurrent allocations cannot over-match them.
        $lines = JournalLine::whereKey([$debit->id, $credit->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $debit = $lines[$debit->id];
        $credit = $lines[$credit->id];

        if ($debit->account_id !== $credit->account_id || $debit->partner_id === null || $debit->partner_id !== $credit->partner_id) {
            throw PostingException::because('reconcile_mismatch', [], 'allocations');
        }

        if (! $debit->debit->isPositive() || ! $credit->credit->isPositive()
            || ! $debit->entry->isPosted() || ! $credit->entry->isPosted()) {
            throw PostingException::because('reconcile_sides', [], 'allocations');
        }

        $amount = $amount->toScale(4);

        if (! $amount->isPositive()
            || $amount->isGreaterThan($this->residual($debit))
            || $amount->isGreaterThan($this->residual($credit))) {
            throw PostingException::because('reconcile_amount', ['amount' => (string) $amount], 'allocations');
        }

        return Reconciliation::create([
            'debit_line_id' => $debit->id,
            'credit_line_id' => $credit->id,
            'amount' => $amount,
            'created_by' => $userId,
        ]);
    }

    /**
     * Removes every match involving the line (used when its document is cancelled).
     */
    public function unreconcile(JournalLine $line): void
    {
        TransactionGuard::assertActive('Reconciler::unreconcile');

        DB::table('reconciliations')
            ->where('debit_line_id', $line->id)
            ->orWhere('credit_line_id', $line->id)
            ->delete();
    }
}
