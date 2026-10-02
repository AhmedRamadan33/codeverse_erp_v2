<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center">
        <input type="search" wire:model.live.debounce.400ms="search" class="form-control w-auto" placeholder="{{ __('core::ui.search') }}">
        <select wire:model.live="status" class="form-select w-auto">
            <option value="">{{ __('accounting::vouchers.fields.status') }}: {{ __('core::ui.all') }}</option>
            @foreach ($statuses as $status) <option value="{{ $status->value }}">{{ $status->label() }}</option> @endforeach
        </select>
        @can('accounting.vouchers.create')
            <a href="{{ route('accounting.expenses.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> {{ __('accounting::expenses.new') }}</a>
        @endcan
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
            <tr>
                <th>{{ __('accounting::vouchers.fields.number') }}</th>
                <th>{{ __('accounting::vouchers.fields.date') }}</th>
                <th>{{ __('accounting::vouchers.fields.description') }}</th>
                <th>{{ __('accounting::vouchers.fields.payment_method') }}</th>
                <th class="text-end">{{ __('accounting::expenses.fields.total') }}</th>
                <th>{{ __('accounting::vouchers.fields.status') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($vouchers as $voucher)
                <tr wire:key="ev-{{ $voucher->id }}">
                    <td class="ltr-value"><a href="{{ route('accounting.expenses.show', $voucher->id) }}">{{ $voucher->number ?? __('core::documents.status.draft').' #'.$voucher->id }}</a></td>
                    <td class="ltr-value">{{ $voucher->date->toDateString() }}</td>
                    <td>{{ $voucher->description }}</td>
                    <td>{{ $voucher->paymentMethod->name }}</td>
                    <td class="text-end ltr-value">@money($voucher->total, $voucher->currency->decimal_places) {{ $voucher->currency->code }}</td>
                    <td><span class="badge {{ $voucher->status->badge() }}">{{ $voucher->status->label() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($vouchers->hasPages())
        <div class="card-footer">{{ $vouchers->links() }}</div>
    @endif
</div>
