<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Before StartSession: a fresh installation has no sessions table yet.
        $middleware->web(prepend: [
            Modules\Core\Http\Middleware\RequireInstallation::class,
        ]);
        $middleware->web(append: [
            Modules\Core\Http\Middleware\SetLocale::class,
            Modules\Core\Http\Middleware\EnsureUserIsActive::class,
        ]);
        $middleware->api(append: [
            Modules\Core\Http\Middleware\SetLocale::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('core.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
