<?php

namespace App\Modules\Ticket\Dienste;

use App\Models\User;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Models\Ticketkategorie;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Nachbuchen nach Beginn der Vorstellung bis zum Abschluss (E129): Verkäufe (z. B. nach der
 * Notfallliste bei Internetausfall) und Freikarten. Karten mit Nummer ohne Etikett, als eingelassen.
 */
class Nachbuchung
{
    public function __construct(private Kontingent $kontingent, private Kartenerzeugung $erzeugung) {}

    /** @param  array<int, int>  $mengen  kategorie_id => Anzahl */
    public function verkauf(Vorstellung $vorstellung, array $mengen, string $zahlungsart, User $durch): array
    {
        if (! in_array($zahlungsart, ['bar', 'karte', 'ueberweisung'], true)) {
            throw new InvalidArgumentException('Bitte die Zahlungsart wählen.');
        }
        $mengen = array_filter(array_map('intval', $mengen), fn ($n) => $n > 0);
        if ($mengen === []) {
            throw new InvalidArgumentException('Bitte bei mindestens einer Kategorie eine Anzahl eintragen.');
        }
        $kategorien = Ticketkategorie::where('veranstaltung_id', $vorstellung->veranstaltung_id)->where('ist_freikarte', false)
            ->whereIn('id', array_keys($mengen))->orderBy('reihenfolge')->get();
        if ($kategorien->count() !== count($mengen)) {
            throw new InvalidArgumentException('Ungültige Kategorie.');
        }

        return $this->buchen($vorstellung, $durch, fn ($v) => $kategorien->map(fn ($k) => ['kategorie' => $k, 'anzahl' => $mengen[$k->id], 'preis_cent' => $k->preisFuer($v->id)])->all(), [
            'zahlungsart' => $zahlungsart, 'verkauft_am' => now(), 'verkauft_von' => $durch->id,
        ], null, ['art' => 'verkauf', 'zahlungsart' => $zahlungsart]);
    }

    public function freikarten(Vorstellung $vorstellung, int $anzahl, string $name, User $durch): array
    {
        if ($anzahl < 1 || $anzahl > 500) {
            throw new InvalidArgumentException('Bitte eine Anzahl zwischen 1 und 500 angeben.');
        }
        if (blank($name)) {
            throw new InvalidArgumentException('Bitte den Namen für die Freikarten angeben.');
        }
        $kategorie = Ticketkategorie::freikarte($vorstellung->veranstaltung);

        return $this->buchen($vorstellung, $durch, fn () => [['kategorie' => $kategorie, 'anzahl' => $anzahl, 'preis_cent' => 0]],
            [], trim($name), ['art' => 'freikarten', 'name' => trim($name)]);
    }

    /** @return array{anzahl: int, betrag_cent: int} */
    private function buchen(Vorstellung $vorstellung, User $durch, \Closure $zeilen, array $verkauf, ?string $name, array $protokoll): array
    {
        if ($vorstellung->beginn_um->isFuture()) {
            throw new InvalidArgumentException('Nachbuchen ist erst ab Beginn der Vorstellung möglich – vorher bitte Abendkasse oder Reservierung nutzen.');
        }

        return DB::transaction(function () use ($vorstellung, $durch, $zeilen, $verkauf, $name, $protokoll) {
            $vorstellung = Vorstellung::whereKey($vorstellung->id)->lockForUpdate()->firstOrFail();
            Abschlusssperre::pruefen($vorstellung->id);
            $liste = $zeilen($vorstellung);
            $summe = array_sum(array_column($liste, 'anzahl'));
            if ($vorstellung->kontingent !== null) {
                $frei = $vorstellung->kontingent - $this->kontingent->belegt($vorstellung);
                if ($summe > $frei) {
                    throw new InvalidArgumentException('Nicht genug Plätze frei: '.$summe.' gewünscht, '.max(0, $frei).' frei.');
                }
            }

            $tickets = $this->erzeugung->erzeugen($vorstellung, $liste, array_merge([
                'status' => 'eingelassen', 'eingelassen_am' => now(), 'eingelassen_durch' => 'Nachbuchung',
                'verkauf_ueber' => 'nachbuchung', 'name' => $name,
            ], $verkauf), $name !== null);
            $betrag = (int) $tickets->sum('preis_cent');

            Protokoll::schreibe('ticket.nachbuchung', $vorstellung, $protokoll + [
                'anzahl' => $summe, 'betrag_cent' => $betrag, 'von' => $tickets->first()->nummer, 'bis' => $tickets->last()->nummer,
            ], $durch);

            return ['anzahl' => $summe, 'betrag_cent' => $betrag];
        });
    }
}
