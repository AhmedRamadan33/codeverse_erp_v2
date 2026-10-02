<form wire:submit="save" class="card">
    <div class="card-body">
        @error('form.lines') <div class="alert alert-danger">{{ $message }}</div> @enderror
        @error('entry') <div class="alert alert-danger">{{ $message }}</div> @enderror
        <div class="row g-3 mb-3">
            <div class="col-md-2">
                <label class="form-label">{{ __('accounting::entries.fields.date') }}</label>
                <input type="date" wire:model="form.date" class="form-control @error('form.date') is-invalid @enderror">
                @error('form.date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('accounting::entries.fields.branch') }}</label>
                <select wire:model="form.branch_id" class="form-select @error('form.branch_id') is-invalid @enderror">
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
                @error('form.branch_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('accounting::entries.fields.journal_type') }}</label>
                <select wire:model="form.journal_type" class="form-select">
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">{{ __('accounting::entries.fields.description') }}</label>
                <input type="text" wire:model="form.description" class="form-control">
            </div>
        </div>

        <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
            <tr>
                <th style="width: 30%">{{ __('accounting::entries.fields.account') }}</th>
                <th style="width: 20%">{{ __('accounting::entries.fields.partner') }}</th>
                <th>{{ __('accounting::entries.fields.description') }}</th>
                <th style="width: 12%">{{ __('accounting::entries.fields.debit') }}</th>
                <th style="width: 12%">{{ __('accounting::entries.fields.credit') }}</th>
                <th style="width: 3rem"></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($form['lines'] as $i => $line)
                <tr wire:key="line-{{ $i }}">
                    <td>
                        <select wire:model="form.lines.{{ $i }}.account_id" class="form-select form-select-sm @error('form.lines.'.$i.'.account_id') is-invalid @enderror">
                            <option value="">—</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->label() }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <select wire:model="form.lines.{{ $i }}.partner_id" class="form-select form-select-sm">
                            <option value="">—</option>
                            @foreach ($partners as $partner)
                                <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td><input type="text" wire:model="form.lines.{{ $i }}.description" class="form-control form-control-sm"></td>
                    <td><input type="text" inputmode="decimal" wire:model.live.debounce.400ms="form.lines.{{ $i }}.debit" class="form-control form-control-sm ltr-value @error('form.lines.'.$i.'.debit') is-invalid @enderror"></td>
                    <td><input type="text" inputmode="decimal" wire:model.live.debounce.400ms="form.lines.{{ $i }}.credit" class="form-control form-control-sm ltr-value @error('form.lines.'.$i.'.credit') is-invalid @enderror"></td>
                    <td class="text-center">
                        @if (count($form['lines']) > 2)
                            <button type="button" class="btn btn-sm btn-link text-danger" wire:click="removeLine({{ $i }})"><i class="bi bi-x-lg"></i></button>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr>
                <td colspan="3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addLine"><i class="bi bi-plus"></i> {{ __('accounting::entries.add_line') }}</button>
                </td>
                <th class="ltr-value">@money($totals['debit'])</th>
                <th class="ltr-value">@money($totals['credit'])</th>
                <td></td>
            </tr>
            @unless ($totals['debit']->isEqualTo($totals['credit']))
                <tr>
                    <td colspan="3" class="text-end text-danger">{{ __('accounting::entries.fields.difference') }}</td>
                    <td colspan="2" class="text-danger ltr-value">@money($totals['debit']->minus($totals['credit'])->abs())</td>
                    <td></td>
                </tr>
            @endunless
            </tfoot>
        </table>
    </div>
    <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ __('accounting::entries.save_draft') }}</button>
        <a href="{{ $entry ? route('accounting.entries.show', $entry->id) : route('accounting.entries.index') }}" class="btn btn-outline-secondary">{{ __('core::ui.cancel') }}</a>
    </div>
</form>
