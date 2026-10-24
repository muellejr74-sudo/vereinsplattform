<?php

namespace App\Modules\Ticket\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Models\Veranstaltung;
use App\Modules\Ticket\Dienste\Kontingent;
use App\Modules\Ticket\Dienste\TicketZugriff;
use App\Modules\Ticket\Models\Kartenauftrag;
use App\Modules\Ticket\Models\Reservierung;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Startseite der Ticketverwaltung: offene Aufträge, Veranstaltungen mit Kontingent (E26a, E39). */
class UebersichtController extends Controller
{
    public function __invoke(Request $request, TicketZugriff $zugriff, Kontingent $kontingent): View
    {
        $benutzer = $request->user();
        $verwaltung = $benutzer->can('ticket.drucken') || $benutzer->can('ticket.kategorien') || $benutzer->can('ticket.kontingente');

        $veranstaltungen = $verwaltung
            ? Veranstaltung::with(['vorstellungen' => fn ($q) => $q->where('beginn_um', '>=', now()->subDays(7))])->orderByDesc('id')->get()
                ->filter(fn ($v) => $v->vorstellungen->isNotEmpty())
            : collect();

        return view('ticket::uebersicht', [
            'veranstaltungen' => $veranstaltungen,
            'kontingent' => $kontingent,
            'offen' => $zugriff->istDruckstelle($benutzer) ? Kartenauftrag::whereIn('status', ['angefordert', 'bestaetigt', 'gedruckt'])->count() : null,
            'stellen' => $zugriff->stellenVon($benutzer),
            'intern' => $zugriff->darfIntern($benutzer),
            'reservieren' => $zugriff->darfReservieren($benutzer) || $zugriff->siehtAlleReservierungen($benutzer),
            'ueberfaellig' => $benutzer->can('ticket.zahlungseingang') || $benutzer->can('ticket.reservierungen') ? Reservierung::ueberfaellig()->count() : 0,
        ]);
    }
}
