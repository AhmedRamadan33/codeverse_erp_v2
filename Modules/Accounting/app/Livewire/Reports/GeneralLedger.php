<?php

namespace Modules\Accounting\Livewire\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Accounting\Ledger\Ledger;
use Modules\Accounting\Models\Account;
use Modules\Core\Models\Branch;

#[Layout('core::layouts.app')]
class GeneralLedger extends Component
{
    #[Url]
    public ?int $accountId = null;

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
        $branchId = $this->branchId ?? ($user->can('core.branches.all_access') ? null : $user->branches()->value('branches.id'));

        $account = $this->accountId ? Account::find($this->accountId) : null;
        $statement = $account
            ? $ledger->statement($ledger->leafIds($account), CarbonImmutable::parse($this->from), CarbonImmutable::parse($this->to), $branchId)
            : null;

        return view('accounting::livewire.reports.general-ledger', [
            'account' => $account,
            'statement' => $statement,
            'accounts' => Account::orderBy('code')->get(),
            'branches' => $user->can('core.branches.all_access') ? Branch::orderBy('code')->get() : $user->branches,
        ])->title(__('accounting::menu.general_ledger'));
    }
}
