<div>
    <div class="card mb-3 d-print-none">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">{{ __('accounting::entries.fields.partner') }}</label>
                <select wire:model.live="partnerId" class="form-select">
                    <option value="">—</option>
                    @foreach ($partners as $option) <option value="{{ $option->id }}">{{ $option->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('accounting::reports.side') }}</label>
                <select wire:model.live="side" class="form-select">
                    <option value="receivable">{{ __('accounting::accounts.subtypes.receivable') }}</option>
                    <option value="payable">{{ __('accounting::accounts.subtypes.payable') }}</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('accounting::reports.from') }}</label>
                <input type="date" wire:model.live="from" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('accounting::reports.to') }}</label>
                <input type="date" wire:model.live="to" class="form-control">
            </div>
            <div class="col-md-2 text-end">
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> {{ __('accounting::reports.print') }}</button>
            </div>
        </div>
    </div>

    @if ($statement)
        <div class="card mb-3">
            <div class="card-header">
                <strong>{{ $partner->name }}</strong>
                <span class="ms-2 ltr-value text-body-secondary">{{ $from }} → {{ $to }}</span>
            </div>
            <div class="card-body p-0">
                @include('accounting::livewire.reports.partials.statement', ['statement' => $statement, 'showPartner' => false])
            </div>
        </div>

        @foreach (['open_debits' => $openDebits, 'open_credits' => $openCredits] as $title => $items)
            @if ($items->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header"><strong>{{ __('accounting::reports.'.$title) }}</strong></div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>{{ __('accounting::entries.fields.date') }}</th>
                                <th>{{ __('accounting::entries.fields.number') }}</th>
                                <th>{{ __('accounting::entries.fields.description') }}</th>
                                <th class="text-end">{{ __('accounting::vouchers.open_amount') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($items as $item)
                                <tr>
                                    <td class="ltr-value">{{ $item['line']->entry->date->toDateString() }}</td>
                                    <td class="ltr-value"><a href="{{ route('accounting.entries.show', $item['line']->journal_entry_id) }}">{{ $item['line']->entry->number }}</a></td>
                                    <td>{{ $item['line']->description ?? $item['line']->entry->description }}</td>
                                    <td class="text-end ltr-value">@money($item['residual'])</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endforeach
    @endif
</div>
