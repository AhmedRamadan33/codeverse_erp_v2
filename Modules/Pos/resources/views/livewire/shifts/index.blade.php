<div class="card">
    <div class="card-header d-flex flex-wrap gap-2">
        <select wire:model.live="status" class="form-select w-auto">
            <option value="">{{ __('pos::shifts.fields.status') }}: {{ __('core::ui.all') }}</option>
            @foreach ($statuses as $status) <option value="{{ $status->value }}">{{ $status->label() }}</option> @endforeach
        </select>
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
            <tr>
                <th>{{ __('pos::shifts.fields.number') }}</th>
                <th>{{ __('pos::shifts.fields.register') }}</th>
                <th>{{ __('pos::shifts.fields.cashier') }}</th>
                <th>{{ __('pos::shifts.fields.opened_at') }}</th>
                <th>{{ __('pos::shifts.fields.closed_at') }}</th>
                <th class="text-end">{{ __('pos::shifts.fields.cash_difference') }}</th>
                <th>{{ __('pos::shifts.fields.status') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($shifts as $shift)
                <tr wire:key="sh-{{ $shift->id }}">
                    <td class="ltr-value"><a href="{{ route('pos.shifts.show', $shift->id) }}">{{ $shift->number }}</a></td>
                    <td>{{ $shift->register->code }}</td>
                    <td>{{ $shift->cashier->name }}</td>
                    <td class="ltr-value">{{ $shift->opened_at->format('Y-m-d H:i') }}</td>
                    <td class="ltr-value">{{ $shift->closed_at?->format('Y-m-d H:i') }}</td>
                    <td @class(['text-end', 'ltr-value', 'text-danger' => $shift->cash_difference?->isNegative()])>@money($shift->cash_difference)</td>
                    <td><span class="badge {{ $shift->status->badge() }}">{{ $shift->status->label() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($shifts->hasPages())
        <div class="card-footer">{{ $shifts->links() }}</div>
    @endif
</div>
