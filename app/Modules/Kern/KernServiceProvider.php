<?php

namespace App\Modules\Kern;

use App\Models\User;
use App\Modules\Kern\Konsole\SuperAdminAnlegen;
use App\Modules\Kern\Konsole\ZweiFaktorZuruecksetzen;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Rechte\Berechtigung;
use App\Modules\Kern\Rechte\Rechtekatalog;
use Illuminate\Support\Facades\Gate;
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
        $this->app->scoped(Berechtigung::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Datenbank/Migrationen');
        $this->loadViewsFrom(__DIR__.'/Ansichten', 'kern');

        Route::middleware('web')->group(__DIR__.'/routes.php');

        // Rechteprüfung (E19): Jedes Recht aus dem Katalog ist als Laravel-"Gate" nutzbar,
        // z. B. Middleware "can:benutzer.verwalten" oder in Ansichten @can('rechte.vergeben').
        Gate::before(function (User $benutzer, string $faehigkeit, array $argumente) {
            if (Rechtekatalog::gibt($faehigkeit)) {
                $verein = ($argumente[0] ?? null) instanceof Mandant ? $argumente[0] : null;

                return app(Berechtigung::class)->darf($benutzer, $faehigkeit, $verein);
            }

            return null;
        });
        Gate::define('verein.verwalten', fn (User $benutzer) => app(Berechtigung::class)->darfVereinVerwalten($benutzer));

        if ($this->app->runningInConsole()) {
            $this->commands([
                SuperAdminAnlegen::class,
                ZweiFaktorZuruecksetzen::class,
            ]);
        }
    }
}
