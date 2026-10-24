<?php

namespace App\Modules\Ticket\Dienste;

use App\Models\User;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Models\Auftragsposition;
use App\Modules\Ticket\Models\Kartenauftrag;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Models\Ticketkategorie;
use App\Modules\Ticket\Models\Verkaufsstelle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Kartenaufträge und Freikarten-Stapel (E24–E26a, E30, E114, E114c, E116).
 */
class Auftragsverwaltung
{
    public function __construct(
        private Mandantenkontext $kontext,
        private Nummernvergabe $nummern,
        private Kontingent $kontingent,
    ) {}

    /**
     * @param  array<int, int>  $mengen  kategorie_id => Anzahl
     */
    public function anfordern(Vorstellung $vorstellung, ?Verkaufsstelle $stelle, string $art, array $mengen, ?string $name, ?string $bemerkung, User $durch): Kartenauftrag
    {
        $mengen = array_filter(array_map('intval', $mengen), fn ($n) => $n > 0);
        if ($mengen === []) {
            throw new InvalidArgumentException('Bitte bei mindestens einer Kategorie eine Anzahl größer als 0 eintragen.');
        }
        if ($vorstellung->istVorbei()) {
            throw new InvalidArgumentException('Diese Vorstellung ist bereits vorbei.');
        }

        $erlaubt = Ticketkategorie::where('veranstaltung_id', $vorstellung->veranstaltung_id)
            ->where('ist_freikarte', $art === 'freikarten')->pluck('id')->all();
        if (array_diff(array_keys($mengen), $erlaubt)) {
            throw new InvalidArgumentException('Ungültige Kategorie.');
        }
        if ($art === 'freikarten' && blank($name)) {
            throw new InvalidArgumentException('Bitte den Namen für die Freikarten angeben.');
        }

        return DB::transaction(function () use ($vorstellung, $stelle, $art, $mengen, $name, $bemerkung, $durch) {
            $auftrag = Kartenauftrag::create([
                'vorstellung_id' => $vorstellung->id,
                'verkaufsstelle_id' => $stelle?->id,
                'art' => $art,
                'status' => 'angefordert',
                'name' => filled($name) ? trim($name) : null,
                'bemerkung' => filled($bemerkung) ? trim($bemerkung) : null,
                'angefordert_von' => $durch->id,
            ]);
            foreach ($mengen as $kategorieId => $anzahl) {
                Auftragsposition::create(['kartenauftrag_id' => $auftrag->id, 'kategorie_id' => $kategorieId, 'anzahl' => $anzahl]);
            }
            Protokoll::schreibe('ticket.auftrag_angefordert', $auftrag, [
                'art' => $art, 'stelle' => $stelle?->name, 'anzahl' => array_sum($mengen), 'name' => $name,
            ], $durch);

            return $auftrag;
        });
    }

    /**
     * Bestätigung durch die Druckstelle (E26a, E30): Mengen ggf. anpassen, erst jetzt
     * Kontingent belegen und Ticketnummern vergeben. Sperre auf die Vorstellung verhindert,
     * dass gleichzeitige Bestätigungen das Kontingent überschreiten (E14d).
     *
     * @param  array<int, int>  $mengen  auftragsposition_id => bestätigte Anzahl
     */
    public function bestaetigen(Kartenauftrag $auftrag, array $mengen, User $durch): Collection
    {
        return DB::transaction(function () use ($auftrag, $mengen, $durch) {
            $auftrag = Kartenauftrag::whereKey($auftrag->id)->lockForUpdate()->firstOrFail();
            if ($auftrag->status !== 'angefordert') {
                throw new InvalidArgumentException('Dieser Auftrag wurde bereits bearbeitet.');
            }

            $vorstellung = Vorstellung::whereKey($auftrag->vorstellung_id)->lockForUpdate()->firstOrFail();
            if ($vorstellung->kontingent === null) {
                throw new InvalidArgumentException('Für diese Vorstellung ist noch kein Kontingent festgelegt.');
            }

            $positionen = $auftrag->positionen()->with('kategorie')->get();
            $summe = 0;
            foreach ($positionen as $position) {
                $anzahl = max(0, (int) ($mengen[$position->id] ?? $position->anzahl));
                $position->anzahl_bestaetigt = $anzahl;
                $summe += $anzahl;
            }
            if ($summe === 0) {
                throw new InvalidArgumentException('Es muss mindestens eine Karte bestätigt werden. Zum Ablehnen bitte „Ablehnen“ wählen.');
            }

            $frei = $vorstellung->kontingent - $this->kontingent->belegt($vorstellung);
            if ($summe > $frei) {
                throw new InvalidArgumentException('Nicht genug Plätze frei: '.$summe.' angefragt, '.max(0, $frei).' frei.');
            }

            $verein = $this->kontext->mandant();
            $nummern = $this->nummern->naechste($verein, $summe);
            $stapel = $auftrag->istFreikarten() || filled($auftrag->name);
            $tickets = collect();
            $lauf = 0;

            foreach ($positionen as $position) {
                $position->save();
                $preis = $position->kategorie->preisFuer($vorstellung->id);
                for ($i = 0; $i < $position->anzahl_bestaetigt; $i++) {
                    $tickets->push(Ticket::create([
                        'nummer' => $nummern[$lauf],
                        'pruefzeichen' => Nummernvergabe::pruefzeichen(),
                        'vorstellung_id' => $vorstellung->id,
                        'kategorie_id' => $position->kategorie_id,
                        'preis_cent' => $preis,
                        'ist_freikarte' => (bool) $position->kategorie->ist_freikarte,
                        'status' => 'erzeugt',
                        'name' => $auftrag->name,
                        'stapel_position' => $stapel ? $lauf + 1 : null,
                        'stapel_groesse' => $stapel ? $summe : null,
                        'kartenauftrag_id' => $auftrag->id,
                        'verkaufsstelle_id' => $auftrag->verkaufsstelle_id,
                    ]));
                    $lauf++;
                }
            }

            $auftrag->forceFill(['status' => 'bestaetigt', 'bestaetigt_von' => $durch->id, 'bestaetigt_am' => now()])->save();
            Protokoll::schreibe('ticket.auftrag_bestaetigt', $auftrag, [
                'angefordert' => (int) $positionen->sum('anzahl'), 'bestaetigt' => $summe,
                'von' => $nummern[0], 'bis' => end($nummern),
            ], $durch);

            return $tickets;
        });
    }

