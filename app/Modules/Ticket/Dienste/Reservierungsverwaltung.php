<?php

namespace App\Modules\Ticket\Dienste;

use App\Models\User;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Models\Auftragsposition;
use App\Modules\Ticket\Models\Kartenauftrag;
use App\Modules\Ticket\Models\Reservierung;
use App\Modules\Ticket\Models\Reservierungsposition;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Models\Ticketkategorie;
use App\Modules\Ticket\Models\Verkaufsstelle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Telefonische Reservierungen (E40, E40a, E41, E114, E114a, E120, E123, E124, E126).
 * Alle Schritte in Transaktionen mit Sperre auf die Vorstellung bzw. die Reservierung (E14d).
 */
class Reservierungsverwaltung
{
    public function __construct(private Kontingent $kontingent, private Kartenerzeugung $erzeugung) {}

    /**
     * @param  array<int, int>  $mengen  kategorie_id => Anzahl
     * @param  array{name: string, telefon?: ?string, strasse?: ?string, plz?: ?string, ort?: ?string, bemerkung?: ?string, zahlungsfrist?: ?string}  $daten
     */
    public function anlegen(Vorstellung $vorstellung, ?Verkaufsstelle $stelle, string $art, array $mengen, array $daten, User $durch): Reservierung
    {
        if (! array_key_exists($art, Reservierung::ARTEN)) {
            throw new InvalidArgumentException('Unbekannte Reservierungsart.');
        }
        $mengen = array_filter(array_map('intval', $mengen), fn ($n) => $n > 0);
        if ($mengen === []) {
            throw new InvalidArgumentException('Bitte bei mindestens einer Kategorie eine Anzahl größer als 0 eintragen.');
        }
        if ($vorstellung->istVorbei()) {
            throw new InvalidArgumentException('Diese Vorstellung ist bereits vorbei.');
        }
        $kategorien = Ticketkategorie::where('veranstaltung_id', $vorstellung->veranstaltung_id)->where('ist_freikarte', false)
            ->whereIn('id', array_keys($mengen))->get()->keyBy('id');
        if ($kategorien->count() !== count($mengen)) {
            throw new InvalidArgumentException('Ungültige Kategorie.');
        }
        if ($art === 'vorkasse' && (blank($daten['strasse'] ?? null) || blank($daten['plz'] ?? null) || blank($daten['ort'] ?? null))) {
            throw new InvalidArgumentException('Für den Versand per Post bitte die vollständige Anschrift angeben.');
        }
        $frist = null;
        if ($art === 'vorkasse') {
            $frist = filled($daten['zahlungsfrist'] ?? null) ? Carbon::parse($daten['zahlungsfrist'])->startOfDay() : null;
            if (! $frist || $frist->lt(today())) {
                throw new InvalidArgumentException('Bitte eine Zahlungsfrist ab heute angeben.');
            }
        }

        return DB::transaction(function () use ($vorstellung, $stelle, $art, $mengen, $daten, $durch, $kategorien, $frist) {
            $vorstellung = Vorstellung::whereKey($vorstellung->id)->lockForUpdate()->firstOrFail();
            if ($vorstellung->kontingent === null) {
                throw new InvalidArgumentException('Für diese Vorstellung ist noch kein Kontingent festgelegt.');
            }
            $summe = array_sum($mengen);
            $frei = $vorstellung->kontingent - $this->kontingent->belegt($vorstellung);
            if ($summe > $frei) {
                throw new InvalidArgumentException('Nicht genug Plätze frei: '.$summe.' gewünscht, '.max(0, $frei).' frei.');
            }

            $nummer = (int) Reservierung::lockForUpdate()->max('nummer') + 1;
            $reservierung = Reservierung::create([
                'nummer' => $nummer,
                'vorstellung_id' => $vorstellung->id,
                'verkaufsstelle_id' => $stelle?->id,
                'art' => $art,
                'status' => 'offen',
                'name' => trim($daten['name']),
                'telefon' => $this->leer($daten['telefon'] ?? null),
                'strasse' => $art === 'vorkasse' ? $this->leer($daten['strasse'] ?? null) : null,
                'plz' => $art === 'vorkasse' ? $this->leer($daten['plz'] ?? null) : null,
                'ort' => $art === 'vorkasse' ? $this->leer($daten['ort'] ?? null) : null,
                'bemerkung' => $this->leer($daten['bemerkung'] ?? null),
                'zahlungsfrist' => $frist,
                'angelegt_von' => $durch->id,
            ]);
            foreach ($mengen as $kategorieId => $anzahl) {
                Reservierungsposition::create([
                    'reservierung_id' => $reservierung->id, 'kategorie_id' => $kategorieId, 'anzahl' => $anzahl,
                    'preis_cent' => $kategorien[$kategorieId]->preisFuer($vorstellung->id),
                ]);
            }

            if ($art === 'gedruckt') {
                // E40a: Karten entstehen sofort und gehen ohne Bestätigungsschritt an die Druckstelle.
                $this->druckauftrag($reservierung, $vorstellung, 'reservierung', [], $durch);
            }

            Protokoll::schreibe('ticket.reservierung_angelegt', $reservierung, [
                'nummer' => $reservierung->kennung(), 'art' => $art, 'anzahl' => $summe, 'stelle' => $stelle?->name,
            ], $durch);

            return $reservierung;
        });
    }

