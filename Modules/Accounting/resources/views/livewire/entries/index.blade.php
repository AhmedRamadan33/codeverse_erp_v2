<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center">
        <input type="search" wire:model.live.debounce.400ms="search" class="form-control w-auto" placeholder="{{ __('core::ui.search') }}">
        <select wire:model.live="type" class="form-select w-auto">
            <option value="">{{ __('accounting::entries.fields.journal_type') }}: {{ __('core::ui.all') }}</option>
            @foreach ($types as $type)
                <option value="{{ $type->value }}">{{ $type->label() }}</option>
            @endforeach
        </select>
        <select wire:model.live="status" class="form-select w-auto">
            <option value="">{{ __('accounting::entries.fields.status') }}: {{ __('core::ui.all') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}">{{ $status->label() }}</option>
            @endforeach
        </select>
        <input type="date" wire:model.live="from" class="form-control w-auto">
        <input type="date" wire:model.live="to" class="form-control w-auto">
        @can('accounting.entries.create')
            <a href="{{ route('accounting.entries.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> {{ __('accounting::entries.new') }}</a>
        @endcan
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
            <tr>
                <th>{{ __('accounting::entries.fields.number') }}</th>
                <th>{{ __('accounting::entries.fields.date') }}</th>
                <th>{{ __('accounting::entries.fields.journal_type') }}</th>
                <th>{{ __('accounting::entries.fields.description') }}</th>
                <th>{{ __('accounting::entries.fields.branch') }}</th>
                <th class="text-end">{{ __('accounting::entries.fields.total') }}</th>
                <th>{{ __('accounting::entries.fields.status') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($entries as $entry)
                <tr wire:key="entry-{{ $entry->id }}">
                    <td class="ltr-value"><a href="{{ route('accounting.entries.show', $entry->id) }}">{{ $entry->number ?? __('accounting::entries.draft').' #'.$entry->id }}</a></td>
                    <td class="ltr-value">{{ $entry->date->toDateString() }}</td>
                    <td>{{ $entry->journal_type->label() }}</td>
                    <td>{{ $entry->description }}</td>
                    <td>{{ $entry->branch->name }}</td>
                    <td class="text-end ltr-value">@money($entry->total ?? 0)</td>
                    <td>
                        <span @class(['badge', 'text-bg-success' => $entry->isPosted(), 'text-bg-warning' => ! $entry->isPosted()])>{{ $entry->status->label() }}</span>
                        @if ($entry->reversed_by_id) <span class="badge text-bg-secondary">{{ __('accounting::entries.reverse') }}</span> @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($entries->hasPages())
        <div class="card-footer">{{ $entries->links() }}</div>
    @endif
</div>
