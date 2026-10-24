<?php

use App\Modules\Kern\Http\Middleware\NurSuperAdmin;
use App\Modules\Kern\Http\Middleware\SetzeMandantenkontext;
use App\Modules\Kern\Http\Middleware\VereinErforderlich;
use App\Modules\Kern\Http\Middleware\ZweiFaktorEingerichtet;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

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
            'verein' => VereinErforderlich::class,
        ]);

        // Zugriffsprüfung VOR dem Laden von Datensätzen aus der Adresse: Wer keinen Zugriff hat,
        // erhält immer "Kein Zugriff" und erfährt nicht, ob es einen Datensatz gibt.
        // Ebenso muss der aktive Verein feststehen, bevor Datensätze aus der Adresse geladen werden –
        // sonst würde der Mandantenfilter (zu Recht) nichts finden.
        $middleware->prependToPriorityList(SubstituteBindings::class, SetzeMandantenkontext::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, NurSuperAdmin::class);

        $middleware->redirectGuestsTo(fn () => route('anmeldung'));
        $middleware->redirectUsersTo(fn () => route('startseite'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
