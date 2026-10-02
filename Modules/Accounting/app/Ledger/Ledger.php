<?php

namespace Modules\Accounting\Ledger;

use Brick\Math\BigDecimal;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Enums\EntryStatus;
use Modules\Accounting\Models\Account;
use Modules\Core\Models\Partner;
use Modules\Core\Support\Attributes\ModuleApi;

/**
 * Read side of the ledger. Every balance is computed from posted journal lines
 * (core-design.md principle 3); nothing here writes.
 * Balances are signed debit minus credit; callers flip the sign for credit-normal accounts.
 */
#[ModuleApi]
class Ledger
{
    /**
     * Posted lines, optionally limited to a date range and a branch.
     */
    public function lines(?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?int $branchId = null): Builder
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.status', EntryStatus::Posted->value)
            ->when($from, fn ($q) => $q->whereDate('journal_entries.date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('journal_entries.date', '<=', $to))
            ->when($branchId, fn ($q) => $q->where('journal_lines.branch_id', $branchId));
    }

    /**
     * Debit minus credit of an account (and its children, for a group account) up to a date.
     */
    public function accountBalance(Account $account, ?DateTimeInterface $to = null, ?int $branchId = null): BigDecimal
    {
        return $this->sum($this->lines(null, $to, $branchId)->whereIn('journal_lines.account_id', $this->leafIds($account)));
    }

    /**
     * What a customer owes us (positive) on receivable accounts, or what we owe a supplier
     * (negative) on payable accounts, up to a date.
     */
    public function partnerBalance(Partner $partner, AccountSubtype $subtype, ?DateTimeInterface $to = null): BigDecimal
    {
        $accounts = Account::where('subtype', $subtype)->pluck('id');

        return $this->sum($this->lines(null, $to)
            ->where('journal_lines.partner_id', $partner->id)
            ->whereIn('journal_lines.account_id', $accounts));
    }

    /**
     * Per leaf account: opening balance before $from, debits and credits within the range, closing balance.
     *
     * @return Collection<int, TrialBalanceRow>
     */
    public function trialBalance(DateTimeInterface $from, DateTimeInterface $to, ?int $branchId = null): Collection
    {
        $opening = $this->lines(null, null, $branchId)
            ->whereDate('journal_entries.date', '<', $from)
            ->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id, sum(debit) - sum(credit) as balance')
            ->pluck('balance', 'account_id');

        $movement = $this->lines($from, $to, $branchId)
            ->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id, sum(debit) as debit, sum(credit) as credit')
            ->get()
            ->keyBy('account_id');

        $ids = $opening->keys()->merge($movement->keys())->unique();

        return Account::whereKey($ids)->orderBy('code')->get()->map(fn (Account $account) => new TrialBalanceRow(
            account: $account,
            opening: BigDecimal::of($opening[$account->id] ?? 0)->toScale(4),
            debit: BigDecimal::of($movement[$account->id]->debit ?? 0)->toScale(4),
            credit: BigDecimal::of($movement[$account->id]->credit ?? 0)->toScale(4),
        ));
    }

    /**
     * Opening balance before $from, then every posted line in the range with a running balance.
     * Used by the general ledger (per account) and the partner statement (per partner).
     *
     * @param  int[]  $accountIds
     * @return array{opening: BigDecimal, rows: Collection<int, StatementRow>, closing: BigDecimal, debit: BigDecimal, credit: BigDecimal}
     */
    public function statement(array $accountIds, DateTimeInterface $from, DateTimeInterface $to, ?int $branchId = null, ?int $partnerId = null): array
    {
        $scope = fn (Builder $q) => $q
            ->whereIn('journal_lines.account_id', $accountIds)
            ->when($partnerId, fn ($q) => $q->where('journal_lines.partner_id', $partnerId));

        $opening = $this->sum($scope($this->lines(null, null, $branchId)->whereDate('journal_entries.date', '<', $from)));

        $balance = $opening;
        $debits = BigDecimal::zero();
        $credits = BigDecimal::zero();

        $rows = $scope($this->lines($from, $to, $branchId))
            ->leftJoin('partners', 'partners.id', '=', 'journal_lines.partner_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->orderBy('journal_entries.date')->orderBy('journal_entries.id')->orderBy('journal_lines.line_no')
            ->get([
                'journal_entries.id as entry_id', 'journal_entries.number', 'journal_entries.date',
                'journal_entries.description as entry_description', 'journal_entries.source_type', 'journal_entries.source_id',
                'journal_lines.description', 'journal_lines.debit', 'journal_lines.credit',
                'partners.name as partner_name', 'accounts.code as account_code',
            ])
            ->map(function ($row) use (&$balance, &$debits, &$credits) {
                $debit = BigDecimal::of($row->debit)->toScale(4);
                $credit = BigDecimal::of($row->credit)->toScale(4);
                $balance = $balance->plus($debit)->minus($credit);
                $debits = $debits->plus($debit);
                $credits = $credits->plus($credit);

                return new StatementRow(
                    entryId: $row->entry_id,
                    number: $row->number,
                    date: $row->date,
                    description: $row->description ?? $row->entry_description,
                    partnerName: $row->partner_name,
                    accountCode: $row->account_code,
                    debit: $debit,
                    credit: $credit,
                    balance: $balance,
                );
            });

        return ['opening' => $opening, 'rows' => $rows, 'closing' => $balance, 'debit' => $debits->toScale(4), 'credit' => $credits->toScale(4)];
    }

    /**
     * @return int[]
     */
    public function leafIds(Account $account): array
    {
        if (! $account->is_group) {
            return [$account->id];
        }

        // Codes are hierarchical in templates, but parents are the source of truth.
        $ids = [];
        $frontier = [$account->id];

        while ($frontier !== []) {
            $children = Account::whereIn('parent_id', $frontier)->get(['id', 'is_group']);
            $ids = array_merge($ids, $children->where('is_group', false)->pluck('id')->all());
            $frontier = $children->where('is_group', true)->pluck('id')->all();
        }

        return $ids;
    }

    private function sum(Builder $lines): BigDecimal
    {
        return BigDecimal::of($lines->selectRaw('coalesce(sum(debit), 0) - coalesce(sum(credit), 0) as balance')->value('balance') ?? 0)->toScale(4);
    }
}
