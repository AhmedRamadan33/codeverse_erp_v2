<?php

namespace Modules\Accounting\FiscalYears\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\EntryStatus;
use Modules\Accounting\Enums\PeriodStatus;
use Modules\Accounting\Models\FiscalPeriod;
use Modules\Accounting\Models\JournalEntry;
use Modules\Core\Settings\Settings;

/**
 * Closes or reopens a monthly period. Closing refuses while draft entries remain in it,
 * so nothing is left unposted in a closed month.
 */
class SetPeriodStatus
{
    public function __construct(private readonly Settings $settings) {}

    public function handle(User $actor, FiscalPeriod $period, PeriodStatus $status): FiscalPeriod
    {
        Gate::forUser($actor)->authorize('accounting.fiscal_years.manage');

        if ($period->year->status === PeriodStatus::Closed) {
            throw ValidationException::withMessages(['period' => __('accounting::fiscal.year_closed')]);
        }

        if ($status === PeriodStatus::Closed) {
            $drafts = JournalEntry::where('status', EntryStatus::Draft)
                ->whereBetween('date', [$period->start_date->toDateString(), $period->end_date->toDateString()])
                ->exists();

            if ($drafts) {
                throw ValidationException::withMessages(['period' => __('accounting::fiscal.drafts_in_period')]);
            }
        }

        DB::transaction(fn () => $period->update(['status' => $status]));

        return $period;
    }

    /**
     * Entries dated on or before the lock date need accounting.entries.post_before_lock.
     */
    public function setLockDate(User $actor, ?string $date): void
    {
        Gate::forUser($actor)->authorize('accounting.fiscal_years.manage');

        $this->settings->set('accounting.lock_date', $date ?: null);
    }
}
