<?php

namespace App\Modules\Kern\Dienste;

use App\Modules\Kern\Models\Tischcode;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use FPDF;
use Illuminate\Support\Collection;

/**
 * QR-Aufkleber als A4-Bogen (E104): Spalten, Zeilen, Ränder und Abstände einstellbar.
 * Unter jedem Code bleibt ein Freifeld für die handschriftliche Tischnummer (E54).
 *
 * Der QR-Code wird als Vektorgrafik (Rechtecke) gezeichnet – gestochen scharf in jeder Größe,
 * ohne Bildbearbeitung auf dem Server.
 */
class AufkleberPdf
{
    public const STANDARD = [
        'spalten' => 3,
        'zeilen' => 7,
        'rand_oben' => 10.0,
        'rand_links' => 7.0,
        'abstand_waagerecht' => 2.5,
        'abstand_senkrecht' => 0.0,
        'start' => 1,
        'rahmen' => true,
    ];

    private const BREITE = 210.0;

    private const HOEHE = 297.0;

    /**
     * @param  Collection<int, Tischcode>  $codes
     * @param  array<string, mixed>  $einstellungen
     */
    public function erzeuge(Collection $codes, array $einstellungen, string $kopf): string
    {
        $e = array_merge(self::STANDARD, $einstellungen);
        $spalten = max(1, min(6, (int) $e['spalten']));
        $zeilen = max(1, min(12, (int) $e['zeilen']));
        $proSeite = $spalten * $zeilen;

        $breite = (self::BREITE - 2 * $e['rand_links'] - ($spalten - 1) * $e['abstand_waagerecht']) / $spalten;
        $hoehe = (self::HOEHE - 2 * $e['rand_oben'] - ($zeilen - 1) * $e['abstand_senkrecht']) / $zeilen;

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetTitle($this->text($kopf));
        $pdf->SetCreator('Vereinsplattform');

        $platz = max(0, min($proSeite - 1, (int) $e['start'] - 1)); // bereits benutzte Aufkleber überspringen
        $pdf->AddPage();

        foreach ($codes->values() as $code) {
            if ($platz >= $proSeite) {
                $pdf->AddPage();
                $platz = 0;
            }

            $x = $e['rand_links'] + ($platz % $spalten) * ($breite + $e['abstand_waagerecht']);
            $y = $e['rand_oben'] + intdiv($platz, $spalten) * ($hoehe + $e['abstand_senkrecht']);
            $this->aufkleber($pdf, $code, $x, $y, $breite, $hoehe, (bool) $e['rahmen']);
            $platz++;
        }

        return $pdf->Output('S');
    }

    private function aufkleber(FPDF $pdf, Tischcode $code, float $x, float $y, float $b, float $h, bool $rahmen): void
    {
        if ($rahmen) {
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->SetLineWidth(0.1);
            $pdf->Rect($x, $y, $b, $h);
        }

        $innen = 2.0;
        $textHoehe = min(12.0, $h * 0.32);          // unten: Freifeld + Bezeichnung
        $qr = min($b - 2 * $innen, $h - $textHoehe - 2 * $innen);
        $qrX = $x + ($b - $qr) / 2;
        $qrY = $y + $innen;
        $this->qrZeichnen($pdf, $code->adresse(), $qrX, $qrY, $qr);

        $unten = $qrY + $qr + 1;
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetXY($x + $innen, $unten);

        if ($code->einheitscode) {
            $pdf->Cell($b - 2 * $innen, 4.5, $this->text('Einheitscode – alle Tische'), 0, 2, 'C');
        } else {
            // Freifeld für die Tischnummer (E54)
            $pdf->Cell(14, 4.5, $this->text('Tisch-Nr.:'), 0, 0, 'L');
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.3);
            $pdf->Line($x + $innen + 15, $unten + 4, $x + $b - $innen, $unten + 4);
            $pdf->SetXY($x + $innen, $unten + 4.5);
        }

        $pdf->SetFont('Helvetica', '', 6);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->Cell($b - 2 * $innen, 3, $this->text($code->bezeichnung.' · '.$code->code), 0, 0, 'C');
    }

    private function qrZeichnen(FPDF $pdf, string $inhalt, float $x, float $y, float $groesse): void
    {
        // Ohne UTF-8-Kennung: Links sind reiner ASCII-Text, ältere Scanner kommen so besser zurecht.
        $matrix = Encoder::encode($inhalt, ErrorCorrectionLevel::M())->getMatrix();
        $anzahl = $matrix->getWidth();
        $ruhezone = 2; // heller Rand, damit Handys den Code sicher erkennen
        $modul = $groesse / ($anzahl + 2 * $ruhezone);

        $pdf->SetFillColor(0, 0, 0);
        for ($zeile = 0; $zeile < $anzahl; $zeile++) {
            $start = null;
            for ($spalte = 0; $spalte <= $anzahl; $spalte++) {
                $dunkel = $spalte < $anzahl && $matrix->get($spalte, $zeile) === 1;
                if ($dunkel && $start === null) {
                    $start = $spalte;
                } elseif (! $dunkel && $start !== null) {
                    // Zusammenhängende Module einer Zeile als ein Rechteck (kleinere Datei)
                    $pdf->Rect($x + ($ruhezone + $start) * $modul, $y + ($ruhezone + $zeile) * $modul, ($spalte - $start) * $modul, $modul + 0.02, 'F');
                    $start = null;
                }
            }
        }
    }

    /** FPDF-Standardschriften kennen nur Windows-1252 – Umlaute und € sind darin enthalten. */
    private function text(string $text): string
    {
        return (string) iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
    }
}
