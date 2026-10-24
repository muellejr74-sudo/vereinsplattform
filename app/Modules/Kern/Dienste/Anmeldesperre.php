<?php

namespace App\Modules\Kern\Dienste;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Sperre nach Fehlversuchen (E16a, Architektur Abschnitt 13).
 *
 * Zwei Zähler:
 * - E-Mail + Gerät (IP):  5 Fehlversuche → 15 Minuten gesperrt
 * - nur E-Mail (Konto):  10 Fehlversuche → 60 Minuten gesperrt
 *   (schützt auch, wenn jemand von vielen Geräten aus probiert)
 *
 * Dieselbe Sperre gilt für den Zwei-Faktor-Code (eigener Zähler).
 */
class Anmeldesperre
{
    public const VERSUCHE_GERAET = 5;

    public const MINUTEN_GERAET = 15;

    public const VERSUCHE_KONTO = 10;

    public const MINUTEN_KONTO = 60;

    public function __construct(private string $bereich = 'anmeldung') {}

    public static function zweiFaktor(): self
    {
        return new self('zweifaktor');
    }

    /** Sekunden bis zur Entsperrung, 0 wenn nicht gesperrt. */
    public function gesperrtFuer(string $email, string $ip): int
    {
        $geraet = $this->schluesselGeraet($email, $ip);
        $konto = $this->schluesselKonto($email);

        if (RateLimiter::tooManyAttempts($geraet, self::VERSUCHE_GERAET)) {
            return RateLimiter::availableIn($geraet);
        }
        if (RateLimiter::tooManyAttempts($konto, self::VERSUCHE_KONTO)) {
            return RateLimiter::availableIn($konto);
        }

        return 0;
    }

    public function fehlversuch(string $email, string $ip): void
    {
        RateLimiter::hit($this->schluesselGeraet($email, $ip), self::MINUTEN_GERAET * 60);
        RateLimiter::hit($this->schluesselKonto($email), self::MINUTEN_KONTO * 60);
    }

    public function erfolg(string $email, string $ip): void
    {
        RateLimiter::clear($this->schluesselGeraet($email, $ip));
        RateLimiter::clear($this->schluesselKonto($email));
    }

    private function schluesselGeraet(string $email, string $ip): string
    {
        return $this->bereich.':'.sha1(Str::lower(trim($email))).'|'.$ip;
    }

    private function schluesselKonto(string $email): string
    {
        return $this->bereich.'-konto:'.sha1(Str::lower(trim($email)));
    }
}