    /** Geldeingang vermerkt (E124, E125): Karten entstehen, gelten als per Überweisung bezahlt, Druckauftrag mit Adressaufkleber. */
    public function bezahlt(Reservierung $reservierung, User $durch): Reservierung
    {
        return DB::transaction(function () use ($reservierung, $durch) {
            $reservierung = $this->sperren($reservierung);
            if ($reservierung->art !== 'vorkasse' || $reservierung->status !== 'offen') {
                throw new InvalidArgumentException('Diese Reservierung wartet nicht auf eine Überweisung.');
            }
            $vorstellung = Vorstellung::whereKey($reservierung->vorstellung_id)->lockForUpdate()->firstOrFail();

            $reservierung->forceFill(['status' => 'bezahlt', 'zahlungsart' => 'ueberweisung', 'bezahlt_von' => $durch->id, 'bezahlt_am' => now()])->save();
            $this->druckauftrag($reservierung, $vorstellung, 'versand', [
                'zahlungsart' => 'ueberweisung', 'verkauft_am' => now(), 'verkauft_von' => $durch->id, 'verkauf_ueber' => 'vorkasse',
            ], $durch);

            Protokoll::schreibe('ticket.zahlung_eingegangen', $reservierung, [
                'nummer' => $reservierung->kennung(), 'betrag_cent' => $reservierung->betragCent(),
            ], $durch);

            return $reservierung;
        });
    }

    /** Nach dem Druck per Post verschickt: Karten gelten als verkauft (E124). */
    public function versendet(Reservierung $reservierung, User $durch): void
    {
        DB::transaction(function () use ($reservierung, $durch) {
            $reservierung = $this->sperren($reservierung);
            if ($reservierung->status !== 'bezahlt') {
                throw new InvalidArgumentException('Nur bezahlte Vorkasse-Reservierungen können versendet werden.');
            }
            if (Ticket::where('reservierung_id', $reservierung->id)->where('status', 'erzeugt')->exists()) {
                throw new InvalidArgumentException('Bitte die Karten zuerst drucken.');
            }
            Ticket::where('reservierung_id', $reservierung->id)->where('status', 'gedruckt')
                ->update(['status' => 'verkauft', 'ausgegeben_am' => now()]);
            $reservierung->forceFill(['status' => 'versendet', 'versendet_am' => now()])->save();
            $reservierung->kartenauftrag?->forceFill(['status' => 'ausgegeben', 'ausgegeben_am' => now()])->save();
            Protokoll::schreibe('ticket.reservierung_versendet', $reservierung, ['nummer' => $reservierung->kennung()], $durch);
        });
    }

