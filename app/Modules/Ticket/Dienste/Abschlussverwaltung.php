<?php

namespace App\Modules\Ticket\Dienste;

use App\Models\User;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Models\Abschluss;
use App\Modules\Ticket\Models\Kartenauftrag;
use App\Modules\Ticket\Models\Reservierung;
use App\Modules\Ticket\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Abschluss je Vorstellung (E4, E4a, E4b, E131): Vorab-Prüfung offener Reste, Ein-Klick-Bereinigung,
 * Abschluss mit Sperre; Rückgängig nur durch den Super-Admin mit Pflichtgrund.
 */
class Abschlussverwaltung
{
    public function __construct(private Reservierungsverwaltung $reservierungen, private Auftragsverwaltung $auftraege) {}

    /**
     * Offene Reste einer Vorstellung.
     *
     * @return array{reservierungen: Collection, auftraege: Collection, karten: Collection, versand: Collection}
     */
    public function reste(Vorstellung $vorstellung): array
    {
        return [
            // nicht abgeholte bzw. unbezahlte Reservierungen
            'reservierungen' => Reservierung::with('positionen')->where('vorstellung_id', $vorstellung->id)->where('status', 'offen')->orderBy('name')->get(),
            // angeforderte, nie bestätigte Kartenaufträge
            'auftraege' => Kartenauftrag::with(['positionen', 'verkaufsstelle'])->where('vorstellung_id', $vorstellung->id)->where('status', 'angefordert')->get(),
            // Karten, die nie an Gäste oder Verkaufsstellen gingen: erzeugt/gedruckt ohne Bezahlung,
            // dazu der nicht verkaufte gedruckte Bestand der Abendkasse (E46)
            'karten' => Ticket::with(['kategorie', 'verkaufsstelle'])->where('vorstellung_id', $vorstellung->id)
                ->whereNull('reservierung_id')
                ->where(fn ($q) => $q->where(fn ($a) => $a->whereIn('status', ['erzeugt', 'gedruckt'])->whereNull('zahlungsart'))
                    ->orWhere(fn ($b) => $b->where('status', 'ausgegeben')->whereHas('verkaufsstelle', fn ($s) => $s->where('art', 'abendkasse'))))
                ->orderBy('nummer')->get(),
            // bezahlte Vorkasse, noch nicht versendet – muss erst gedruckt/versendet werden
            'versand' => Reservierung::where('vorstellung_id', $vorstellung->id)->where('status', 'bezahlt')->orderBy('name')->get(),
        ];
    }

    public function hatReste(Vorstellung $vorstellung): bool
    {
        return collect($this->reste($vorstellung))->contains(fn ($liste) => $liste->isNotEmpty());
    }

    /** Ein-Klick-Bereinigung einer Art von Resten (E131). Rückgabe: Anzahl. */
    public function bereinigen(Vorstellung $vorstellung, string $art, User $durch): int
    {
        Abschlusssperre::pruefen($vorstellung->id);
        $reste = $this->reste($vorstellung);

        return match ($art) {
            'reservierungen' => $reste['reservierungen']->each(fn ($r) => $this->reservierungen->stornieren($r, $durch, 'Abschluss: nicht abgeholt'))->count(),
            'auftraege' => $reste['auftraege']->each(fn ($a) => $this->auftraege->ablehnen($a, $durch))->count(),
            'karten' => $this->kartenStornieren($vorstellung, $reste['karten'], $durch),
            default => throw new InvalidArgumentException('Unbekannte Art.'),
        };
    }

