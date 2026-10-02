<?php

namespace Modules\Accounting\FiscalYears\Actions;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\PeriodStatus;
use Modules\Accounting\Models\FiscalYear;

/**
 * Creates a twelve-month fiscal year split into monthly periods. Years may not overlap.
 */
class CreateFiscalYear
{
    /**
     * @param  User|null  $actor  null only when called by the installer
     */
    public function handle(?User $actor, CarbonImmutable $start): FiscalYear
    {
        if ($actor !== null) {
            Gate::forUser($actor)->authorize('accounting.fiscal_years.manage');
        }

        $start = $start->startOfMonth();
        $end = $start->addYear()->subDay();

        $overlaps = FiscalYear::whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['start_date' => __('accounting::fiscal.overlap')]);
        }

        return DB::transaction(function () use ($start, $end) {
            $year = FiscalYear::create([
                'name' => $start->month === 1 ? $start->format('Y') : $start->format('Y').'/'.$end->format('Y'),
                'start_date' => $start,
                'end_date' => $end,
                'status' => PeriodStatus::Open,
            ]);

            for ($month = $start; $month->lte($end); $month = $month->addMonth()) {
                $year->periods()->create([
                    'start_date' => $month,
                    'end_date' => $month->endOfMonth(),
                    'status' => PeriodStatus::Open,
                ]);
            }

            return $year;
        });
    }
}
