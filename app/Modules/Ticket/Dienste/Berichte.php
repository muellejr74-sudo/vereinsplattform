<?php

namespace App\Modules\Ticket\Dienste;

use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Models\Reservierung;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Models\Verkaufsstelle;

/** Abschlussbericht, Abrechnung je Stelle (PDF), CSV-Listen (E79c) und Notfallliste (E2a). */
class Berichte
{
    public function __construct(private Auswertung $auswertung, private Mandantenkontext $kontext) {}

    private function neu(string $titel, Vorstellung $v): BerichtPdf
    {
        $pdf = new BerichtPdf($titel);
        $pdf->kopfLinks = (string) $this->kontext->mandant()?->name;
        $pdf->kopfRechts = $v->veranstaltung->name.' · '.$v->anzeige();
        $pdf->AddPage();

        return $pdf;
    }

    private function g(int $cent): string
    {
        return Geld::anzeigen($cent);
    }

    public function abschlussbericht(Vorstellung $v): string
    {
        $z = $this->auswertung->zahlen($v);
        $pdf = $this->neu('Abschlussbericht', $v);
        $stand = $z['abschluss']
            ? 'Abgeschlossen am '.$z['abschluss']->abgeschlossen_am->format('d.m.Y H:i').' von '.($z['abschluss']->durch?->name ?? '–')
            : 'Noch nicht abgeschlossen – vorläufiger Stand';
        $pdf->titel('Abschlussbericht', $v->veranstaltung->name."\n".$v->anzeige()."\n".$stand);

        $k = $z['kontingent'];
        $pdf->abschnitt('Übersicht');
        $pdf->tabelle([['', 90], ['Anzahl', 30, 'R']], [
            ['Kontingent', (string) ($k['kontingent'] ?? '–')],
            ['Belegt', (string) $k['belegt']],
            ['Frei', (string) ($k['frei'] ?? '–')],
            ['Eingelassen', (string) $z['eingelassen']],
            ['Freikarten', (string) $z['freikarten']],
            ['Storniert', (string) $z['storniert']],
            ['Reservierungen abgeholt / storniert / offen', $z['reservierungen']['abgeholt'].' / '.$z['reservierungen']['storniert'].' / '.$z['reservierungen']['offen']],
        ]);

        $pdf->abschnitt('Verkauf nach Weg');
        $pdf->tabelle([['Weg', 90], ['Karten', 30, 'R']],
            collect(Auswertung::WEGE)->map(fn ($text, $weg) => [$text, (string) $z['wege'][$weg]])->values()->all());

        $pdf->abschnitt('Einnahmen nach Zahlungsart');
        $pdf->tabelle([['Zahlungsart', 90], ['Karten', 30, 'R'], ['Betrag', 40, 'R']],
            collect(Auswertung::ZAHLUNGSARTEN)->map(fn ($text, $art) => [$text, (string) $z['zahlungsarten'][$art]['anzahl'], $this->g($z['zahlungsarten'][$art]['betrag'])])->values()->all(),
            ['Gesamt', (string) collect($z['zahlungsarten'])->sum('anzahl'), $this->g($z['einnahmen'])]);
        $pdf->absatz('„Bar“ ist der Betrag, der in der Kasse sein muss (Abendkasse, Abholung, Nachbuchung). „Abrechnung Vorverkaufsstellen“ zahlen die Stellen an den Verein.', 8);

        $pdf->abschnitt('Einnahmen nach Kategorie');
        $pdf->tabelle([['Kategorie', 70], ['Preis', 30, 'R'], ['Karten', 30, 'R'], ['Betrag', 40, 'R']],
            $z['kategorien']->map(fn ($r) => [$r['name'], $this->g($r['preis']), (string) $r['anzahl'], $this->g($r['betrag'])])->all(),
            ['Gesamt', '', (string) $z['kategorien']->sum('anzahl'), $this->g($z['einnahmen'])]);

        $abrechnung = $this->auswertung->abrechnung($v);
        if ($abrechnung->isNotEmpty()) {
            $pdf->abschnitt('Abrechnung je Vorverkaufsstelle');
            $pdf->tabelle([['Stelle', 60], ['Ausgegeben', 25, 'R'], ['Zurück', 22, 'R'], ['Verkauft', 22, 'R'], ['Freik.', 18, 'R'], ['Betrag', 33, 'R']],
                $abrechnung->map(fn ($a) => [$a['stelle']->name, (string) $a['ausgegeben'], (string) $a['zurueck'], (string) $a['verkauft'], (string) $a['freikarten'], $this->g($a['betrag'])])->all(),
                ['Gesamt', (string) $abrechnung->sum('ausgegeben'), (string) $abrechnung->sum('zurueck'), (string) $abrechnung->sum('verkauft'), (string) $abrechnung->sum('freikarten'), $this->g($abrechnung->sum('betrag'))]);
        }

        $freikarten = $this->auswertung->freikartenliste($v);
        if ($freikarten->isNotEmpty()) {
            $pdf->abschnitt('Freikarten');
            $pdf->tabelle([['Name', 70], ['Über', 55], ['Anzahl', 25, 'R'], ['Eingelassen', 30, 'R']],
                $freikarten->map(fn ($f) => [$f['name'], $f['stelle'], (string) $f['anzahl'], (string) $f['eingelassen']])->all(),
                ['Gesamt', '', (string) $freikarten->sum('anzahl'), (string) $freikarten->sum('eingelassen')]);
        }

        return $pdf->ausgabe();
    }

