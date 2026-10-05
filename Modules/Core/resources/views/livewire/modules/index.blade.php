<div class="card">
    <div class="card-body p-0">
        @error('module') <div class="alert alert-danger m-3">{{ $message }}</div> @enderror
        <table class="table mb-0">
            <thead>
            <tr>
                <th>{{ __('core::modules.fields.name') }}</th>
                <th>{{ __('core::modules.fields.requires') }}</th>
                <th>{{ __('core::modules.fields.version') }}</th>
                <th>{{ __('core::ui.status') }}</th>
                <th class="text-end">{{ __('core::ui.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($modules as $module)
                <tr wire:key="module-{{ $module['name'] }}">
                    <td>
                        <div class="fw-semibold">{{ $module['label'] }}</div>
                        <div class="small text-body-secondary">{{ $module['description'] }}</div>
                    </td>
                    <td>{{ implode('، ', $module['requires']) ?: '—' }}</td>
                    <td class="ltr-value">
                        {{ $module['version'] }}
                        @if ($module['installed_version'] && version_compare($module['version'], $module['installed_version'], '>'))
                            <span class="badge text-bg-warning">{{ __('core::modules.upgrade_pending', ['version' => $module['installed_version']]) }}</span>
                        @endif
                    </td>
                    <td>
                        <span @class(['badge', 'text-bg-success' => $module['enabled'], 'text-bg-secondary' => ! $module['enabled']])>
                            {{ $module['enabled'] ? __('core::modules.status.enabled') : ($module['installed_version'] ? __('core::modules.status.disabled') : __('core::modules.status.not_installed')) }}
                        </span>
                    </td>
                    <td class="text-end">
                        @if ($module['locked'])
                            <span class="text-body-secondary small">{{ __('core::modules.always_enabled') }}</span>
                        @elseif ($module['enabled'])
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="disable('{{ $module['name'] }}')" wire:confirm="{{ __('core::modules.confirm_disable') }}">{{ __('core::modules.disable') }}</button>
                        @elseif (! $module['available'])
                            <span class="badge text-bg-light">{{ __('core::modules.experimental_badge') }}</span>
                        @else
                            <button type="button" class="btn btn-sm btn-primary" wire:click="enable('{{ $module['name'] }}')" wire:loading.attr="disabled">{{ __('core::modules.enable') }}</button>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
