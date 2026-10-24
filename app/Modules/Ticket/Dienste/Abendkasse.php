<?php

namespace App\Modules\Ticket\Dienste;

use App\Models\User;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Models\Kartenauftrag;
use App\Modules\Ticket\Models\Reservierung;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Models\Ticketeinstellung;
use App\Modules\Ticket\Models\Ticketkategorie;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Abendkasse (E32, E41, E42, E44, E46, E46a, E114, E121): Spontanverkauf, Verkauf aus dem
 * gedruckten Bestand, Ausgabe von Freikarten-Stapeln, nicht abgeholte Reservierungen.
 */
class Abendkasse
{
    public function __construct(private Kontingent $kontingent, private Kartenerzeugung $erzeugung) {}

    /**
     * Virtuelle Karten ohne Etikett, sofort eingelassen (E44).
     *
     * @param  array<int, int>  $mengen  kategorie_id => Anzahl
     * @return array{anzahl: int, betrag_cent: int, tickets: Collection<int, Ticket>}
     */
    public function spontanverkauf(Vorstellung $vorstellung, array $mengen, string $zahlungsart, User $durch): array
    {
        $this->zahlungsartPruefen($zahlungsart);
        $mengen = array_filter(array_map('intval', $mengen), fn ($n) => $n > 0);
        if ($mengen === []) {
            throw new InvalidArgumentException('Bitte bei mindestens einer Kategorie eine Anzahl eintragen.');
        }
        if ($vorstellung->istVorbei()) {
            throw new InvalidArgumentException('Diese Vorstellung ist bereits vorbei.');
        }
        $kategorien = Ticketkategorie::where('veranstaltung_id', $vorstellung->veranstaltung_id)->where('ist_freikarte', false)
            ->whereIn('id', array_keys($mengen))->orderBy('reihenfolge')->get();
        if ($kategorien->count() !== count($mengen)) {
            throw new InvalidArgumentException('Ungültige Kategorie.');
        }

        return DB::transaction(function () use ($vorstellung, $mengen, $zahlungsart, $durch, $kategorien) {
            $vorstellung = Vorstellung::whereKey($vorstellung->id)->lockForUpdate()->firstOrFail();
            if ($vorstellung->kontingent === null) {
                throw new InvalidArgumentException('Für diese Vorstellung ist noch kein Kontingent festgelegt.');
            }
            $summe = array_sum($mengen);
            $frei = $vorstellung->kontingent - $this->kontingent->belegt($vorstellung);
            if ($summe > $frei) {
                throw new InvalidArgumentException('Nicht genug Plätze frei: '.$summe.' gewünscht, '.max(0, $frei).' frei.');
            }

            $zeilen = $kategorien->map(fn ($k) => ['kategorie' => $k, 'anzahl' => $mengen[$k->id], 'preis_cent' => $k->preisFuer($vorstellung->id)])->all();
            $tickets = $this->erzeugung->erzeugen($vorstellung, $zeilen, [
                'status' => 'eingelassen', 'eingelassen_am' => now(), 'eingelassen_durch' => 'Abendkasse',
                'zahlungsart' => $zahlungsart, 'verkauft_am' => now(), 'verkauft_von' => $durch->id, 'verkauf_ueber' => 'abendkasse',
            ], false);
            $betrag = (int) $tickets->sum('preis_cent');

            Protokoll::schreibe('ticket.abendkasse_verkauf', $vorstellung, [
                'anzahl' => $summe, 'zahlungsart' => $zahlungsart, 'betrag_cent' => $betrag,
                'von' => $tickets->first()->nummer, 'bis' => $tickets->last()->nummer,
            ], $durch);

            return ['anzahl' => $summe, 'betrag_cent' => $betrag, 'tickets' => $tickets];
        });
    }

    /** Prüft eine gescannte Karte aus dem Abendkassen-Bestand (E46, E46a). Rückgabe: Fehlertext oder null. */
    public function bestandPruefen(Vorstellung $vorstellung, Ticket $ticket): ?string
    {
        if ($ticket->vorstellung_id !== $vorstellung->id) {
            return 'Diese Karte gilt für eine andere Vorstellung.';
        }
        if ($ticket->status !== 'ausgegeben' || $ticket->verkaufsstelle?->art !== 'abendkasse') {
            return match ($ticket->status) {
                'verkauft', 'eingelassen' => 'Diese Karte ist bereits verkauft.',
                'storniert' => 'Diese Karte ist storniert.',
                default => 'Diese Karte gehört nicht zum Bestand der Abendkasse.',
            };
        }

        return null;
    }

