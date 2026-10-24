<?php

namespace App\Modules\Ticket;

use App\Models\User;
use App\Modules\Ticket\Dienste\TicketZugriff;
use App\Modules\Ticket\Http\Middleware\AbendkasseGeoeffnet;
use App\Modules\Ticket\Http\Middleware\TicketmodulAktiv;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Modul Ticketverwaltung (Stufe 3: Kategorien, Kontingent, Verkaufsstellen, Kartenaufträge, Druck).
 * Aufbau wie im Kern beschrieben; Ansichten im Namensraum "ticket::".
 */
class TicketServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Datenbank/Migrationen');
        $this->loadViewsFrom(__DIR__.'/Ansichten', 'ticket');
        $router->aliasMiddleware('ticketmodul', TicketmodulAktiv::class);
        $router->aliasMiddleware('abendkasse.offen', AbendkasseGeoeffnet::class);

        Route::middleware('web')->group(__DIR__.'/routes.php');

        // Menüpunkt „Tickets“ und Zugang zur Ticketverwaltung.
        Gate::define('tickets.nutzen', fn (User $benutzer) => app(TicketZugriff::class)->darfTicketsNutzen($benutzer));
    }
}
