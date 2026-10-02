<div>
    @if ($showForm)
        <form wire:submit="save" class="card mb-3">
            <div class="card-header">{{ \Modules\Core\Livewire\Sequences\Index::label($form['key']) }}</div>
            <div class="card-body row g-3">
                @if ($editingId === null)
                    <div class="col-md-3">
                        <label class="form-label">{{ __('core::sequences.fields.branch') }}</label>
                        <select wire:model="form.branch_id" class="form-select @error('form.branch_id') is-invalid @enderror">
                            <option value="">—</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('form.branch_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @endif
                <div class="col-md-4">
                    <label class="form-label">{{ __('core::sequences.fields.prefix') }}</label>
                    <input type="text" wire:model="form.prefix" class="form-control ltr-value @error('form.prefix') is-invalid @enderror">
                    <div class="form-text">{{ __('core::sequences.fields.prefix_hint') }}</div>
                    @error('form.prefix') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('core::sequences.fields.padding') }}</label>
                    <input type="number" min="1" max="12" wire:model="form.padding" class="form-control @error('form.padding') is-invalid @enderror">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('core::sequences.fields.reset') }}</label>
                    <select wire:model="form.reset" class="form-select">
                        @foreach ($resets as $reset)
                            <option value="{{ $reset->value }}">{{ $reset->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ __('core::ui.save') }}</button>
                <button type="button" class="btn btn-outline-secondary" wire:click="$set('showForm', false)">{{ __('core::ui.cancel') }}</button>
            </div>
        </form>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                <tr>
                    <th>{{ __('core::sequences.fields.document') }}</th>
                    <th>{{ __('core::sequences.fields.branch') }}</th>
                    <th>{{ __('core::sequences.fields.example') }}</th>
                    <th>{{ __('core::sequences.fields.reset') }}</th>
                    <th class="text-end">{{ __('core::ui.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($sequences as $sequence)
                    <tr wire:key="sequence-{{ $sequence->id }}">
                        <td>{{ \Modules\Core\Livewire\Sequences\Index::label($sequence->key) }}</td>
                        <td>{{ $sequence->branch?->name ?? __('core::sequences.all_branches') }}</td>
                        <td class="ltr-value">
                            {{ strtr($sequence->prefix, ['{branch}' => $sequence->branch?->code ?? 'MAIN', '{yyyy}' => now()->format('Y'), '{yy}' => now()->format('y'), '{mm}' => now()->format('m')]) . str_pad('1', $sequence->padding, '0', STR_PAD_LEFT) }}
                        </td>
                        <td>{{ $sequence->reset->label() }}</td>
                        <td class="text-end text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $sequence->id }})">{{ __('core::ui.edit') }}</button>
                            @if ($sequence->branch_id === null)
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addBranchOverride({{ $sequence->id }})">{{ __('core::sequences.add_branch_override') }}</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('core::sequences.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
