<?php

namespace Modules\Accounting\Livewire\Vouchers;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Accounting\Mappings\AccountResolver;
use Modules\Accounting\Models\CashVoucher;
use Modules\Accounting\Reconciliation\Reconciler;
use Modules\Accounting\Vouchers\Actions\AllocateVoucher;
use Modules\Accounting\Vouchers\Actions\CancelVoucher;
use Modules\Accounting\Vouchers\Actions\PostVoucher;
use Modules\Accounting\Vouchers\Actions\SaveVoucher;
use Modules\Accounting\Vouchers\VoucherKind;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Documents\DocumentStatus;
use Throwable;

#[Layout('core::layouts.app')]
class Show extends Component
{
    public string $kind;

    public int $voucherId;

    /** @var array<int, string> journal line id => amount */
    public array $allocations = [];

    public bool $showCancel = false;

    public string $cancelReason = '';

    public function mount(string $kind, int $id): void
    {
        Gate::authorize('accounting.vouchers.view');

        $this->kind = VoucherKind::from($kind)->value;
        $voucher = $this->voucher($id);
        abort_unless(auth()->user()->canAccessBranch($voucher->branch_id), 404);

        $this->voucherId = $voucher->id;
    }

    private function voucher(?int $id = null): CashVoucher
    {
        return VoucherKind::from($this->kind)->model()::with(['partner', 'paymentMethod', 'currency', 'branch', 'journalEntry'])
            ->findOrFail($id ?? $this->voucherId);
    }

    /**
     * Allocate the available amount to open items from the oldest.
     */
    public function fillInOrder(): void
    {
        $available = $this->available();
        $this->allocations = [];

        foreach ($this->openItems() as $item) {
            if (! $available->isPositive()) {
                break;
            }

            $amount = $available->isLessThan($item['residual']) ? $available : $item['residual'];
            $this->allocations[$item['line']->id] = (string) $amount;
            $available = $available->minus($amount);
        }
    }

    public function post(PostVoucher $action): void
    {
        $voucher = $action->handle(auth()->user(), $this->voucher(), $this->cleanAllocations());

        $this->allocations = [];
        session()->flash('status', __('accounting::vouchers.posted', ['number' => $voucher->number]));
    }

    public function allocate(AllocateVoucher $action): void
    {
        $action->handle(auth()->user(), $this->voucher(), $this->cleanAllocations());

        $this->allocations = [];
        session()->flash('status', __('core::ui.saved'));
    }

    public function cancel(CancelVoucher $action): void
    {
        $this->validate(['cancelReason' => ['required', 'string', 'max:255']]);

        $action->handle(auth()->user(), $this->voucher(), $this->cancelReason);

        $this->showCancel = false;
        session()->flash('status', __('accounting::vouchers.cancelled'));
    }

    public function delete(SaveVoucher $action): void
    {
        $action->delete(auth()->user(), $this->voucher());

        session()->flash('status', __('core::ui.deleted'));
        $this->redirectRoute(VoucherKind::from($this->kind)->routePrefix().'index');
    }

    /**
     * @return array<int, string>
     */
    private function cleanAllocations(): array
    {
        $this->validate(['allocations.*' => ['nullable', 'decimal:0,4', 'min:0']]);

        return array_filter($this->allocations, fn ($amount) => $amount !== null && $amount !== '' && ! BigDecimal::of($amount)->isZero());
    }

    /**
     * Items the voucher can settle: debit items for a receipt, credit items for a payment.
     *
     * @return \Illuminate\Support\Collection<int, array{line: \Modules\Accounting\Models\JournalLine, residual: BigDecimal}>
     */
    private function openItems()
    {
        $voucher = $this->voucher();
        $kind = $voucher->kind();

        try {
            $account = app(AccountResolver::class)->resolve($kind->partnerMappingKey(), [$voucher->partner, $voucher->branch]);
        } catch (Throwable) {
            return collect();
        }

        $own = $voucher->journal_entry_id ? $voucher->journalEntry->id : null;

        return app(Reconciler::class)
            ->openLines($voucher->partner_id, [$account->id], debitSide: $kind->partnerLineIsCredit())
            ->reject(fn ($item) => $item['line']->journal_entry_id === $own)
            ->values();
    }

    /**
     * Base-currency amount still free to allocate.
     */
    private function available(): BigDecimal
    {
        $voucher = $this->voucher();

        if ($voucher->status === DocumentStatus::Posted) {
            return app(Reconciler::class)->residual($voucher->partnerLine());
        }

        $base = app(Currencies::class)->base();

        return $voucher->amount->multipliedBy($voucher->exchange_rate)->toScale($base->decimal_places, \Brick\Math\RoundingMode::HalfUp);
    }

    public function render()
    {
        $voucher = $this->voucher();
        $canAllocate = $voucher->status !== DocumentStatus::Cancelled;

        return view('accounting::livewire.vouchers.show', [
            'voucher' => $voucher,
            'kindEnum' => $voucher->kind(),
            'openItems' => $canAllocate ? $this->openItems() : collect(),
            'available' => $canAllocate ? $this->available() : BigDecimal::zero(),
        ])->title(($voucher->number ?? __('core::documents.status.draft')).' — '.$voucher->kind()->label());
    }
}
