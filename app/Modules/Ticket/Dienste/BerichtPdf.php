<?php

namespace App\Modules\Ticket\Dienste;

use FPDF;

/**
 * Schlichte A4-Berichte (Abschlussbericht, Abrechnung, Notfallliste) mit FPDF (T2-1).
 * Nur Schwarz/Grau, Kopf- und Fußzeile mit Seitenzahl.
 */
class BerichtPdf extends FPDF
{
    public string $kopfLinks = '';

    public string $kopfRechts = '';

    public function __construct(string $titel)
    {
        parent::__construct('P', 'mm', 'A4');
        $this->SetMargins(15, 18, 15);
        $this->SetAutoPageBreak(true, 18);
        $this->SetCreator('Vereinsplattform');
        $this->SetTitle(self::t($titel));
        $this->AliasNbPages();
    }

    public static function t(string $text): string
    {
        return (string) iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
    }

    public function Header(): void
    {
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(110, 110, 110);
        $this->SetY(8);
        $this->Cell(90, 4, self::t($this->kopfLinks));
        $this->Cell(0, 4, self::t($this->kopfRechts), 0, 0, 'R');
        $this->SetTextColor(0, 0, 0);
        $this->SetY(18);
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(110, 110, 110);
        $this->Cell(0, 4, self::t('Seite '.$this->PageNo().' von {nb} · erstellt '.now()->format('d.m.Y H:i')), 0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }

    public function titel(string $titel, ?string $unter = null): void
    {
        $this->SetFont('Helvetica', 'B', 16);
        $this->Cell(0, 8, self::t($titel), 0, 1);
        if ($unter) {
            $this->SetFont('Helvetica', '', 10);
            $this->MultiCell(0, 5, self::t($unter));
        }
        $this->Ln(3);
    }

    public function abschnitt(string $text): void
    {
        if ($this->GetY() > 250) {
            $this->AddPage();
        }
        $this->Ln(2);
        $this->SetFont('Helvetica', 'B', 11.5);
        $this->Cell(0, 7, self::t($text), 0, 1);
    }

    public function absatz(string $text, int $groesse = 9): void
    {
        $this->SetFont('Helvetica', '', $groesse);
        $this->MultiCell(0, 4.6, self::t($text));
    }

    /**
     * @param  list<array{0: string, 1: float, 2?: string}>  $spalten  [Überschrift, Breite mm, 'R' für rechtsbündig]
     * @param  list<list<string>>  $zeilen
     * @param  list<string>|null  $summe  fett gesetzte Abschlusszeile
     */
    public function tabelle(array $spalten, array $zeilen, ?array $summe = null): void
    {
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->SetFillColor(232, 232, 232);
        foreach ($spalten as [$text, $breite]) {
            $this->Cell($breite, 6, self::t($text), 1, 0, 'L', true);
        }
        $this->Ln();
        $this->SetFont('Helvetica', '', 8.5);
        foreach ($zeilen as $zeile) {
            if ($this->GetY() > 272) {
                $this->AddPage();
            }
            foreach ($spalten as $i => $spalte) {
                $text = self::t((string) ($zeile[$i] ?? ''));
                while ($this->GetStringWidth($text) > $spalte[1] - 2 && strlen($text) > 1) {
                    $text = substr($text, 0, -1);
                }
                $this->Cell($spalte[1], 5.5, $text, 1, 0, $spalte[2] ?? 'L');
            }
            $this->Ln();
        }
        if ($summe) {
            $this->SetFont('Helvetica', 'B', 8.5);
            foreach ($spalten as $i => $spalte) {
                $this->Cell($spalte[1], 6, self::t((string) ($summe[$i] ?? '')), 1, 0, $spalte[2] ?? 'L');
            }
            $this->Ln();
        }
        $this->Ln(1);
    }

    public function ausgabe(): string
    {
        return $this->Output('S');
    }
}
