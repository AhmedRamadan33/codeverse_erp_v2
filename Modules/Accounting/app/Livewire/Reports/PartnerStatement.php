<?php

namespace Modules\Accounting\Livewire\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Ledger\Ledger;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Reconciliation\Reconciler;
use Modules\Core\Models\Partner;

/**
 * Everything a customer or supplier did with us in a period, with running balance,
 * plus the items still open (unpaid invoices, unallocated payments).
 */
#[Layout('core::layouts.app')]
class PartnerStatement extends Component
{
    #[Url]
    public ?int $partnerId = null;

    /** receivable or payable */
    #[Url]
    public string $side = 'receivable';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        Gate::authorize('accounting.reports.view');

        $this->from = $this->from ?: now()->startOfYear()->toDateString();
        $this->to = $this->to ?: now()->toDateString();
    }

    public function render(Ledger $ledger, Reconciler $reconciler)
    {
        $this->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'side' => ['required', 'in:receivable,payable'],
        ]);

        $user = auth()->user();
        $partner = $this->partnerId ? Partner::visibleTo($user)->find($this->partnerId) : null;
        $accountIds = Account::where('subtype', AccountSubtype::from($this->side))->pluck('id')->all();

        $statement = $partner
            ? $ledger->statement($accountIds, CarbonImmutable::parse($this->from), CarbonImmutable::parse($this->to), null, $partner->id)
            : null;

        return view('accounting::livewire.reports.partner-statement', [
            'partner' => $partner,
            'statement' => $statement,
            'openDebits' => $partner ? $reconciler->openLines($partner->id, $accountIds, debitSide: true) : collect(),
            'openCredits' => $partner ? $reconciler->openLines($partner->id, $accountIds, debitSide: false) : collect(),
            'partners' => Partner::visibleTo($user)->orderBy('name')->get(['id', 'name']),
        ])->title(__('accounting::menu.partner_statement'));
    }
}
