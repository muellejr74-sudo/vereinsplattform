<?php

namespace App\Modules\Ticket\Dienste;

use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Models\Ticketkategorie;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Erzeugt Karten mit fortlaufender Nummer und Prüfzeichen – an genau einer Stelle für
 * Kartenaufträge, Reservierungen, Vorkasse und Abendkasse (E45, E113, E14d).
 * Muss innerhalb einer Transaktion laufen, in der die Vorstellung gesperrt ist.
 */
class Kartenerzeugung
{
    public function __construct(private Mandantenkontext $kontext, private Nummernvergabe $nummern) {}

    /**
     * @param  list<array{kategorie: Ticketkategorie, anzahl: int, preis_cent: int}>  $zeilen
     * @param  array<string, mixed>  $gemeinsam  Felder für alle Karten (status, name, zahlungsart …)
     * @param  bool  $stapel  Stapelposition „3/20“ vergeben (E114b)
     * @return Collection<int, Ticket>
     */
    public function erzeugen(Vorstellung $vorstellung, array $zeilen, array $gemeinsam, bool $stapel): Collection
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Karten dürfen nur innerhalb einer Transaktion erzeugt werden.');
        }

        $summe = array_sum(array_column($zeilen, 'anzahl'));
        if ($summe === 0) {
            return collect();
        }

        $nummern = $this->nummern->naechste($this->kontext->mandant(), $summe);
        $tickets = collect();
        $lauf = 0;

        foreach ($zeilen as $zeile) {
            for ($i = 0; $i < $zeile['anzahl']; $i++) {
                $tickets->push(Ticket::create(array_merge([
                    'nummer' => $nummern[$lauf],
                    'pruefzeichen' => Nummernvergabe::pruefzeichen(),
                    'vorstellung_id' => $vorstellung->id,
                    'kategorie_id' => $zeile['kategorie']->id,
                    'preis_cent' => $zeile['preis_cent'],
                    'ist_freikarte' => (bool) $zeile['kategorie']->ist_freikarte,
                    'stapel_position' => $stapel ? $lauf + 1 : null,
                    'stapel_groesse' => $stapel ? $summe : null,
                ], $gemeinsam)));
                $lauf++;
            }
        }

        return $tickets;
    }
}
