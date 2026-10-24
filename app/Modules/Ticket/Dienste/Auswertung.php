<?php

namespace App\Modules\Ticket\Dienste;

use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Models\Abschluss;
use App\Modules\Ticket\Models\Reservierung;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Models\Verkaufsstelle;
use Illuminate\Support\Collection;

/**
 * Ticket-Auswertungen je Vorstellung (E79): Übersicht, Einnahmen nach Kategorie und Zahlungsart,
 * Abrechnung je Vorverkaufsstelle, Freikartenliste. Grundlage für Bildschirm, PDF und CSV.
 */
class Auswertung
{
    public const WEGE = [
        'vorverkauf' => 'Vorverkaufsstellen',
        'abendkasse' => 'Abendkasse',
        'reservierung' => 'Reservierung (Abholung)',
        'vorkasse' => 'Vorkasse (Überweisung)',
        'nachbuchung' => 'Nachbuchung',
        'freikarte' => 'Freikarten',
    ];

    public const ZAHLUNGSARTEN = [
        'bar' => 'bar',
        'karte' => 'Karte/EC',
        'ueberweisung' => 'Überweisung',
        'stellen' => 'Abrechnung Vorverkaufsstellen',
    ];

    public function __construct(private Kontingent $kontingent) {}

    /** Alle Karten der Vorstellung mit allem, was die Auswertung braucht. */
    public function karten(Vorstellung $vorstellung): Collection
    {
        return Ticket::with(['kategorie', 'verkaufsstelle', 'reservierung'])->where('vorstellung_id', $vorstellung->id)->orderBy('nummer')->get();
    }

    /** Zählt die Karte als verkauft bzw. abzurechnen? (nicht storniert, an Gäste oder Stellen gegangen oder bezahlt) */
    public function zaehlt(Ticket $t): bool
    {
        if ($t->status === 'storniert') {
            return false;
        }
        // Nicht verkaufter Bestand der Abendkasse zählt nicht (E46).
        if ($t->verkaufsstelle?->art === 'abendkasse' && $t->zahlungsart === null) {
            return false;
        }

        return in_array($t->status, ['ausgegeben', 'verkauft', 'eingelassen'], true) || $t->zahlungsart !== null;
    }

    public function weg(Ticket $t): string
    {
        if ($t->ist_freikarte) {
            return 'freikarte';
        }
        if (in_array($t->verkauf_ueber, ['abendkasse', 'reservierung', 'vorkasse', 'nachbuchung'], true)) {
            return $t->verkauf_ueber;
        }

        return $t->verkaufsstelle?->art === 'abendkasse' ? 'abendkasse' : 'vorverkauf';
    }

    public function zahlungsart(Ticket $t): ?string
    {
        if ($t->ist_freikarte) {
            return null;
        }

        return $t->zahlungsart ?? ($this->weg($t) === 'vorverkauf' ? 'stellen' : null);
    }

