<?php

namespace App\Modules\Ticket\Dienste;

use App\Modules\Kern\Models\Stationsgeraet;
use App\Modules\Ticket\Models\Ticket;

/**
 * Einlass-Scan an der Station „Einlass“ (E29, E43, E119, E122). Immer mit Prüfzeichen (E14d).
 * Ergebnis: gruen = eingelassen, gelb = schon eingelassen, rot = ungültig.
 */
class Einlass
{
    public const GUELTIG = ['gedruckt', 'ausgegeben', 'verkauft'];

    public function __construct(private Kontingent $kontingent) {}

    /** @return array{farbe: string, titel: string, text: string} */
    public function scannen(Stationsgeraet $geraet, string $eingabe, string $akteur): array
    {
        $ticket = TicketCode::finde($eingabe);
        if (! $ticket) {
            return $this->ergebnis('rot', 'Ungültig', 'Diese Karte ist nicht gültig.');
        }

        $text = $ticket->nummer.' · '.($ticket->ist_freikarte ? 'Freikarte' : ($ticket->kategorie?->name ?? ''));
        if ($ticket->vorstellung_id !== $geraet->vorstellung_id) {
            $v = $ticket->vorstellung;

            return $this->ergebnis('rot', 'Andere Vorstellung', 'Diese Karte gilt für '.$v->veranstaltung->name.', '.$v->anzeige().'.');
        }
        if ($ticket->status === 'storniert') {
            return $this->ergebnis('rot', 'Storniert', $text.' – diese Karte ist storniert.');
        }
        if ($ticket->status === 'eingelassen') {
            return $this->ergebnis('gelb', 'Schon eingelassen', $text.' – eingelassen um '.($ticket->eingelassen_am?->format('H:i') ?? '?').' Uhr.');
        }
        if (! in_array($ticket->status, self::GUELTIG, true)) {
            return $this->ergebnis('rot', 'Nicht freigegeben', $text.' – diese Karte wurde noch nicht ausgegeben.');
        }

        // Nur ein Gerät gewinnt, auch wenn zwei gleichzeitig dieselbe Karte scannen.
        $geaendert = Ticket::whereKey($ticket->id)->whereIn('status', self::GUELTIG)
            ->update(['status' => 'eingelassen', 'eingelassen_am' => now(), 'eingelassen_durch' => mb_substr($akteur, 0, 120)]);
        if ($geaendert === 0) {
            $ticket->refresh();

            return $this->ergebnis('gelb', 'Schon eingelassen', $text.' – eingelassen um '.($ticket->eingelassen_am?->format('H:i') ?? '?').' Uhr.');
        }

        return $this->ergebnis('gruen', 'Willkommen', $text); // ohne Namen (Station ohne Konto)
    }

    /** @return array{eingelassen: int, belegt: int} */
    public function zaehler(Stationsgeraet $geraet): array
    {
        return [
            'eingelassen' => $this->kontingent->eingelassen($geraet->vorstellung),
            'belegt' => $this->kontingent->belegt($geraet->vorstellung),
        ];
    }

    private function ergebnis(string $farbe, string $titel, string $text): array
    {
        return ['farbe' => $farbe, 'titel' => $titel, 'text' => $text];
    }
}
