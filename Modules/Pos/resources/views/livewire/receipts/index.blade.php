<div class="card">
    <div class="card-header d-flex flex-wrap gap-2">
        <input type="search" wire:model.live.debounce.400ms="search" class="form-control w-auto" placeholder="{{ __('core::ui.search') }}">
        <select wire:model.live="kind" class="form-select w-auto">
            <option value="">{{ __('pos::receipts.fields.kind') }}: {{ __('core::ui.all') }}</option>
            @foreach ($kinds as $kind) <option value="{{ $kind->value }}">{{ $kind->label() }}</option> @endforeach
        </select>
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
            <tr>
                <th>{{ __('pos::receipts.fields.number') }}</th>
                <th>{{ __('pos::receipts.fields.date') }}</th>
                <th>{{ __('pos::receipts.fields.kind') }}</th>
                <th>{{ __('pos::receipts.fields.customer') }}</th>
                <th>{{ __('pos::receipts.fields.register') }}</th>
                <th>{{ __('pos::receipts.fields.cashier') }}</th>
                <th class="text-end">{{ __('pos::receipts.fields.total') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($receipts as $receipt)
                <tr wire:key="rc-{{ $receipt->id }}">
                    <td class="ltr-value"><a href="{{ route('pos.receipts.show', $receipt->id) }}">{{ $receipt->number }}</a></td>
                    <td class="ltr-value">{{ $receipt->created_at->format('Y-m-d H:i') }}</td>
                    <td>
                        <span @class(['badge', 'text-bg-info' => ! $receipt->isReturn(), 'text-bg-warning' => $receipt->isReturn()])>{{ $receipt->kind->label() }}</span>
                        @if ($receipt->is_credit)<span class="badge text-bg-secondary">{{ __('pos::receipts.credit') }}</span>@endif
                    </td>
                    <td>{{ $receipt->partner->name }}</td>
                    <td>{{ $receipt->register->code }}</td>
                    <td>{{ $receipt->creator?->name }}</td>
                    <td class="text-end ltr-value">@money($receipt->total)</td>
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
