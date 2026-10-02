<div class="card">
    <div class="card-body login-card-body">
        <p class="login-box-msg">{{ __('core::auth.login_intro') }}</p>
        <form wire:submit="login">
            <div class="mb-3">
                <label for="email" class="form-label">{{ __('core::auth.email') }}</label>
                <input id="email" type="email" wire:model="email" class="form-control ltr-value @error('email') is-invalid @enderror" autocomplete="username" autofocus>
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">{{ __('core::auth.password') }}</label>
                <input id="password" type="password" wire:model="password" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="form-check mb-3">
                <input id="remember" type="checkbox" wire:model="remember" class="form-check-input">
                <label for="remember" class="form-check-label">{{ __('core::auth.remember') }}</label>
            </div>
            <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled">{{ __('core::auth.login') }}</button>
        </form>
    </div>
</div>
