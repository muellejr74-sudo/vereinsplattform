<?php

namespace App\Modules\Ticket\Http\Middleware;

use App\Modules\Ticket\Dienste\TicketZugriff;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Ticketseiten nur, wenn das Modul für den aktiven Verein freigeschaltet ist. */
class TicketmodulAktiv
{
    public function __construct(private TicketZugriff $zugriff) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->zugriff->modulAktiv(), 403, 'Die Ticketverwaltung ist für diesen Verein nicht freigeschaltet.');
        abort_unless($this->zugriff->darfTicketsNutzen($request->user()), 403);

        return $next($request);
    }
}
