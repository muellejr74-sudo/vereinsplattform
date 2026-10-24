<?php

use App\Modules\Kern\Http\Middleware\NurSuperAdmin;
use App\Modules\Kern\Http\Middleware\SetzeMandantenkontext;
use App\Modules\Kern\Http\Middleware\ZweiFaktorEingerichtet;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Aktiven Verein bei jeder Anfrage setzen und prüfen (Mandantentrennung, E14a).
        $middleware->appendToGroup('web', SetzeMandantenkontext::class);

        $middleware->alias([
            'superadmin' => NurSuperAdmin::class,
            'zweifaktor' => ZweiFaktorEingerichtet::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('anmeldung'));
        $middleware->redirectUsersTo(fn () => route('startseite'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