    /** Vorkasse → Abholung an der Abendkasse (E126). */
    public function aufAbholungUmstellen(Reservierung $reservierung, User $durch): void
    {
        DB::transaction(function () use ($reservierung, $durch) {
            $reservierung = $this->sperren($reservierung);
            if ($reservierung->art !== 'vorkasse' || $reservierung->status !== 'offen') {
                throw new InvalidArgumentException('Nur offene Vorkasse-Reservierungen können umgestellt werden.');
            }
            $reservierung->forceFill(['art' => 'digital', 'zahlungsfrist' => null])->save();
            Protokoll::schreibe('ticket.reservierung_umgestellt', $reservierung, ['nummer' => $reservierung->kennung()], $durch);
        });
    }

    /** Offene Reservierung stornieren (E1-Ergänzung, E41): Plätze werden frei, hinterlegte Karten ungültig. */
    public function stornieren(Reservierung $reservierung, User $durch, string $grund = 'manuell'): void
    {
        DB::transaction(function () use ($reservierung, $durch, $grund) {
            $reservierung = $this->sperren($reservierung);
            if ($reservierung->status !== 'offen') {
                throw new InvalidArgumentException('Nur offene Reservierungen können storniert werden.');
            }
            $karten = Ticket::where('reservierung_id', $reservierung->id)->whereIn('status', ['erzeugt', 'gedruckt'])
                ->update(['status' => 'storniert', 'storniert_am' => now(), 'storniert_von' => $durch->id]);
            if ($auftrag = $reservierung->kartenauftrag) {
                $auftrag->forceFill(['status' => $auftrag->status === 'bestaetigt' ? 'zurueckgezogen' : $auftrag->status])->save();
            }
            $reservierung->forceFill(['status' => 'storniert', 'storniert_am' => now(), 'storniert_von' => $durch->id])->save();
            Protokoll::schreibe('ticket.reservierung_storniert', $reservierung, [
                'nummer' => $reservierung->kennung(), 'karten' => $karten, 'grund' => $grund,
            ], $durch);
        });
    }

    /**
     * Abholung an der Abendkasse (E114, E114a, E123), auch teilweise.
     *
     * @param  array<int, int>  $mengen  reservierungsposition_id => abgeholte Anzahl
     * @return array{anzahl: int, betrag_cent: int}
     */
    public function ausgeben(Reservierung $reservierung, array $mengen, string $zahlungsart, User $durch): array
    {
        if (! in_array($zahlungsart, ['bar', 'karte'], true)) {
            throw new InvalidArgumentException('Bitte die Zahlungsart wählen.');
        }

        return DB::transaction(function () use ($reservierung, $mengen, $zahlungsart, $durch) {
            $reservierung = $this->sperren($reservierung);
            if ($reservierung->status !== 'offen') {
                throw new InvalidArgumentException('Diese Reservierung wurde bereits bearbeitet.');
            }
            $vorstellung = Vorstellung::whereKey($reservierung->vorstellung_id)->lockForUpdate()->firstOrFail();
            $positionen = $reservierung->positionen()->with('kategorie')->get();

            $summe = 0;
            $betrag = 0;
            foreach ($positionen as $p) {
                $n = (int) ($mengen[$p->id] ?? $p->anzahl);
                if ($n < 0 || $n > $p->anzahl) {
                    throw new InvalidArgumentException('Es können höchstens die reservierten Karten ausgegeben werden.');
                }
                $p->anzahl_abgeholt = $n;
                $summe += $n;
                $betrag += $n * $p->preis_cent;
            }
            if ($summe === 0) {
                throw new InvalidArgumentException('Niemand da? Dann bitte die Reservierung stornieren.');
            }

            $verkauf = ['zahlungsart' => $zahlungsart, 'verkauft_am' => now(), 'verkauft_von' => $durch->id, 'verkauf_ueber' => 'reservierung'];

            if ($reservierung->art === 'gedruckt') {
                // Hinterlegte Karten: abgeholte → verkauft, übrige → storniert (Platz wird frei).
                foreach ($positionen as $p) {
                    $karten = Ticket::where('reservierung_id', $reservierung->id)->where('kategorie_id', $p->kategorie_id)
                        ->whereIn('status', ['erzeugt', 'gedruckt'])->orderBy('id')->lockForUpdate()->get();
                    foreach ($karten as $i => $karte) {
                        $karte->forceFill($i < $p->anzahl_abgeholt
                            ? array_merge($verkauf, ['status' => 'verkauft', 'ausgegeben_am' => now()])
                            : ['status' => 'storniert', 'storniert_am' => now(), 'storniert_von' => $durch->id])->save();
                    }
                }
                $reservierung->kartenauftrag?->forceFill(['status' => 'ausgegeben', 'ausgegeben_am' => now()])->save();
            } else {
                // Digital (auch umgestellte oder nicht bezahlte Vorkasse): Karten entstehen jetzt, sofort eingelassen (E44, E123).
                $reservierung->forceFill(['status' => 'abgeholt'])->save(); // Vormerkung zuerst lösen
                $zeilen = $positionen->map(fn ($p) => ['kategorie' => $p->kategorie, 'anzahl' => $p->anzahl_abgeholt, 'preis_cent' => $p->preis_cent])->all();
                $this->erzeugung->erzeugen($vorstellung, $zeilen, array_merge($verkauf, [
                    'status' => 'eingelassen', 'eingelassen_am' => now(), 'eingelassen_durch' => 'Abendkasse',
                    'name' => $reservierung->name, 'reservierung_id' => $reservierung->id,
                ]), true);
            }

            $positionen->each->save();
            $reservierung->forceFill(['status' => 'abgeholt', 'zahlungsart' => $zahlungsart, 'abgeholt_am' => now()])->save();
            Protokoll::schreibe('ticket.reservierung_abgeholt', $reservierung, [
                'nummer' => $reservierung->kennung(), 'abgeholt' => $summe, 'reserviert' => (int) $positionen->sum('anzahl'),
                'zahlungsart' => $zahlungsart, 'betrag_cent' => $betrag,
            ], $durch);

            return ['anzahl' => $summe, 'betrag_cent' => $betrag];
        });
    }

