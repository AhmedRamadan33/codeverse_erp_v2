<?php

namespace Modules\Accounting\Livewire\FiscalYears;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Accounting\Enums\PeriodStatus;
use Modules\Accounting\FiscalYears\Actions\CloseFiscalYear;
use Modules\Accounting\FiscalYears\Actions\CreateFiscalYear;
use Modules\Accounting\FiscalYears\Actions\SetPeriodStatus;
use Modules\Accounting\Models\FiscalPeriod;
use Modules\Accounting\Models\FiscalYear;
use Modules\Core\Settings\Settings;

#[Layout('core::layouts.app')]
class Index extends Component
{
    public ?string $lockDate = null;

    public function mount(Settings $settings): void
    {
        Gate::authorize('accounting.fiscal_years.manage');

        $this->lockDate = $settings->get('accounting.lock_date');
    }

    public function createNext(CreateFiscalYear $action): void
    {
        $last = FiscalYear::orderByDesc('end_date')->first();
        $start = $last ? $last->end_date->addDay() : now()->toImmutable()->startOfYear();

        $action->handle(auth()->user(), $start);
        session()->flash('status', __('core::ui.saved'));
    }

    public function toggle(int $periodId, SetPeriodStatus $action): void
    {
        $period = FiscalPeriod::with('year')->findOrFail($periodId);
        $status = $period->status === PeriodStatus::Open ? PeriodStatus::Closed : PeriodStatus::Open;

        $action->handle(auth()->user(), $period, $status);
    }

    public function closeYear(int $yearId, CloseFiscalYear $action): void
    {
        $action->handle(auth()->user(), FiscalYear::findOrFail($yearId));
        session()->flash('status', __('core::ui.saved'));
    }

    public function saveLockDate(SetPeriodStatus $action): void
    {
        $this->validate(['lockDate' => ['nullable', 'date']]);

        $action->setLockDate(auth()->user(), $this->lockDate);
        session()->flash('status', __('core::ui.saved'));
    }

    public function render()
    {
        return view('accounting::livewire.fiscal-years.index', [
            'years' => FiscalYear::with('periods')->orderByDesc('start_date')->get(),
        ])->title(__('accounting::menu.fiscal_years'));
    }
}