    public function abrechnungStelle(Vorstellung $v, Verkaufsstelle $stelle): ?string
    {
        $a = $this->auswertung->abrechnung($v)->first(fn ($z) => $z['stelle']->id === $stelle->id);
        if (! $a) {
            return null;
        }
        $pdf = $this->neu('Abrechnung '.$stelle->name, $v);
        $pdf->titel('Abrechnung '.$stelle->name, $v->veranstaltung->name."\n".$v->anzeige());
        $pdf->tabelle([['', 100], ['Karten', 30, 'R']], [
            ['Erhalten (ausgegeben)', (string) $a['ausgegeben']],
            ['Zurückgegeben (storniert)', (string) $a['zurueck']],
            ['Verkauft', (string) $a['verkauft']],
            ['Freikarten (0 €)', (string) $a['freikarten']],
        ]);
        $pdf->abschnitt('Zu zahlen an den Verein');
        $pdf->tabelle([['Kategorie', 70], ['Preis', 30, 'R'], ['Karten', 30, 'R'], ['Betrag', 40, 'R']],
            $a['kategorien']->map(fn ($r) => [$r['name'], $this->g($r['preis']), (string) $r['anzahl'], $this->g($r['betrag'])])->all(),
            ['Gesamt', '', (string) $a['verkauft'], $this->g($a['betrag'])]);
        $pdf->Ln(14);
        $pdf->absatz('______________________________          ______________________________');
        $pdf->absatz('Datum, Unterschrift Verkaufsstelle                       Datum, Unterschrift Verein');

        return $pdf->ausgabe();
    }

