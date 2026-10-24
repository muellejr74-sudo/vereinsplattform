<?php

namespace App\Modules\Ticket\Dienste;

/** Beträge werden in Cent gespeichert und deutsch angezeigt. */
class Geld
{
    public static function anzeigen(int $cent): string
    {
        return number_format($cent / 100, 2, ',', '.').' €';
    }

    /** „12,50“, „12.50“ oder „12“ → 1250 Cent; null bei ungültiger Eingabe. */
    public static function ausEingabe(?string $text): ?int
    {
        $text = trim(str_replace(['€', ' '], '', (string) $text));
        if (! preg_match('/^\d{1,5}([.,]\d{1,2})?$/', $text)) {
            return null;
        }

        return (int) round(((float) str_replace(',', '.', $text)) * 100);
    }
}