    /** Druckauftrag einer Reservierung ohne Bestätigungsschritt (Plätze sind bereits gesichert). */
    private function druckauftrag(Reservierung $reservierung, Vorstellung $vorstellung, string $art, array $verkauf, User $durch): Kartenauftrag
    {
        $positionen = $reservierung->positionen()->with('kategorie')->get();
        $auftrag = Kartenauftrag::create([
            'vorstellung_id' => $vorstellung->id,
            'verkaufsstelle_id' => null,
            'art' => $art,
            'status' => 'bestaetigt',
            'name' => $reservierung->name,
            'bemerkung' => $reservierung->kennung(),
            'angefordert_von' => $durch->id,
        ]);
        $auftrag->forceFill(['bestaetigt_von' => $durch->id, 'bestaetigt_am' => now()])->save();
        foreach ($positionen as $p) {
            Auftragsposition::create(['kartenauftrag_id' => $auftrag->id, 'kategorie_id' => $p->kategorie_id, 'anzahl' => $p->anzahl, 'anzahl_bestaetigt' => $p->anzahl]);
        }
        $zeilen = $positionen->map(fn ($p) => ['kategorie' => $p->kategorie, 'anzahl' => $p->anzahl, 'preis_cent' => $p->preis_cent])->all();
        $this->erzeugung->erzeugen($vorstellung, $zeilen, array_merge([
            'status' => 'erzeugt', 'name' => $reservierung->name, 'kartenauftrag_id' => $auftrag->id, 'reservierung_id' => $reservierung->id,
        ], $verkauf), true);

        $reservierung->forceFill(['kartenauftrag_id' => $auftrag->id])->save();

        return $auftrag;
    }

    private function sperren(Reservierung $reservierung): Reservierung
    {
        return Reservierung::whereKey($reservierung->id)->lockForUpdate()->firstOrFail();
    }

    private function leer(?string $wert): ?string
    {
        return filled($wert) ? trim($wert) : null;
    }
}
