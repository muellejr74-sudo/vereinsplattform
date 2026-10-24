<?php

namespace App\Modules\Ticket\Dienste;

use App\Modules\Ticket\Models\Ticket;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use FPDF;
use Illuminate\Support\Collection;

/**
 * Ticket-Etiketten als PDF – eine Seite je Etikett (E111) für den Labelident BP4X
 * mit 101,6 × 38,1 mm (E110). Inhalt nach E77, Stapelposition nach E114b.
 * Nur Schwarz (Thermodirekt), kein Logo.
 */
class EtikettPdf
{
    public const BREITE = 101.6;

    public const HOEHE = 38.1;

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @param  list<string>|null  $anschrift  Zeilen für den Adressaufkleber vor den Karten (E124)
     */
    public function erzeuge(Collection $tickets, float $breite = self::BREITE, float $hoehe = self::HOEHE, ?array $anschrift = null, ?string $absender = null): string
    {
        $pdf = new FPDF($breite >= $hoehe ? 'L' : 'P', 'mm', [min($breite, $hoehe), max($breite, $hoehe)]);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetCreator('Vereinsplattform');
        $pdf->SetTitle('Eintrittskarten');

        if ($anschrift) {
            $pdf->AddPage();
            $this->adressaufkleber($pdf, $anschrift, (string) $absender, $breite, $hoehe);
        }

        foreach ($tickets as $ticket) {
            $pdf->AddPage();
            $this->etikett($pdf, $ticket, $breite, $hoehe);
        }

        return $pdf->Output('S');
    }

    private function etikett(FPDF $pdf, Ticket $ticket, float $b, float $h): void
    {
        $rand = 2.5;
        $qr = $h - 2 * $rand;                       // QR-Code rechts, volle Höhe
        $qrX = $b - $rand - $qr;
        $this->qr($pdf, $ticket->qrInhalt(), $qrX, $rand, $qr);

        $textBreite = $qrX - $rand - 2;
        $x = $rand + 0.5;
        $y = $rand + 0.5;
        $vorstellung = $ticket->vorstellung;
        $veranstaltung = $vorstellung->veranstaltung;

        $pdf->SetTextColor(0, 0, 0);

        // 1. Kurzer Veranstaltungsname
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->SetXY($x, $y);
        $pdf->Cell($textBreite, 5, $this->kuerzen($pdf, $veranstaltung->kurzname ?: $veranstaltung->name, $textBreite));

        // 2. Datum und Uhrzeit
        $pdf->SetFont('Helvetica', '', 9.5);
        $zeileY = $y + 5.6;
        $pdf->SetXY($x, $zeileY);
        $pdf->Cell($textBreite, 4.5, $this->text($vorstellung->anzeige()));

        // 3. Einlass ab … (E77, Wunsch des Betreibers 24.10.2026) – nur wenn hinterlegt
        if ($vorstellung->einlass_um) {
            $zeileY += 4.4;
            $pdf->SetFont('Helvetica', '', 8.5);
            $pdf->SetXY($x, $zeileY);
            $pdf->Cell($textBreite, 4.5, $this->text('Einlass ab '.$vorstellung->einlass_um->format('H:i').' Uhr'));
        }

        // 4. Kategorie · Preis bzw. „Freikarte“
        $zeileY += 4.6;
        $pdf->SetFont('Helvetica', '', 9.5);
        $pdf->SetXY($x, $zeileY);
        $zeile = $ticket->ist_freikarte ? 'Freikarte' : $ticket->kategorie->name.' · '.Geld::anzeigen($ticket->preis_cent);
        $pdf->Cell($textBreite, 4.5, $this->kuerzen($pdf, $zeile, $textBreite));

        // 5. Name (Reservierung/Freikarte) mit Stapelposition – ohne „Name:“
        if ($ticket->name) {
            $zeileY += 4.6;
            $pdf->SetFont('Helvetica', '', 8.5);
            $pdf->SetXY($x, $zeileY);
            $name = $ticket->name.($ticket->stapelText() ? ' · '.$ticket->stapelText() : '');
            $pdf->Cell($textBreite, 4.5, $this->kuerzen($pdf, $name, $textBreite));
        }

        // 6. Ticketnummer
        $pdf->SetFont('Courier', 'B', 11);
        $pdf->SetXY($x, $h - $rand - 5.5);
        $pdf->Cell($textBreite, 5, $ticket->nummer);
    }

    /** Adressaufkleber im Etikettenformat: kleine Absenderzeile, darunter die Anschrift (E124). */
    private function adressaufkleber(FPDF $pdf, array $anschrift, string $absender, float $b, float $h): void
    {
        $rand = 4.0;
        $breite = $b - 2 * $rand;
        $pdf->SetTextColor(0, 0, 0);

        if ($absender !== '') {
            $pdf->SetFont('Helvetica', '', 6.5);
            $pdf->SetXY($rand, 3);
            $pdf->Cell($breite, 3, $this->kuerzen($pdf, 'Abs.: '.$absender, $breite));
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.15);
            $pdf->Line($rand, 6.6, $b - $rand, 6.6);
        }

        $zeilen = array_slice(array_values(array_filter($anschrift)), 0, 4);
        $groesse = count($zeilen) > 3 ? 10.5 : 12;
        $zeilenhoehe = $groesse * 0.45;
        $y = 9 + max(0, (($h - 11) - count($zeilen) * $zeilenhoehe) / 2);
        foreach ($zeilen as $i => $zeile) {
            $pdf->SetFont('Helvetica', $i === 0 ? 'B' : '', $groesse);
            $pdf->SetXY($rand + 4, $y);
            $pdf->Cell($breite - 4, $zeilenhoehe, $this->kuerzen($pdf, $zeile, $breite - 4));
            $y += $zeilenhoehe + 0.6;
        }
    }

    private function qr(FPDF $pdf, string $inhalt, float $x, float $y, float $groesse): void
    {
        $matrix = Encoder::encode($inhalt, ErrorCorrectionLevel::M())->getMatrix();
        $anzahl = $matrix->getWidth();
        $ruhezone = 2;
        $modul = $groesse / ($anzahl + 2 * $ruhezone);

        $pdf->SetFillColor(0, 0, 0);
        for ($zeile = 0; $zeile < $anzahl; $zeile++) {
            $start = null;
            for ($spalte = 0; $spalte <= $anzahl; $spalte++) {
                $dunkel = $spalte < $anzahl && $matrix->get($spalte, $zeile) === 1;
                if ($dunkel && $start === null) {
                    $start = $spalte;
                } elseif (! $dunkel && $start !== null) {
                    $pdf->Rect($x + ($ruhezone + $start) * $modul, $y + ($ruhezone + $zeile) * $modul, ($spalte - $start) * $modul, $modul + 0.02, 'F');
                    $start = null;
                }
            }
        }
    }

    private function kuerzen(FPDF $pdf, string $text, float $breite): string
    {
        $text = $this->text($text);
        if ($pdf->GetStringWidth($text) <= $breite) {
            return $text;
        }
        while (strlen($text) > 1 && $pdf->GetStringWidth($text.chr(133)) > $breite) {
            $text = substr($text, 0, -1);
        }

        return rtrim($text).chr(133); // „…“ in Windows-1252
    }

    private function text(string $text): string
    {
        return (string) iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
    }
}
