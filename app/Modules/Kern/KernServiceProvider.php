<?php

namespace App\Modules\Kern;

use App\Modules\Kern\Konsole\SuperAdminAnlegen;
use App\Modules\Kern\Konsole\ZweiFaktorZuruecksetzen;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Plattformkern: Mandanten, Benutzer, Anmeldung, Protokoll, Rechtstexte.
 *
 * Aufbau eines Moduls (gilt auch für spätere Module):
 *   Datenbank/Migrationen  Tabellen des Moduls
 *   Models                 Datenmodelle
 *   Dienste                wiederverwendbare Fachlogik
 *   Http                   Controller und Middleware
 *   Konsole                Server-Befehle (php artisan …)
 *   Nachrichten            E-Mails
 *   Ansichten              Seitenvorlagen (Blade), Namensraum "kern::"
 *   routes.php             Adressen des Moduls
 */
class KernServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Ein Mandantenkontext pro Anfrage (bzw. pro Befehl).
        $this->app->scoped(Mandantenkontext::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Datenbank/Migrationen');
        $this->loadViewsFrom(__DIR__.'/Ansichten', 'kern');

        Route::middleware('web')->group(__DIR__.'/routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SuperAdminAnlegen::class,
                ZweiFaktorZuruecksetzen::class,
            ]);
        }
    }
}
