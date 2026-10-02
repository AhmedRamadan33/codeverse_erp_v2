<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Settings\Settings;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Web: the user's preference, then the session choice (guests), then the installation default.
 * API: the Accept-Language header, then the user's preference, then the installation default.
 */
class SetLocale
{
    public const SUPPORTED = ['ar', 'en'];

    public function __construct(private readonly Settings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $candidates = $request->is('api/*')
            ? [$request->getPreferredLanguage(self::SUPPORTED), $request->user()?->locale]
            : [$request->user()?->locale, $request->hasSession() ? $request->session()->get('locale') : null];

        $candidates[] = $this->installationDefault();

        foreach ($candidates as $locale) {
            if (in_array($locale, self::SUPPORTED, true)) {
                app()->setLocale($locale);
                break;
            }
        }

        return $next($request);
    }

    private function installationDefault(): string
    {
        try {
            return $this->settings->get('core.default_locale');
        } catch (Throwable) {
            return config('app.locale'); // not installed yet
        }
    }

    public static function isRtl(?string $locale = null): bool
    {
        return ($locale ?? app()->getLocale()) === 'ar';
    }
}