    /** Notfallliste bei Internetausfall (E2a): gültige Karten und offene Reservierungen – ohne Telefonnummern. */
    public function notfallliste(Vorstellung $v): string
    {
        $pdf = $this->neu('Notfallliste', $v);
        $pdf->titel('Notfallliste Abendkasse / Einlass', $v->veranstaltung->name."\n".$v->anzeige()."\nStand: ".now()->format('d.m.Y H:i').' – nur für den Notfall (Internetausfall).');

        $reservierungen = Reservierung::with('positionen.kategorie')->where('vorstellung_id', $v->id)->where('status', 'offen')->orderBy('name')->get();
        $pdf->abschnitt('Offene Reservierungen ('.$reservierungen->count().')');
        $pdf->tabelle([['Abgeholt', 16], ['Name', 58], ['Nr.', 20], ['Karten', 52], ['Betrag', 24, 'R']],
            $reservierungen->map(fn ($r) => ['[   ]', $r->name, $r->kennung(),
                $r->positionen->map(fn ($p) => $p->anzahl.'× '.$p->kategorie->name)->join(', ').($r->art === 'vorkasse' ? ' (Vorkasse offen)' : ''),
                $this->g($r->betragCent())])->all());

        $karten = Ticket::with('kategorie')->where('vorstellung_id', $v->id)->whereIn('status', ['gedruckt', 'ausgegeben', 'verkauft', 'eingelassen'])->orderBy('nummer')->get();
        $pdf->abschnitt('Gültige Karten ('.$karten->count().')');
        $pdf->absatz('Bei Internetausfall: Kartennummer suchen und abhaken. Bereits eingelassene sind mit „E“ markiert.', 8);
        $zeilen = $karten->map(fn ($t) => ['[   ]', $t->nummer, $t->ist_freikarte ? 'Freikarte' : $t->kategorie->name, $t->status === 'eingelassen' ? 'E' : ''])->all();
        $pdf->tabelle([['Einlass', 16], ['Nummer', 40], ['Kategorie', 50], ['', 10]], $zeilen);

        return $pdf->ausgabe();
    }

    /** CSV für Excel: Semikolon, UTF-8 mit BOM (E79c). */
    public function csv(Vorstellung $v, string $liste): string
    {
        $zeilen = match ($liste) {
            'karten' => array_merge([['Nummer', 'Kategorie', 'Preis', 'Freikarte', 'Status', 'Weg', 'Zahlungsart', 'Verkaufsstelle', 'Name', 'Verkauft am', 'Eingelassen am', 'Storniert am']],
                $this->auswertung->karten($v)->map(fn ($t) => [
                    $t->nummer, $t->kategorie->name, $this->dezimal($t->preis_cent), $t->ist_freikarte ? 'ja' : 'nein', Ticket::STATUS[$t->status] ?? $t->status,
                    Auswertung::WEGE[$this->auswertung->weg($t)] ?? '', Auswertung::ZAHLUNGSARTEN[$this->auswertung->zahlungsart($t) ?? ''] ?? '',
                    $t->verkaufsstelle?->name ?? '', $t->name ?? '', $t->verkauft_am?->format('d.m.Y H:i') ?? '',
                    $t->eingelassen_am?->format('d.m.Y H:i') ?? '', $t->storniert_am?->format('d.m.Y H:i') ?? '',
                ])->all()),
            'freikarten' => array_merge([['Name', 'Über', 'Anzahl', 'Eingelassen']],
                $this->auswertung->freikartenliste($v)->map(fn ($f) => [$f['name'], $f['stelle'], $f['anzahl'], $f['eingelassen']])->all()),
            'abrechnung' => array_merge([['Verkaufsstelle', 'Ausgegeben', 'Zurückgegeben', 'Verkauft', 'Freikarten', 'Betrag']],
                $this->auswertung->abrechnung($v)->map(fn ($a) => [$a['stelle']->name, $a['ausgegeben'], $a['zurueck'], $a['verkauft'], $a['freikarten'], $this->dezimal($a['betrag'])])->all()),
            default => null,
        };
        abort_if($zeilen === null, 404);

        $griff = fopen('php://temp', 'r+');
        fwrite($griff, "\xEF\xBB\xBF");
        foreach ($zeilen as $zeile) {
            fputcsv($griff, array_map(fn ($w) => $this->sicher((string) $w), $zeile), ';', '"', '');
        }
        rewind($griff);

        return (string) stream_get_contents($griff);
    }

    private function dezimal(int $cent): string
    {
        return number_format($cent / 100, 2, ',', '');
    }

    /** Schutz vor Formeln in Excel (Werte, die mit = + - @ beginnen). */
    private function sicher(string $wert): string
    {
        return $wert !== '' && in_array($wert[0], ['=', '+', '-', '@'], true) ? "'".$wert : $wert;
    }
}
