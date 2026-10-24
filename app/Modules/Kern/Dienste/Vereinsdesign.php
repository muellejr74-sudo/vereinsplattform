<?php

namespace App\Modules\Kern\Dienste;

use App\Modules\Kern\Models\Mandant;

/**
 * Vereinsdesign (E76, E76a, E99): Farben kommen aus der Datenbank, nie aus dem Code.
 *
 * - Schriftfarbe auf Vereinsfarben wird automatisch gewählt (schwarz oder weiß,
 *   je nachdem was besser lesbar ist – Kontrastberechnung nach WCAG).
 * - Bei schlecht lesbaren Farben gibt es einen Hinweis, aber keine Sperre.
 */
class Vereinsdesign
{
    private const SCHWARZ = '#1b1b18';

    private const WEISS = '#ffffff';

    /** Mindestkontrast der Primärfarbe zum weißen Seitenhintergrund (Links, Ränder). */
    public const MINDESTKONTRAST = 3.0;

    /**
     * CSS-Variablen für einen Verein, oder leer (dann gilt das Plattformdesign).
     *
     * @return array<string, string>
     */
    public static function variablen(?Mandant $verein): array
    {
        if (! $verein) {
            return [];
        }

        $werte = [];
        if (self::istFarbe($verein->farbe_primaer)) {
            $werte['--farbe-primaer'] = strtolower($verein->farbe_primaer);
            $werte['--farbe-primaer-text'] = self::textfarbe($verein->farbe_primaer);
        }
        if (self::istFarbe($verein->farbe_sekundaer)) {
            $werte['--farbe-sekundaer'] = strtolower($verein->farbe_sekundaer);
            $werte['--farbe-sekundaer-text'] = self::textfarbe($verein->farbe_sekundaer);
        }

        return $werte;
    }

    public static function textfarbe(string $hintergrund): string
    {
        return self::kontrast($hintergrund, self::SCHWARZ) >= self::kontrast($hintergrund, self::WEISS) ? self::SCHWARZ : self::WEISS;
    }

    /** @return list<string> verständliche Hinweise zu schlecht lesbaren Farben */
    public static function hinweise(?string $primaer, ?string $sekundaer): array
    {
        $hinweise = [];
        if (self::istFarbe($primaer) && self::kontrast($primaer, self::WEISS) < self::MINDESTKONTRAST) {
            $hinweise[] = 'Die Primärfarbe ist sehr hell. Links und Rahmen in dieser Farbe sind auf weißem Grund schlecht zu erkennen – eine dunklere Farbe wäre besser lesbar.';
        }
        if (self::istFarbe($primaer) && self::istFarbe($sekundaer) && self::kontrast($primaer, $sekundaer) < 1.3) {
            $hinweise[] = 'Primär- und Sekundärfarbe sind kaum zu unterscheiden.';
        }

        return $hinweise;
    }

    public static function kontrast(string $a, string $b): float
    {
        [$hell, $dunkel] = [self::leuchtdichte($a), self::leuchtdichte($b)];
        if ($hell < $dunkel) {
            [$hell, $dunkel] = [$dunkel, $hell];
        }

        return ($hell + 0.05) / ($dunkel + 0.05);
    }

    public static function istFarbe(?string $wert): bool
    {
        return is_string($wert) && preg_match('/^#[0-9a-fA-F]{6}$/', $wert) === 1;
    }

    private static function leuchtdichte(string $hex): float
    {
        $kanal = function (string $teil): float {
            $c = hexdec($teil) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        $hex = ltrim($hex, '#');

        return 0.2126 * $kanal(substr($hex, 0, 2)) + 0.7152 * $kanal(substr($hex, 2, 2)) + 0.0722 * $kanal(substr($hex, 4, 2));
    }
}
