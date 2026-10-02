<div>
    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-2">
            <div class="col-md-2"><div class="text-body-secondary small">{{ __('accounting::entries.fields.number') }}</div><div class="ltr-value">{{ $entry->number ?? '—' }}</div></div>
            <div class="col-md-2"><div class="text-body-secondary small">{{ __('accounting::entries.fields.date') }}</div><div class="ltr-value">{{ $entry->date->toDateString() }}</div></div>
            <div class="col-md-2"><div class="text-body-secondary small">{{ __('accounting::entries.fields.journal_type') }}</div><div>{{ $entry->journal_type->label() }}</div></div>
            <div class="col-md-2"><div class="text-body-secondary small">{{ __('accounting::entries.fields.branch') }}</div><div>{{ $entry->branch->name }}</div></div>
            <div class="col-md-2"><div class="text-body-secondary small">{{ __('accounting::entries.fields.status') }}</div>
                <span @class(['badge', 'text-bg-success' => $entry->isPosted(), 'text-bg-warning' => ! $entry->isPosted()])>{{ $entry->status->label() }}</span>
            </div>
            <div class="col-md-2"><div class="text-body-secondary small">{{ __('accounting::entries.fields.source') }}</div><div>{{ $entry->source_type ? class_basename($entry->source_type).' #'.$entry->source_id : __('accounting::entries.manual') }}</div></div>
            <div class="col-12"><div class="text-body-secondary small">{{ __('accounting::entries.fields.description') }}</div><div>{{ $entry->description }}</div></div>
            @if ($entry->reversalOf)
                <div class="col-12"><a href="{{ route('accounting.entries.show', $entry->reversal_of_id) }}">{{ __('accounting::entries.reversal_of', ['number' => $entry->reversalOf->number]) }}</a></div>
            @endif
            @if ($entry->reversedBy)
                <div class="col-12"><a href="{{ route('accounting.entries.show', $entry->reversed_by_id) }}">{{ __('accounting::entries.reversed_by', ['number' => $entry->reversedBy->number]) }}</a></div>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                <tr>
                    <th>{{ __('accounting::entries.fields.account') }}</th>
                    <th>{{ __('accounting::entries.fields.partner') }}</th>
                    <th>{{ __('accounting::entries.fields.description') }}</th>
                    <th class="text-end">{{ __('accounting::entries.fields.debit') }}</th>
                    <th class="text-end">{{ __('accounting::entries.fields.credit') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($entry->lines as $line)
                    <tr>
                        <td>{{ $line->account->label() }}</td>
                        <td>{{ $line->partner?->name }}</td>
                        <td>{{ $line->description }}</td>
                        <td class="text-end ltr-value">{{ $line->debit->isZero() ? '' : \Modules\Core\Support\Money::format($line->debit) }}</td>
                        <td class="text-end ltr-value">{{ $line->credit->isZero() ? '' : \Modules\Core\Support\Money::format($line->credit) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="table-light">
                <tr>
                    <th colspan="3">{{ __('accounting::entries.fields.total') }}</th>
                    <th class="text-end ltr-value">@money($totalDebit)</th>
                    <th class="text-end ltr-value">@money($totalCredit)</th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="d-flex gap-2">
        @if (! $entry->isPosted() && ! $entry->source_type)
            @can('accounting.entries.post')
                <button type="button" class="btn btn-success" wire:click="post">{{ __('accounting::entries.post') }}</button>
            @endcan
            @can('accounting.entries.create')
                <a href="{{ route('accounting.entries.edit', $entry->id) }}" class="btn btn-outline-primary">{{ __('core::ui.edit') }}</a>
                <button type="button" class="btn btn-outline-danger" wire:click="delete" wire:confirm="{{ __('core::ui.confirm_delete') }}">{{ __('core::ui.delete') }}</button>
            @endcan
        @endif
        @if ($entry->isPosted() && ! $entry->source_type && ! $entry->reversed_by_id && ! $entry->reversal_of_id)
            @can('accounting.entries.reverse')
                <button type="button" class="btn btn-outline-danger" wire:click="$toggle('showReverse')">{{ __('accounting::entries.reverse') }}</button>
            @endcan
        @endif
        <a href="{{ route('accounting.entries.index') }}" class="btn btn-outline-secondary ms-auto">{{ __('core::ui.cancel') }}</a>
    </div>

    @if ($showReverse)
        <form wire:submit="reverse" class="card mt-3">
            <div class="card-body row g-3">
                <div class="col-md-3">
                    <label class="form-label">{{ __('accounting::entries.fields.reversal_date') }}</label>
                    <input type="date" wire:model="reverseDate" class="form-control @error('reverseDate') is-invalid @enderror">
                </div>
                <div class="col-md-9">
                    <label class="form-label">{{ __('accounting::entries.fields.reason') }}</label>
                    <input type="text" wire:model="reverseReason" class="form-control @error('reverseReason') is-invalid @enderror">
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-danger">{{ __('accounting::entries.reverse') }}</button>
            </div>
        </form>
    @endif
</div>
