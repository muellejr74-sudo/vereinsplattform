<?php

namespace App\Modules\Ticket\Dienste;

use App\Models\User;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Ticket\Models\Reservierung;
use App\Modules\Ticket\Models\Ticket;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Stornieren einzelner Karten und Massen-Storno per Dauer-Scan (E1, E43, E4b, E119).
 * Eine stornierte Nummer bleibt dauerhaft ungültig, der Platz wird frei.
 */
class Stornierung
{
    /** Warum kann diese Karte nicht storniert werden? null = möglich. */
    public function hinderungsgrund(Ticket $ticket): ?string
    {
        return match ($ticket->status) {
            'storniert' => 'bereits storniert',
            'eingelassen' => 'bereits eingelassen – nicht mehr stornierbar',
            default => null,
        };
    }

    /** Hinweis für die Person am Bildschirm, z. B. Geld zurückgeben. */
    public function hinweis(Ticket $ticket): ?string
    {
        if ($ticket->zahlungsart && $ticket->status === 'verkauft') {
            return 'bereits bezahlt ('.(Reservierung::ZAHLUNGSARTEN[$ticket->zahlungsart] ?? $ticket->zahlungsart).') – Geld zurückgeben';
        }

        return null;
    }

    /**
     * @param  list<int>  $ticketIds
     * @return list<string> stornierte Nummern
     */
    public function stornieren(array $ticketIds, User $durch): array
    {
        if ($ticketIds === []) {
            throw new InvalidArgumentException('Keine Karten ausgewählt.');
        }

        return DB::transaction(function () use ($ticketIds, $durch) {
            $karten = Ticket::whereIn('id', $ticketIds)->orderBy('id')->lockForUpdate()->get();
            $nummern = [];
            foreach ($karten as $karte) {
                if ($this->hinderungsgrund($karte) !== null) {
                    continue;
                }
                $karte->forceFill(['status' => 'storniert', 'storniert_am' => now(), 'storniert_von' => $durch->id])->save();
                $nummern[] = $karte->nummer;
            }
            if ($nummern !== []) {
                Protokoll::schreibe('ticket.storniert', $karten->first(), ['anzahl' => count($nummern), 'nummern' => $nummern], $durch);
            }

            return $nummern;
        });
    }
}
