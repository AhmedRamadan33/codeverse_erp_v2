<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Modules\DatabaseActivator;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Before setup, every web page leads to the setup wizard; after it, the wizard is gone.
 *
 * Runs first in the web group: until the database is migrated there are no session or cache
 * tables, so the wizard uses file sessions and cache.
 */
class RequireInstallation
{
    public function __construct(private readonly ActivatorInterface $activator) {}

    public function handle(Request $request, Closure $next): Response
    {
        $installed = ! $this->activator instanceof DatabaseActivator || $this->activator->isInstalled();
        $wizard = $request->routeIs('core.install', 'core.locale.update', '*livewire.update');

        if ($installed) {
            abort_if($request->routeIs('core.install'), 404);

            return $next($request);
        }

        config(['session.driver' => 'file', 'cache.default' => 'file']);

        return $wizard ? $next($request) : redirect()->route('core.install');
    }
}
