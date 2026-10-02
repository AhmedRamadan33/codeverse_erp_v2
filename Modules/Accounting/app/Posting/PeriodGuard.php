<?php

namespace Modules\Accounting\Posting;

use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Modules\Accounting\Enums\PeriodStatus;
use Modules\Accounting\Exceptions\PostingException;
use Modules\Accounting\Models\FiscalPeriod;
use Modules\Core\Settings\Settings;

/**
 * Entries may be dated only inside an open fiscal period of an open year, and after the
 * lock date unless the posting user may post adjusting entries.
 */
class PeriodGuard
{
    public function __construct(private readonly Settings $settings) {}

    public function assertOpen(DateTimeInterface $date, ?User $postedBy): void
    {
        $period = FiscalPeriod::with('year')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        if ($period === null) {
            throw PostingException::because('no_fiscal_period', ['date' => $date->format('Y-m-d')], 'date');
        }

        if ($period->status === PeriodStatus::Closed || $period->year->status === PeriodStatus::Closed) {
            throw PostingException::because('period_closed', ['date' => $date->format('Y-m-d')], 'date');
        }

        $lockDate = $this->settings->get('accounting.lock_date');

        if ($lockDate !== null
            && CarbonImmutable::parse($date)->lte(CarbonImmutable::parse($lockDate))
            && ! $postedBy?->can('accounting.entries.post_before_lock')) {
            throw PostingException::because('before_lock_date', ['date' => $lockDate], 'date');
        }
    }
}
