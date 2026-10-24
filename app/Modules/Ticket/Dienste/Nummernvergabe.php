<?php

namespace App\Modules\Ticket\Dienste;

use App\Modules\Kern\Models\Mandant;
use App\Modules\Ticket\Models\Nummernkreis;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Fortlaufende Ticketnummern je Verein und Jahr der Erzeugung (E45, E113):
 * KÜRZEL-JJ-NNNNN, z. B. TVM-26-00412. Die Zeile des Nummernkreises wird gesperrt,
 * damit zwei gleichzeitige Vorgänge nie dieselbe Nummer erhalten.
 *
 * Muss innerhalb einer Datenbank-Transaktion aufgerufen werden.
 */
class Nummernvergabe
{
    /** @return list<string> */
    public function naechste(Mandant $verein, int $anzahl): array
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Ticketnummern dürfen nur innerhalb einer Transaktion vergeben werden.');
        }

        $jahr = (int) now()->format('Y');
        $kreis = Nummernkreis::where('jahr', $jahr)->lockForUpdate()->first();

        if (! $kreis) {
            try {
                Nummernkreis::create(['jahr' => $jahr, 'letzte_nummer' => 0]);
            } catch (QueryException) {
                // Gleichzeitig von einem anderen Vorgang angelegt – dann diesen verwenden.
            }
            $kreis = Nummernkreis::where('jahr', $jahr)->lockForUpdate()->firstOrFail();
        }

        $start = $kreis->letzte_nummer + 1;
        $kreis->update(['letzte_nummer' => $kreis->letzte_nummer + $anzahl]);

        $kurz = substr((string) $jahr, -2);
        $nummern = [];
        for ($n = $start; $n < $start + $anzahl; $n++) {
            $nummern[] = sprintf('%s-%s-%05d', $verein->kuerzel, $kurz, $n);
        }

        return $nummern;
    }

    /** Zufälliges, nicht erratbares Prüfzeichen (E14d, E112). */
    public static function pruefzeichen(): string
    {
        $zeichen = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $wert = '';
        for ($i = 0; $i < 6; $i++) {
            $wert .= $zeichen[random_int(0, strlen($zeichen) - 1)];
        }

        return $wert;
    }
}
