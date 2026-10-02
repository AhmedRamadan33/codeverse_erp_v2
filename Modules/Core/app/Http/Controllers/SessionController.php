<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Modules\Core\Http\Middleware\SetLocale;

class SessionController
{
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Signed-in users keep the choice on their profile; guests keep it in the session.
     */
    public function updateLocale(Request $request): RedirectResponse
    {
        $locale = $request->validate(['locale' => ['required', Rule::in(SetLocale::SUPPORTED)]])['locale'];

        $request->user()?->update(['locale' => $locale]);
        $request->session()->put('locale', $locale);

        return back();
    }
}