    public function abschliessen(Vorstellung $vorstellung, User $durch): Abschluss
    {
        return DB::transaction(function () use ($vorstellung, $durch) {
            $vorstellung = Vorstellung::whereKey($vorstellung->id)->lockForUpdate()->firstOrFail();
            Abschlusssperre::pruefen($vorstellung->id);
            if ($this->hatReste($vorstellung)) {
                throw new InvalidArgumentException('Es sind noch offene Reste vorhanden – bitte zuerst bereinigen.');
            }

            // E4: Ausgegebene Vorverkaufskarten gelten jetzt als verkauft. Markiert, damit ein
            // Rückgängigmachen genau diese Karten zurücksetzen kann.
            $verkauft = Ticket::where('vorstellung_id', $vorstellung->id)->where('status', 'ausgegeben')
                ->update(['status' => 'verkauft', 'verkauft_am' => now(), 'verkauf_ueber' => 'abschluss']);

            $abschluss = Abschluss::create(['vorstellung_id' => $vorstellung->id, 'abgeschlossen_von' => $durch->id, 'abgeschlossen_am' => now()]);
            Protokoll::schreibe('ticket.abschluss', $vorstellung, ['vorstellung' => $vorstellung->anzeige(), 'als_verkauft' => $verkauft], $durch);

            return $abschluss;
        });
    }

    /** Nur der Super-Admin, nur mit Grund (E4a, E4b). */
    public function rueckgaengig(Vorstellung $vorstellung, string $grund, User $durch): void
    {
        if (! $durch->istSuperAdmin()) {
            throw new InvalidArgumentException('Nur der Super-Admin kann einen Abschluss rückgängig machen.');
        }
        if (mb_strlen(trim($grund)) < 5) {
            throw new InvalidArgumentException('Bitte einen Grund angeben (mindestens 5 Zeichen).');
        }

        DB::transaction(function () use ($vorstellung, $grund, $durch) {
            $abschluss = Abschluss::where('vorstellung_id', $vorstellung->id)->lockForUpdate()->first();
            if (! $abschluss) {
                throw new InvalidArgumentException('Diese Vorstellung ist nicht abgeschlossen.');
            }
            $zurueck = Ticket::where('vorstellung_id', $vorstellung->id)->where('status', 'verkauft')->where('verkauf_ueber', 'abschluss')
                ->update(['status' => 'ausgegeben', 'verkauft_am' => null, 'verkauf_ueber' => null]);
            $abschluss->delete();
            Protokoll::schreibe('ticket.abschluss_rueckgaengig', $vorstellung, [
                'vorstellung' => $vorstellung->anzeige(), 'grund' => trim($grund), 'zurueckgesetzt' => $zurueck,
                'abgeschlossen_am' => $abschluss->abgeschlossen_am->format('d.m.Y H:i'),
            ], $durch);
        });
    }

    /** Offene Abschlüsse vergangener Vorstellungen (E130). */
    public function offen(): Collection
    {
        $abgeschlossen = Abschluss::pluck('vorstellung_id');

        return Vorstellung::with('veranstaltung')->where('beginn_um', '<', now())->whereNotIn('id', $abgeschlossen)
            ->whereIn('id', Ticket::select('vorstellung_id')->distinct())
            ->orderBy('beginn_um')->get()->filter(fn ($v) => $v->istVorbei())->values();
    }

    private function kartenStornieren(Vorstellung $vorstellung, Collection $karten, User $durch): int
    {
        if ($karten->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($vorstellung, $karten, $durch) {
            $anzahl = Ticket::whereIn('id', $karten->pluck('id'))->whereIn('status', ['erzeugt', 'gedruckt', 'ausgegeben'])
                ->update(['status' => 'storniert', 'storniert_am' => now(), 'storniert_von' => $durch->id]);
            // Aufträge, deren Karten nun alle storniert sind, gelten als zurückgezogen (verschwinden aus „offen“).
            foreach ($karten->pluck('kartenauftrag_id')->filter()->unique() as $auftragId) {
                if (! Ticket::where('kartenauftrag_id', $auftragId)->where('status', '!=', 'storniert')->exists()) {
                    Kartenauftrag::whereKey($auftragId)->whereIn('status', ['bestaetigt', 'gedruckt'])->update(['status' => 'zurueckgezogen']);
                }
            }
            Protokoll::schreibe('ticket.storniert', $vorstellung, ['anzahl' => $anzahl, 'nummern' => $karten->pluck('nummer')->all(), 'grund' => 'Abschluss: Restbestand'], $durch);

            return $anzahl;
        });
    }
}
