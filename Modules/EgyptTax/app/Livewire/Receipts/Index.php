<?php

namespace Modules\EgyptTax\Livewire\Receipts;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\EgyptTax\Actions\EtaReceiptActions;
use Modules\EgyptTax\Enums\EtaReceiptStatus;
use Modules\EgyptTax\Models\EtaDevice;
use Modules\EgyptTax\Models\EtaReceipt;
use Modules\EgyptTax\Models\EtaSetting;

/**
 * The E-Receipts log: status, ETA errors, and retry / reissue.
 */
#[Layout('core::layouts.app')]
class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public ?int $device = null;

    public function mount(): void
    {
        Gate::authorize('egypttax.receipts.view');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'status', 'device'], true)) {
            $this->resetPage();
        }
    }

    public function retry(int $id, EtaReceiptActions $actions): void
    {
        $receipt = $actions->retry(auth()->user(), EtaReceipt::findOrFail($id));
        session()->flash('status', __('egypttax::receipts.now', ['status' => $receipt->status->label()]));
    }

    public function reissue(int $id, EtaReceiptActions $actions): void
    {
        $receipt = $actions->reissue(auth()->user(), EtaReceipt::findOrFail($id));
        session()->flash('status', __('egypttax::receipts.now', ['status' => $receipt->status->label()]));
    }

    public function sendAll(EtaReceiptActions $actions): void
    {
        $result = $actions->sendAll(auth()->user());
        session()->flash('status', __('egypttax::receipts.sent_all', $result));
    }

    public function render()
    {
        $user = auth()->user();

        $receipts = EtaReceipt::with(['receipt', 'device.register'])
            ->when(! $user->can('core.branches.all_access'), fn ($q) => $q->whereHas('receipt', fn ($r) => $r->whereIn('branch_id', $user->branches()->select('branches.id'))))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('uuid', 'like', "{$this->search}%")
                ->orWhereHas('receipt', fn ($r) => $r->where('number', 'like', "%{$this->search}%"))))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->device, fn ($q) => $q->where('device_id', $this->device))
            ->orderByDesc('id')
            ->paginate(30);

        return view('egypttax::livewire.receipts.index', [
            'receipts' => $receipts,
            'statuses' => EtaReceiptStatus::cases(),
            'devices' => EtaDevice::with('register')->get(),
            'active' => EtaSetting::current()->isUsable(),
            'counts' => EtaReceipt::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ])->title(__('egypttax::receipts.title'));
    }
}
