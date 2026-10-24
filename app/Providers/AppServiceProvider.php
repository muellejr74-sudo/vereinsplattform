<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Passwortregel für alle Benutzer: mindestens 10 Zeichen.
        Password::defaults(fn () => Password::min(10));

        // Seitenblätterung mit schlichter, eigener Vorlage (ohne Tailwind).
        Paginator::defaultView('kern::teile.seiten');
        Paginator::defaultSimpleView('kern::teile.seiten');
    }
}
