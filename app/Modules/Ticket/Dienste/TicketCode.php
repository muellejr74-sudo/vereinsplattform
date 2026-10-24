<?php

namespace App\Modules\Ticket\Dienste;

use App\Modules\Ticket\Models\Ticket;

/**
 * Findet eine Karte aus einer Scan- oder Tastatureingabe (E112, E119):
 *  - kompletter Link aus dem QR-Code: https://…/k/TVM-26-00412.X7KQ2M
 *  - Nummer mit Prüfzeichen:          TVM-26-00412.X7KQ2M
 *  - nur die Nummer:                  TVM-26-00412 (nur, wo ausdrücklich erlaubt – z. B. beim
 *    Stornieren durch berechtigte Personen; beim Einlass immer mit Prüfzeichen, E14d)
 * Gesucht wird immer nur im aktiven Verein (Mandantenfilter).
 */
class TicketCode
{
    public const MUSTER = '/([A-Z0-9]{2,6}-\d{2}-\d{5})(?:\.([A-Z0-9]{6}))?/';

    public static function finde(string $eingabe, bool $ohnePruefzeichenErlaubt = false): ?Ticket
    {
        $eingabe = strtoupper(trim($eingabe));
        if (! preg_match(self::MUSTER, $eingabe, $treffer)) {
            return null;
        }

        $ticket = Ticket::where('nummer', $treffer[1])->first();
        if (! $ticket) {
            return null;
        }

        $zeichen = $treffer[2] ?? '';
        if ($zeichen === '') {
            return $ohnePruefzeichenErlaubt ? $ticket : null;
        }

        return hash_equals($ticket->pruefzeichen, $zeichen) ? $ticket : null;
    }
}
