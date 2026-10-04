<?php

namespace Modules\Accounting\Ledger;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Enums\EntryStatus;
use Modules\Core\Models\Partner;

/**
 * Aged receivables / payables: each partner's open documents (what reconciliation has not
 * matched yet) by how long they are past due, as of today. Payments and credit notes not yet
 * allocated to a document are shown apart, so the total equals the partner's balance.
 */
class Aging
{
    /** Upper day limits of the overdue buckets; anything older goes in the last one. */
    public const BUCKETS = [30, 60, 90];

    /**
     * @return Collection<int, AgingRow> sorted by partner name
     */
    public function report(AccountSubtype $subtype, CarbonImmutable $today, ?int $branchId = null): Collection
    {
        // Receivables are opened by debits (invoices); payables by credits (bills).
        [$openSide, $otherSide] = $subtype === AccountSubtype::Payable ? ['credit', 'debit'] : ['debit', 'credit'];

        $lines = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.status', EntryStatus::Posted->value)
            ->where('a.subtype', $subtype->value)
            ->whereNotNull('l.partner_id')
            ->when($branchId, fn ($q) => $q->where('e.branch_id', $branchId))
            ->select([
                'l.partner_id', 'l.debit', 'l.credit', 'l.due_date', 'e.date',
                'matched_as_debit' => DB::table('reconciliations')->selectRaw('coalesce(sum(amount), 0)')->whereColumn('debit_line_id', 'l.id'),
                'matched_as_credit' => DB::table('reconciliations')->selectRaw('coalesce(sum(amount), 0)')->whereColumn('credit_line_id', 'l.id'),
            ])
            ->get();

        /** @var array<int, array<string, BigDecimal>> $totals */
        $totals = [];
        $zero = fn () => array_fill_keys(['current', ...array_map(fn ($d) => "d{$d}", self::BUCKETS), 'older', 'unallocated'], BigDecimal::zero());

        foreach ($lines as $line) {
            $row = &$totals[$line->partner_id];
            $row ??= $zero();
            $open = BigDecimal::of($line->{$openSide})->minus(BigDecimal::of($line->{"matched_as_{$openSide}"}));
            $other = BigDecimal::of($line->{$otherSide})->minus(BigDecimal::of($line->{"matched_as_{$otherSide}"}));

            if ($open->isPositive()) {
                $bucket = $this->bucket($line->due_date ?? $line->date, $today);
                $row[$bucket] = $row[$bucket]->plus($open);
            }
            if ($other->isPositive()) {
                $row['unallocated'] = $row['unallocated']->plus($other);
            }
            unset($row);
        }

        $names = Partner::whereKey(array_keys($totals))->pluck('name', 'id');

        return collect($totals)
            ->map(fn (array $amounts, int $partnerId) => new AgingRow($partnerId, (string) $names[$partnerId], $amounts))
            ->reject(fn (AgingRow $row) => $row->total()->isZero() && $row->amounts['unallocated']->isZero())
            ->sortBy('partnerName')
            ->values();
    }

    private function bucket(string $dueDate, CarbonImmutable $today): string
    {
        $overdue = (int) CarbonImmutable::parse($dueDate)->diffInDays($today, false);
        if ($overdue <= 0) {
            return 'current';
        }

        foreach (self::BUCKETS as $limit) {
            if ($overdue <= $limit) {
                return "d{$limit}";
            }
        }

        return 'older';
    }
}
