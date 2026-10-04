<?php

namespace Modules\Accounting\Livewire\Reports;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Ledger\Aging;
use Modules\Accounting\Ledger\AgingRow;
use Modules\Core\Models\Branch;

#[Layout('core::layouts.app')]
class AgedBalances extends Component
{
    /** receivable | payable */
    #[Url]
    public string $kind = 'receivable';

    #[Url]
    public ?int $branchId = null;

    public function mount(): void
    {
        Gate::authorize('accounting.reports.view');

        $this->kind = $this->kind === 'payable' ? 'payable' : 'receivable';
    }

    public function render(Aging $aging)
    {
        $user = auth()->user();
        abort_if($this->branchId && ! $user->canAccessBranch($this->branchId), 403);
        $branchId = $this->branchId ?? ($user->can('core.branches.all_access') ? null : $user->branches()->value('branches.id'));

        $rows = $aging->report(AccountSubtype::from($this->kind), CarbonImmutable::today(), $branchId);
        $columns = ['current', ...array_map(fn ($d) => "d{$d}", Aging::BUCKETS), 'older', 'unallocated'];
        $totals = collect($columns)->mapWithKeys(fn ($c) => [
            $c => $rows->reduce(fn (BigDecimal $sum, AgingRow $r) => $sum->plus($r->amounts[$c]), BigDecimal::zero()),
        ])->all();

        return view('accounting::livewire.reports.aged-balances', [
            'rows' => $rows,
            'columns' => $columns,
            'totals' => $totals,
            'grandTotal' => $rows->reduce(fn (BigDecimal $sum, AgingRow $r) => $sum->plus($r->total()), BigDecimal::zero()),
            'branches' => $user->can('core.branches.all_access') ? Branch::orderBy('code')->get() : $user->branches,
        ])->title(__("accounting::reports.aging.{$this->kind}"));
    }
}
