<div class="card">
    <div class="card-header d-flex flex-wrap gap-2">
        <select wire:model.live="userId" class="form-select w-auto">
            <option value="">{{ __('core::ui.user') }}: {{ __('core::ui.all') }}</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}">{{ $user->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="type" class="form-select w-auto">
            <option value="">{{ __('core::audit.record') }}: {{ __('core::ui.all') }}</option>
            @foreach ($types as $type)
                <option value="{{ $type }}">{{ class_basename($type) }}</option>
            @endforeach
        </select>
        <input type="date" wire:model.live="from" class="form-control w-auto">
        <input type="date" wire:model.live="to" class="form-control w-auto">
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead>
            <tr>
                <th>{{ __('core::ui.date') }}</th>
                <th>{{ __('core::ui.user') }}</th>
                <th>{{ __('core::audit.event') }}</th>
                <th>{{ __('core::audit.record') }}</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($logs as $log)
                <tr wire:key="log-{{ $log->id }}">
                    <td class="ltr-value">{{ $log->created_at }}</td>
                    <td>{{ $log->user?->name ?? __('core::audit.system') }}</td>
                    <td>{{ __('core::audit.events.'.$log->event) }}</td>
                    <td class="ltr-value">{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-link" wire:click="toggle({{ $log->id }})"><i class="bi bi-chevron-expand"></i></button>
                    </td>
                </tr>
                @if ($expanded === $log->id)
                    <tr wire:key="log-detail-{{ $log->id }}">
                        <td colspan="5">
                            <table class="table table-sm table-bordered mb-0">
                                <thead><tr><th>{{ __('core::audit.field') }}</th><th>{{ __('core::audit.old') }}</th><th>{{ __('core::audit.new') }}</th></tr></thead>
                                <tbody>
                                @foreach (array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))) as $field)
                                    <tr>
                                        <td class="ltr-value">{{ $field }}</td>
                                        <td>{{ json_encode($log->old_values[$field] ?? null, JSON_UNESCAPED_UNICODE) }}</td>
                                        <td>{{ json_encode($log->new_values[$field] ?? null, JSON_UNESCAPED_UNICODE) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('core::ui.no_records') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($logs->hasPages())
        <div class="card-footer">{{ $logs->links() }}</div>
    @endif
</div>