    /** @return array<string, mixed> */
    public function zahlen(Vorstellung $vorstellung): array
    {
        $karten = $this->karten($vorstellung);
        $zaehlend = $karten->filter(fn ($t) => $this->zaehlt($t));
        $bezahlt = $zaehlend->reject(fn ($t) => $t->ist_freikarte);

        $wege = collect(self::WEGE)->map(fn ($text, $weg) => $zaehlend->filter(fn ($t) => $this->weg($t) === $weg)->count());
        $zahlungsarten = collect(self::ZAHLUNGSARTEN)->map(fn ($text, $art) => [
            'anzahl' => $bezahlt->filter(fn ($t) => $this->zahlungsart($t) === $art)->count(),
            'betrag' => (int) $bezahlt->filter(fn ($t) => $this->zahlungsart($t) === $art)->sum('preis_cent'),
        ]);
        $kategorien = $bezahlt->groupBy(fn ($t) => $t->kategorie->name.'|'.$t->preis_cent)->map(fn ($gruppe, $schluessel) => [
            'name' => explode('|', $schluessel)[0], 'preis' => (int) explode('|', $schluessel)[1],
            'anzahl' => $gruppe->count(), 'betrag' => (int) $gruppe->sum('preis_cent'),
        ])->sortBy('name')->values();

        $reservierungen = Reservierung::with('positionen')->where('vorstellung_id', $vorstellung->id)->get();

        return [
            'kontingent' => $this->kontingent->uebersicht($vorstellung),
            'status' => collect(array_keys(Ticket::STATUS))->mapWithKeys(fn ($s) => [$s => $karten->where('status', $s)->count()]),
            'wege' => $wege,
            'zahlungsarten' => $zahlungsarten,
            'kategorien' => $kategorien,
            'einnahmen' => (int) $bezahlt->sum('preis_cent'),
            'freikarten' => $zaehlend->where('ist_freikarte', true)->count(),
            'storniert' => $karten->where('status', 'storniert')->count(),
            'eingelassen' => $karten->where('status', 'eingelassen')->count(),
            'reservierungen' => [
                'offen' => $reservierungen->where('status', 'offen')->count(),
                'abgeholt' => $reservierungen->whereIn('status', ['abgeholt', 'versendet', 'bezahlt'])->count(),
                'storniert' => $reservierungen->where('status', 'storniert')->count(),
            ],
            'abschluss' => Abschluss::with('durch')->where('vorstellung_id', $vorstellung->id)->first(),
        ];
    }

    /**
     * Abrechnung je Vorverkaufsstelle: ausgegeben – zurückgegeben (storniert) = verkauft (E4, E79).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function abrechnung(Vorstellung $vorstellung): Collection
    {
        $karten = $this->karten($vorstellung)->filter(fn ($t) => $t->verkaufsstelle?->art === 'vorverkauf' && $t->ausgegeben_am !== null);

        return $karten->groupBy('verkaufsstelle_id')->map(function ($gruppe) {
            $stelle = $gruppe->first()->verkaufsstelle;
            $zurueck = $gruppe->where('status', 'storniert');
            $verkauft = $gruppe->where('status', '!=', 'storniert');
            $bezahlt = $verkauft->where('ist_freikarte', false);

            return [
                'stelle' => $stelle,
                'ausgegeben' => $gruppe->where('ist_freikarte', false)->count(),
                'zurueck' => $zurueck->where('ist_freikarte', false)->count(),
                'verkauft' => $bezahlt->count(),
                'betrag' => (int) $bezahlt->sum('preis_cent'),
                'freikarten' => $verkauft->where('ist_freikarte', true)->count(),
                'kategorien' => $bezahlt->groupBy(fn ($t) => $t->kategorie->name.'|'.$t->preis_cent)->map(fn ($g, $k) => [
                    'name' => explode('|', $k)[0], 'preis' => (int) explode('|', $k)[1], 'anzahl' => $g->count(), 'betrag' => (int) $g->sum('preis_cent'),
                ])->sortBy('name')->values(),
            ];
        })->sortBy(fn ($z) => $z['stelle']->name)->values();
    }

    /** Freikarten mit Namen (E79): je Name/Stelle Anzahl und eingelassen. */
    public function freikartenliste(Vorstellung $vorstellung): Collection
    {
        return $this->karten($vorstellung)->filter(fn ($t) => $t->ist_freikarte && $this->zaehlt($t))
            ->groupBy(fn ($t) => ($t->name ?? '–').'|'.($t->verkaufsstelle?->name ?? 'Verein (intern)'))
            ->map(fn ($g, $k) => [
                'name' => explode('|', $k)[0], 'stelle' => explode('|', $k)[1],
                'anzahl' => $g->count(), 'eingelassen' => $g->where('status', 'eingelassen')->count(),
            ])->sortBy('name')->values();
    }

    public function stelle(int $id): ?Verkaufsstelle
    {
        return Verkaufsstelle::find($id);
    }
}
