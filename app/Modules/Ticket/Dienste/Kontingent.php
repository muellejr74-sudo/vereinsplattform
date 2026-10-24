<?php

namespace App\Modules\Ticket\Dienste;

use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Models\Auftragsposition;
use App\Modules\Ticket\Models\Kartenauftrag;
use App\Modules\Ticket\Models\Ticket;

/**
 * Kontingent als Gesamtzahl pro Vorstellung, Freikarten eingeschlossen (E39, E6).
 * Belegt wird erst mit der Bestätigung eines Auftrags (E30); angeforderte Mengen werden
 * nur angezeigt (E26a). Reservierungen kommen in Stufe 4 hinzu.
 */
class Kontingent
{
    public function belegt(Vorstellung $vorstellung): int
    {
        return Ticket::where('vorstellung_id', $vorstellung->id)->whereIn('status', Ticket::BELEGEND)->count();
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

    /** @return array{kontingent: ?int, belegt: int, frei: ?int, angefordert: int, freikarten: int} */
    public function uebersicht(Vorstellung $vorstellung): array
    {
        return [
            'kontingent' => $vorstellung->kontingent,
            'belegt' => $this->belegt($vorstellung),
            'frei' => $this->frei($vorstellung),
            'angefordert' => $this->angefordertOffen($vorstellung),
            'freikarten' => Ticket::where('vorstellung_id', $vorstellung->id)->whereIn('status', Ticket::BELEGEND)->where('ist_freikarte', true)->count(),
        ];
    }
}
