<?php

namespace App\Modules\Ticket\Dienste;

use App\Models\User;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Ticket\Models\Kartenauftrag;
use App\Modules\Ticket\Models\Verkaufsstelle;
use Illuminate\Support\Collection;

/**
 * Wer darf welchen Kartenauftrag sehen und anfordern (E24, E25, E31, E114c, E115)?
 * Immer im aktiven Verein und nur bei freigeschaltetem Ticketmodul.
 */
class TicketZugriff
{
    public function __construct(private Mandantenkontext $kontext) {}

    public function modulAktiv(): bool
    {
        return (bool) $this->kontext->mandant()?->hatModul('ticket');
    }

    /** Aktive Verkaufsstellen, denen die Person zugeordnet ist (Super-Admin: alle). */
    public function stellenVon(User $benutzer): Collection
    {
        $abfrage = Verkaufsstelle::where('aktiv', true)->orderBy('name');

        return $benutzer->istSuperAdmin()
            ? $abfrage->get()
            : $abfrage->whereHas('benutzer', fn ($q) => $q->whereKey($benutzer->id))->get();
    }

    public function istDruckstelle(User $benutzer): bool
    {
        return $benutzer->can('ticket.drucken');
    }

    public function darfIntern(User $benutzer): bool
    {
        return $benutzer->can('ticket.reservierungen');
    }

    public function darfTicketsNutzen(User $benutzer): bool
    {
        if (! $this->modulAktiv()) {
            return false;
        }

        foreach (['ticket.drucken', 'ticket.kategorien', 'ticket.kontingente', 'ticket.verkaufsstellen', 'ticket.reservierungen'] as $recht) {
            if ($benutzer->can($recht)) {
                return true;
            }
        }

        return $this->stellenVon($benutzer)->isNotEmpty();
    }

    public function darfAuftragSehen(User $benutzer, Kartenauftrag $auftrag): bool
    {
        if ($this->istDruckstelle($benutzer)) {
            return true;
        }
        if ($auftrag->verkaufsstelle_id === null) {
            return $this->darfIntern($benutzer);
        }

        return $this->stellenVon($benutzer)->contains('id', $auftrag->verkaufsstelle_id);
    }

    /** Für wen darf die Person anfordern? null = „Verein intern“ (nur Freikarten-Stapel, E114c). */
    public function darfAnfordern(User $benutzer, ?Verkaufsstelle $stelle, string $art): bool
    {
        if ($stelle === null) {
            return $art === 'freikarten' && $this->darfIntern($benutzer);
        }

        return $stelle->aktiv && $this->stellenVon($benutzer)->contains('id', $stelle->id);
    }
}