    public function bestandVerkaufen(Vorstellung $vorstellung, Ticket $ticket, string $zahlungsart, User $durch): Ticket
    {
        $this->zahlungsartPruefen($zahlungsart);

        return DB::transaction(function () use ($vorstellung, $ticket, $zahlungsart, $durch) {
            $ticket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if ($fehler = $this->bestandPruefen($vorstellung, $ticket)) {
                throw new InvalidArgumentException($fehler);
            }
            $ticket->forceFill([
                'status' => 'verkauft', 'zahlungsart' => $zahlungsart, 'verkauft_am' => now(), 'verkauft_von' => $durch->id, 'verkauf_ueber' => 'abendkasse',
            ])->save();
            Protokoll::schreibe('ticket.abendkasse_bestand', $ticket, ['nummer' => $ticket->nummer, 'zahlungsart' => $zahlungsart], $durch);

            return $ticket;
        });
    }

    /** Freikarten-Stapel des Vereins, die an der Abendkasse hinterlegt sind (E114, E114a). */
    public function hinterlegteFreikarten(Vorstellung $vorstellung): Collection
    {
        return Kartenauftrag::with('tickets')->where('vorstellung_id', $vorstellung->id)->where('art', 'freikarten')
            ->whereNull('verkaufsstelle_id')->whereIn('status', ['bestaetigt', 'gedruckt'])->orderBy('name')->get();
    }

    public function freikartenAusgeben(Kartenauftrag $auftrag, int $anzahl, User $durch): int
    {
        return DB::transaction(function () use ($auftrag, $anzahl, $durch) {
            $auftrag = Kartenauftrag::whereKey($auftrag->id)->lockForUpdate()->firstOrFail();
            if ($auftrag->art !== 'freikarten' || $auftrag->verkaufsstelle_id !== null || ! in_array($auftrag->status, ['bestaetigt', 'gedruckt'], true)) {
                throw new InvalidArgumentException('Dieser Freikarten-Stapel ist nicht an der Abendkasse hinterlegt.');
            }
            $karten = Ticket::where('kartenauftrag_id', $auftrag->id)->whereIn('status', ['erzeugt', 'gedruckt'])->orderBy('id')->lockForUpdate()->get();
            if ($anzahl < 1 || $anzahl > $karten->count()) {
                throw new InvalidArgumentException('Bitte eine Anzahl zwischen 1 und '.$karten->count().' angeben.');
            }
            foreach ($karten as $i => $karte) {
                $karte->forceFill($i < $anzahl
                    ? ['status' => 'ausgegeben', 'ausgegeben_am' => now()]
                    : ['status' => 'storniert', 'storniert_am' => now(), 'storniert_von' => $durch->id])->save();
            }
            $auftrag->forceFill(['status' => 'ausgegeben', 'ausgegeben_am' => now()])->save();
            Protokoll::schreibe('ticket.freikarten_ausgegeben', $auftrag, ['name' => $auftrag->name, 'ausgegeben' => $anzahl, 'hinterlegt' => $karten->count()], $durch);

            return $anzahl;
        });
    }

    /** Offene Reservierungen der Vorstellung zur Abholung (alle Arten). */
    public function offeneReservierungen(Vorstellung $vorstellung, ?string $suche = null): Collection
    {
        return Reservierung::with(['positionen.kategorie', 'tickets'])->where('vorstellung_id', $vorstellung->id)->where('status', 'offen')
            ->when(filled($suche), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$suche.'%')->orWhere('nummer', (int) ltrim((string) preg_replace('/\D/', '', (string) $suche), '0'))))
            ->orderBy('name')->get();
    }

    /** Ab wann erscheinen nicht abgeholte Reservierungen (E41, E121)? */
    public function nichtAbgeholtAb(Vorstellung $vorstellung): Carbon
    {
        return $vorstellung->beginn_um->copy()->addMinutes(Ticketeinstellung::aktuell()->nicht_abgeholt_minuten);
    }

    private function zahlungsartPruefen(string $zahlungsart): void
    {
        if (! in_array($zahlungsart, ['bar', 'karte'], true)) {
            throw new InvalidArgumentException('Bitte die Zahlungsart wählen (bar oder Karte).');
        }
    }
}
