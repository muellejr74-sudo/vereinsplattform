<?php

namespace App\Modules\Ticket\Dienste;

use App\Models\User;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Ticket\Models\Kartenauftrag;
use App\Modules\Ticket\Models\Reservierung;
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

        foreach (['ticket.drucken', 'ticket.kategorien', 'ticket.kontingente', 'ticket.verkaufsstellen', 'ticket.reservierungen', 'ticket.abendkasse', 'ticket.stornieren', 'ticket.zahlungseingang', 'ticket.auswertungen', 'ticket.abschluss'] as $recht) {
            if ($benutzer->can($recht)) {
                return true;
            }
        }

        return $this->stellenVon($benutzer)->isNotEmpty();
    }

    /** Verkaufsstellen der Person, die ihre Karten selbst drucken (E128). */
    public function selbstdruckStellen(User $benutzer): Collection
    {
        return $this->stellenVon($benutzer)->where('druckt_selbst', true)->values();
    }

    /** Reservierungen annehmen: Vereinsmitglieder mit Recht „Reservierungen“ und Vorverkaufsstellen (E124). */
    public function darfReservieren(User $benutzer): bool
    {
        return $this->darfIntern($benutzer) || $this->stellenVon($benutzer)->contains('art', 'vorverkauf');
    }

    /** Für welche Stellen darf die Person Reservierungen annehmen? (null = Verein) */
    public function reservierungsStellen(User $benutzer): Collection
    {
        return $this->stellenVon($benutzer)->where('art', 'vorverkauf')->values();
    }

    /** Alle Reservierungen sehen: Reservierungen, Zahlungseingang, Druckstelle, Abendkasse. */
    public function siehtAlleReservierungen(User $benutzer): bool
    {
        foreach (['ticket.reservierungen', 'ticket.zahlungseingang', 'ticket.drucken', 'ticket.abendkasse'] as $recht) {
            if ($benutzer->can($recht)) {
                return true;
            }
        }

        return false;
    }

    public function darfReservierungSehen(User $benutzer, Reservierung $reservierung): bool
    {
        if ($this->siehtAlleReservierungen($benutzer)) {
            return true;
        }

        return $reservierung->verkaufsstelle_id !== null && $this->stellenVon($benutzer)->contains('id', $reservierung->verkaufsstelle_id);
    }

    /** Offene Reservierung ändern/stornieren: wer sie sehen und annehmen darf (E124). */
    public function darfReservierungBearbeiten(User $benutzer, Reservierung $reservierung): bool
    {
        if ($this->darfIntern($benutzer) || $benutzer->can('ticket.zahlungseingang')) {
            return true;
        }

        return $reservierung->verkaufsstelle_id !== null && $this->reservierungsStellen($benutzer)->contains('id', $reservierung->verkaufsstelle_id);
    }

    public function darfAuftragSehen(User $benutzer, Kartenauftrag $auftrag): bool
    {
        if ($this->istDruckstelle($benutzer)) {
            return true;
        }
        if ($auftrag->istReservierung()) {
            return $this->siehtAlleReservierungen($benutzer);
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
