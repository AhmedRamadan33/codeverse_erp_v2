<?php

namespace Modules\Accounting\FiscalYears\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Enums\EntryStatus;
use Modules\Accounting\Enums\JournalType;
use Modules\Accounting\Enums\PeriodStatus;
use Modules\Accounting\Ledger\Ledger;
use Modules\Accounting\Mappings\AccountResolver;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\FiscalYear;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Posting\JournalEntryData;
use Modules\Accounting\Posting\JournalLineData;
use Modules\Accounting\Posting\PostJournalEntry;
use Modules\Core\Models\Branch;

/**
 * Year-end closing (core-design.md §6.5): posts one closing entry per branch, dated the
 * last day of the year, that zeroes every income and expense account into retained
 * earnings, then closes the year and all its periods.
 */
class CloseFiscalYear
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly AccountResolver $accounts,
        private readonly PostJournalEntry $post,
    ) {}

    public function handle(User $actor, FiscalYear $year): FiscalYear
    {
        Gate::forUser($actor)->authorize('accounting.fiscal_years.manage');

        return DB::transaction(function () use ($actor, $year) {
            $year = FiscalYear::whereKey($year->id)->lockForUpdate()->firstOrFail();

            if ($year->status === PeriodStatus::Closed) {
                throw ValidationException::withMessages(['year' => __('accounting::fiscal.year_closed')]);
            }

            $earlierOpen = FiscalYear::where('end_date', '<', $year->start_date)->where('status', PeriodStatus::Open)->exists();
            if ($earlierOpen) {
                throw ValidationException::withMessages(['year' => __('accounting::fiscal.close_in_order')]);
            }

            $drafts = JournalEntry::where('status', EntryStatus::Draft)
                ->whereBetween('date', [$year->start_date->toDateString(), $year->end_date->toDateString()])
                ->exists();
            if ($drafts) {
                throw ValidationException::withMessages(['year' => __('accounting::fiscal.drafts_in_period')]);
            }

            $retained = $this->accounts->resolve('retained_earnings');
            $plAccounts = Account::whereIn('type', [AccountType::Income, AccountType::Expense])->pluck('id');

            // Reopen the last period for a moment if needed: the closing entry is dated in it.
            $year->periods()->update(['status' => PeriodStatus::Open]);

            foreach (Branch::pluck('id') as $branchId) {
                $balances = $this->ledger->lines($year->start_date, $year->end_date, $branchId)
                    ->whereIn('journal_lines.account_id', $plAccounts)
                    ->groupBy('journal_lines.account_id')
                    ->selectRaw('journal_lines.account_id, sum(debit) - sum(credit) as balance')
                    ->pluck('balance', 'account_id')
                    ->map(fn ($b) => BigDecimal::of($b)->toScale(4))
                    ->reject(fn (BigDecimal $b) => $b->isZero());

                if ($balances->isEmpty()) {
                    continue;
                }

                $lines = [];
                $net = BigDecimal::zero();
                foreach ($balances as $accountId => $balance) {
                    $lines[] = $balance->isPositive()
                        ? JournalLineData::credit($accountId, $balance)
                        : JournalLineData::debit($accountId, $balance->negated());
                    $net = $net->plus($balance);
                }

                // Net debit balance = loss (debit retained earnings); net credit = profit.
                if (! $net->isZero()) {
                    $lines[] = $net->isPositive()
                        ? JournalLineData::debit($retained->id, $net)
                        : JournalLineData::credit($retained->id, $net->negated());
                }

                $this->post->handle(new JournalEntryData(
                    date: $year->end_date,
                    branchId: $branchId,
                    journalType: JournalType::Closing,
                    lines: $lines,
                    description: __('accounting::fiscal.closing_entry', ['year' => $year->name]),
                    postedBy: $actor,
                ));
            }

            $year->periods()->update(['status' => PeriodStatus::Closed]);
            $year->update(['status' => PeriodStatus::Closed, 'closed_at' => now(), 'closed_by' => $actor->id]);

            return $year;
        });
    }
}
