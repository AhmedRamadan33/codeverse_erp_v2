<?php

namespace Modules\Accounting\Livewire\Reports;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Accounting\Ledger\Ledger;
use Modules\Accounting\Ledger\TrialBalanceRow;
use Modules\Core\Models\Branch;

#[Layout('core::layouts.app')]
class TrialBalance extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public ?int $branchId = null;

    public function mount(): void
    {
        Gate::authorize('accounting.reports.view');

        $this->from = $this->from ?: now()->startOfYear()->toDateString();
        $this->to = $this->to ?: now()->toDateString();
    }

    public function render(Ledger $ledger)
    {
        $this->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);
        $user = auth()->user();
        abort_if($this->branchId && ! $user->canAccessBranch($this->branchId), 403);

        // Users limited to some branches only see those branches.
        $branchId = $this->branchId ?? ($user->can('core.branches.all_access') ? null : $user->branches()->value('branches.id'));
        $rows = $ledger->trialBalance(CarbonImmutable::parse($this->from), CarbonImmutable::parse($this->to), $branchId);

        $sum = fn (callable $pick) => $rows->reduce(fn (BigDecimal $c, TrialBalanceRow $r) => $c->plus($pick($r)), BigDecimal::zero());

        return view('accounting::livewire.reports.trial-balance', [
            'rows' => $rows,
            'totals' => [
                'opening_debit' => $sum(fn ($r) => $r->opening->isPositive() ? $r->opening : 0),
                'opening_credit' => $sum(fn ($r) => $r->opening->isNegative() ? $r->opening->negated() : 0),
                'debit' => $sum(fn ($r) => $r->debit),
                'credit' => $sum(fn ($r) => $r->credit),
                'closing_debit' => $sum(fn ($r) => $r->closing->isPositive() ? $r->closing : 0),
                'closing_credit' => $sum(fn ($r) => $r->closing->isNegative() ? $r->closing->negated() : 0),
            ],
            'branches' => $user->can('core.branches.all_access') ? Branch::orderBy('code')->get() : $user->branches,
        ])->title(__('accounting::menu.trial_balance'));
    }
}
