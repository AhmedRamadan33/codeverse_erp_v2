<form wire:submit="save" class="card">
    <div class="card-body row g-3">
        @error('form.roles') <div class="col-12"><div class="alert alert-danger mb-0">{{ $message }}</div></div> @enderror
        <div class="col-md-4">
            <label class="form-label">{{ __('core::users.fields.name') }}</label>
            <input type="text" wire:model="form.name" class="form-control @error('form.name') is-invalid @enderror">
            @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('core::ui.email') }}</label>
            <input type="email" wire:model="form.email" class="form-control ltr-value @error('form.email') is-invalid @enderror" autocomplete="off">
            @error('form.email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('core::ui.phone') }}</label>
            <input type="text" wire:model="form.phone" class="form-control ltr-value">
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('core::users.fields.password') }}</label>
            <input type="password" wire:model="form.password" class="form-control @error('form.password') is-invalid @enderror" autocomplete="new-password">
            @error('form.password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            @if ($user) <div class="form-text">{{ __('core::users.fields.password_hint') }}</div> @endif
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('core::users.fields.locale') }}</label>
            <select wire:model="form.locale" class="form-select">
                <option value="">{{ __('core::users.installation_default') }}</option>
                <option value="ar">العربية</option>
                <option value="en">English</option>
            </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <div class="form-check form-switch">
                <input id="user_active" type="checkbox" wire:model="form.is_active" class="form-check-input @error('form.is_active') is-invalid @enderror">
                <label for="user_active" class="form-check-label">{{ __('core::ui.active') }}</label>
                @error('form.is_active') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label">{{ __('core::users.fields.roles') }}</label>
            @foreach ($roles as $role)
                <div class="form-check">
                    <input id="role-{{ $loop->index }}" type="checkbox" value="{{ $role }}" wire:model="form.roles" class="form-check-input">
                    <label for="role-{{ $loop->index }}" class="form-check-label">{{ $role }}</label>
                </div>
            @endforeach
        </div>
        <div class="col-md-6">
            <label class="form-label">{{ __('core::users.fields.branches') }}</label>
            @foreach ($branches as $branch)
                <div class="d-flex gap-3 align-items-center">
                    <div class="form-check">
                        <input id="branch-{{ $branch->id }}" type="checkbox" value="{{ $branch->id }}" wire:model.live="form.branches" class="form-check-input">
                        <label for="branch-{{ $branch->id }}" class="form-check-label">{{ $branch->name }}</label>
                    </div>
                    @if (in_array((string) $branch->id, array_map('strval', $form['branches']), true))
                        <div class="form-check">
                            <input id="default-{{ $branch->id }}" type="radio" value="{{ $branch->id }}" wire:model="form.default_branch_id" class="form-check-input">
                            <label for="default-{{ $branch->id }}" class="form-check-label small">{{ __('core::users.fields.default_branch') }}</label>
                        </div>
                    @endif
                </div>
            @endforeach
            @error('form.default_branch_id') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ __('core::ui.save') }}</button>
        <a href="{{ route('core.users.index') }}" class="btn btn-outline-secondary">{{ __('core::ui.cancel') }}</a>
    </div>
</form>
