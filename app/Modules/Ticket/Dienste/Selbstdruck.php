<?php

namespace App\Modules\Ticket\Dienste;

use App\Models\User;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Models\Auftragsposition;
use App\Modules\Ticket\Models\Kartenauftrag;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Models\Ticketkategorie;
use App\Modules\Ticket\Models\Verkaufsstelle;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Verkaufsstelle druckt selbst (E128): Karten entstehen beim Verkauf an der Stelle sofort –
 * ohne Bestätigungsschritt der Druckstelle, aber im Rahmen des Kontingents (Sperre wie E14d).
 * Abrechnung wie Vorverkauf: Die Karten gelten als „ausgegeben an Stelle X“ (E4).
 */
class Selbstdruck
{
    public function __construct(private Kontingent $kontingent, private Kartenerzeugung $erzeugung) {}

    /** @param  array<int, int>  $mengen  kategorie_id => Anzahl */
    public function verkaufen(Verkaufsstelle $stelle, Vorstellung $vorstellung, array $mengen, ?string $name, User $durch): Kartenauftrag
    {
        if (! $stelle->druckt_selbst || ! $stelle->aktiv) {
            throw new InvalidArgumentException('Diese Verkaufsstelle druckt nicht selbst.');
        }
        $mengen = array_filter(array_map('intval', $mengen), fn ($n) => $n > 0);
        if ($mengen === []) {
            throw new InvalidArgumentException('Bitte bei mindestens einer Kategorie eine Anzahl größer als 0 eintragen.');
        }
        if ($vorstellung->istVorbei()) {
            throw new InvalidArgumentException('Diese Vorstellung ist bereits vorbei.');
        }
        $kategorien = Ticketkategorie::where('veranstaltung_id', $vorstellung->veranstaltung_id)->where('ist_freikarte', false)
            ->whereIn('id', array_keys($mengen))->orderBy('reihenfolge')->get();
        if ($kategorien->count() !== count($mengen)) {
            throw new InvalidArgumentException('Ungültige Kategorie.');
        }
        $name = filled($name) ? trim($name) : null;

        return DB::transaction(function () use ($stelle, $vorstellung, $mengen, $name, $durch, $kategorien) {
            $vorstellung = Vorstellung::whereKey($vorstellung->id)->lockForUpdate()->firstOrFail();
            Abschlusssperre::pruefen($vorstellung->id);
            if ($vorstellung->kontingent === null) {
                throw new InvalidArgumentException('Für diese Vorstellung ist noch kein Kontingent festgelegt.');
            }
            $summe = array_sum($mengen);
            $frei = $vorstellung->kontingent - $this->kontingent->belegt($vorstellung);
            if ($summe > $frei) {
                throw new InvalidArgumentException('Nicht genug Plätze frei: '.$summe.' gewünscht, '.max(0, $frei).' frei.');
            }

            $auftrag = Kartenauftrag::create([
                'vorstellung_id' => $vorstellung->id, 'verkaufsstelle_id' => $stelle->id, 'art' => 'karten',
                'status' => 'ausgegeben', 'name' => $name, 'bemerkung' => 'Selbstdruck an der Verkaufsstelle', 'angefordert_von' => $durch->id,
            ]);
            $auftrag->forceFill(['bestaetigt_von' => $durch->id, 'bestaetigt_am' => now(), 'gedruckt_am' => now(), 'ausgegeben_am' => now()])->save();

            $zeilen = [];
            foreach ($kategorien as $k) {
                Auftragsposition::create(['kartenauftrag_id' => $auftrag->id, 'kategorie_id' => $k->id, 'anzahl' => $mengen[$k->id], 'anzahl_bestaetigt' => $mengen[$k->id]]);
                $zeilen[] = ['kategorie' => $k, 'anzahl' => $mengen[$k->id], 'preis_cent' => $k->preisFuer($vorstellung->id)];
            }
            $tickets = $this->erzeugung->erzeugen($vorstellung, $zeilen, [
                'status' => 'ausgegeben', 'name' => $name, 'kartenauftrag_id' => $auftrag->id, 'verkaufsstelle_id' => $stelle->id,
            ], $name !== null);
            Ticket::whereIn('id', $tickets->pluck('id'))
                ->update(['druckanzahl' => 1, 'gedruckt_am' => now(), 'ausgegeben_am' => now()]);

            Protokoll::schreibe('ticket.selbstdruck', $auftrag, [
                'stelle' => $stelle->name, 'anzahl' => $summe, 'von' => $tickets->first()->nummer, 'bis' => $tickets->last()->nummer,
            ], $durch);

            return $auftrag;
        });
    }
}
