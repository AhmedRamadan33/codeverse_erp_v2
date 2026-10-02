<?php

namespace Modules\Core\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('core::layouts.guest')]
class Login extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();

        $throttleKey = Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('core::auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        $credentials = ['email' => $this->email, 'password' => $this->password, 'is_active' => true];

        if (! Auth::attempt($credentials, $this->remember)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['email' => __('core::auth.failed')]);
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        $this->redirectIntended(route('core.dashboard'));
    }

    public function render()
    {
        return view('core::livewire.auth.login')->title(__('core::auth.login'));
    }
}
