<div>
    @unless ($active)
        <div class="alert alert-warning">{{ __('egypttax::receipts.inactive') }}</div>
    @endunless
    @error('receipt') <div class="alert alert-danger">{{ $message }}</div> @enderror

    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center">
            <input type="search" wire:model.live.debounce.400ms="search" class="form-control w-auto" placeholder="{{ __('core::ui.search') }}">
            <select wire:model.live="status" class="form-select w-auto">
                <option value="">{{ __('egypttax::receipts.fields.status') }}: {{ __('core::ui.all') }}</option>
                @foreach ($statuses as $s) <option value="{{ $s->value }}">{{ $s->label() }} ({{ $counts[$s->value] ?? 0 }})</option> @endforeach
            </select>
            <select wire:model.live="device" class="form-select w-auto">
                <option value="">{{ __('egypttax::receipts.fields.device') }}: {{ __('core::ui.all') }}</option>
                @foreach ($devices as $d) <option value="{{ $d->id }}">{{ $d->register->code }} — {{ $d->serial }}</option> @endforeach
            </select>
            @can('egypttax.receipts.submit')
                <button type="button" class="btn btn-primary ms-auto" wire:click="sendAll" wire:loading.attr="disabled">
                    <i class="bi bi-send"></i> {{ __('egypttax::receipts.send_all') }}
                </button>
            @endcan
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                <tr>
                    <th>{{ __('egypttax::receipts.fields.receipt') }}</th>
                    <th>{{ __('egypttax::receipts.fields.issued_at') }}</th>
                    <th>{{ __('egypttax::receipts.fields.device') }}</th>
                    <th>{{ __('egypttax::receipts.fields.uuid') }}</th>
                    <th>{{ __('egypttax::receipts.fields.status') }}</th>
                    <th>{{ __('egypttax::receipts.fields.errors') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($receipts as $etaReceipt)
                    <tr wire:key="eta-{{ $etaReceipt->id }}">
                        <td class="ltr-value">
                            @can('pos.receipts.view')
                                <a href="{{ route('pos.receipts.show', $etaReceipt->receipt_id) }}">{{ $etaReceipt->receipt->number }}</a>
                            @else
                                {{ $etaReceipt->receipt->number }}
                            @endcan
                            @if ($etaReceipt->receipt->isReturn())<span class="badge text-bg-warning">{{ $etaReceipt->receipt->kind->label() }}</span>@endif
                        </td>
                        <td class="ltr-value">{{ $etaReceipt->issued_at?->format('Y-m-d H:i') }}</td>
                        <td>{{ $etaReceipt->device->register->code }}</td>
                        <td class="ltr-value small text-break" style="max-width: 14rem">{{ $etaReceipt->uuid }}</td>
                        <td>
                            <span class="badge {{ $etaReceipt->status->badge() }}">{{ $etaReceipt->status->label() }}</span>
                            @if ($etaReceipt->status === \Modules\EgyptTax\Enums\EtaReceiptStatus::Pending && $etaReceipt->attempts > 0)
                                <div class="small text-body-secondary">{{ __('egypttax::receipts.attempts', ['count' => $etaReceipt->attempts]) }}</div>
                            @endif
                        </td>
                        <td class="small">
                            @foreach ($etaReceipt->errors ?? [] as $error)<div>{{ $error }}</div>@endforeach
                        </td>
                        <td class="text-end text-nowrap">
                            @can('egypttax.receipts.submit')
                                @if (in_array($etaReceipt->status, [\Modules\EgyptTax\Enums\EtaReceiptStatus::Unbuilt, \Modules\EgyptTax\Enums\EtaReceiptStatus::Pending], true))
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="retry({{ $etaReceipt->id }})">{{ __('egypttax::receipts.retry') }}</button>
                                @elseif ($etaReceipt->status === \Modules\EgyptTax\Enums\EtaReceiptStatus::Invalid)
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="reissue({{ $etaReceipt->id }})" wire:confirm="{{ __('egypttax::receipts.reissue_confirm') }}">{{ __('egypttax::receipts.reissue') }}</button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($receipts->hasPages())
            <div class="card-footer">{{ $receipts->links() }}</div>
        @endif
    </div>
</div>
