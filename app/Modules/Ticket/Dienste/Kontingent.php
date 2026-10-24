<?php

namespace App\Modules\Ticket\Dienste;

use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Models\Auftragsposition;
use App\Modules\Ticket\Models\Kartenauftrag;
use App\Modules\Ticket\Models\Reservierung;
use App\Modules\Ticket\Models\Reservierungsposition;
use App\Modules\Ticket\Models\Ticket;

/**
 * Kontingent als Gesamtzahl pro Vorstellung, Freikarten eingeschlossen (E39, E6).
 * Belegt wird erst mit der Bestätigung eines Auftrags (E30); angeforderte Mengen werden
 * nur angezeigt (E26a). Reservierungen belegen sofort (E43): gedruckte über ihre Karten,
 * digitale und Vorkasse-Reservierungen über ihre vorgemerkten Plätze.
 */
class Kontingent
{
    public function belegt(Vorstellung $vorstellung): int
    {
        return Ticket::where('vorstellung_id', $vorstellung->id)->whereIn('status', Ticket::BELEGEND)->count()
            + $this->vorgemerkt($vorstellung);
    }

    /** Plätze aus Reservierungen, für die noch keine Karten existieren. */
    public function vorgemerkt(Vorstellung $vorstellung): int
    {
        $ids = Reservierung::vorgemerkt()->where('vorstellung_id', $vorstellung->id)->pluck('id');

        return (int) Reservierungsposition::whereIn('reservierung_id', $ids)->sum('anzahl');
    }

    public function eingelassen(Vorstellung $vorstellung): int
    {
        return Ticket::where('vorstellung_id', $vorstellung->id)->where('status', 'eingelassen')->count();
    }

    public function frei(Vorstellung $vorstellung): ?int
    {
        return $vorstellung->kontingent === null ? null : max(0, $vorstellung->kontingent - $this->belegt($vorstellung));
    }

    public function angefordertOffen(Vorstellung $vorstellung): int
    {
        $auftraege = Kartenauftrag::where('vorstellung_id', $vorstellung->id)->where('status', 'angefordert')->pluck('id');

        return (int) Auftragsposition::whereIn('kartenauftrag_id', $auftraege)->sum('anzahl');
    }

    /** @return array{kontingent: ?int, belegt: int, frei: ?int, angefordert: int, freikarten: int, vorgemerkt: int, eingelassen: int} */
    public function uebersicht(Vorstellung $vorstellung): array
    {
        return [
            'kontingent' => $vorstellung->kontingent,
            'belegt' => $this->belegt($vorstellung),
            'frei' => $this->frei($vorstellung),
            'angefordert' => $this->angefordertOffen($vorstellung),
            'freikarten' => Ticket::where('vorstellung_id', $vorstellung->id)->whereIn('status', Ticket::BELEGEND)->where('ist_freikarte', true)->count(),
            'vorgemerkt' => $this->vorgemerkt($vorstellung),
            'eingelassen' => $this->eingelassen($vorstellung),
        ];
    }
}