    public function ablehnen(Kartenauftrag $auftrag, User $durch, string $neuerStatus = 'abgelehnt'): void
    {
        DB::transaction(function () use ($auftrag, $durch, $neuerStatus) {
            $auftrag = Kartenauftrag::whereKey($auftrag->id)->lockForUpdate()->firstOrFail();
            if ($auftrag->status !== 'angefordert') {
                throw new InvalidArgumentException('Dieser Auftrag wurde bereits bearbeitet.');
            }
            $auftrag->forceFill(['status' => $neuerStatus])->save();
            Protokoll::schreibe($neuerStatus === 'abgelehnt' ? 'ticket.auftrag_abgelehnt' : 'ticket.auftrag_zurueckgezogen', $auftrag, [], $durch);
        });
    }

    /** Nach erfolgreichem Druck (E116): erzeugt → gedruckt. */
    public function alsGedrucktMarkieren(Kartenauftrag $auftrag, User $durch): int
    {
        return DB::transaction(function () use ($auftrag, $durch) {
            $anzahl = Ticket::where('kartenauftrag_id', $auftrag->id)->where('status', 'erzeugt')
                ->update(['status' => 'gedruckt', 'gedruckt_am' => now(), 'druckanzahl' => DB::raw('druckanzahl + 1')]);
            if ($auftrag->status === 'bestaetigt') {
                $auftrag->forceFill(['status' => 'gedruckt', 'gedruckt_am' => now()])->save();
            }
            Protokoll::schreibe('ticket.gedruckt', $auftrag, ['anzahl' => $anzahl], $durch);

            return $anzahl;
        });
    }

    /** Übergabe an die Verkaufsstelle bzw. den Empfänger (E26a): gedruckt → ausgegeben. */
    public function uebergeben(Kartenauftrag $auftrag, User $durch): int
    {
        return DB::transaction(function () use ($auftrag, $durch) {
            if ($auftrag->status !== 'gedruckt') {
                throw new InvalidArgumentException('Bitte die Karten zuerst drucken.');
            }
            $anzahl = Ticket::where('kartenauftrag_id', $auftrag->id)->where('status', 'gedruckt')
                ->update(['status' => 'ausgegeben', 'ausgegeben_am' => now()]);
            $auftrag->forceFill(['status' => 'ausgegeben', 'ausgegeben_am' => now()])->save();
            Protokoll::schreibe('ticket.ausgegeben', $auftrag, ['anzahl' => $anzahl], $durch);

            return $anzahl;
        });
    }

    /** Nachdruck einzelner Karten mit derselben Nummer (E117). */
    public function nachdruck(Ticket $ticket, User $durch): void
    {
        if (! in_array($ticket->status, ['gedruckt', 'ausgegeben'], true)) {
            throw new InvalidArgumentException('Nur bereits gedruckte Karten können nachgedruckt werden.');
        }
        $ticket->increment('druckanzahl');
        Protokoll::schreibe('ticket.nachdruck', $ticket, ['nummer' => $ticket->nummer, 'druck' => $ticket->druckanzahl], $durch);
    }
}
