<?php

namespace App\Modules\Kern\Dienste;

use InvalidArgumentException;

/**
 * Zeitbasierte Einmal-Codes nach RFC 6238 (wie Google/Microsoft Authenticator):
 * 6 Ziffern, wechseln alle 30 Sekunden, Verfahren SHA-1.
 *
 * Bewusst ohne Zusatzpaket umgesetzt (wenige Zeilen, mit den offiziellen
 * Prüfwerten aus RFC 6238 getestet, siehe tests/Unit/TotpTest.php).
 */
class Totp
{
    public const SCHRITT_SEKUNDEN = 30;

    public const STELLEN = 6;

    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Neues Geheimnis (160 Bit), Base32-kodiert wie von den Apps erwartet. */
    public static function neuesGeheimnis(): string
    {
        return self::base32Kodieren(random_bytes(20));
    }

    public static function zeitschritt(?int $zeitpunkt = null): int
    {
        return intdiv($zeitpunkt ?? time(), self::SCHRITT_SEKUNDEN);
    }

    public static function code(string $geheimnisBase32, int $zeitschritt, int $stellen = self::STELLEN, string $verfahren = 'sha1'): string
    {
        return self::codeAusSchluessel(self::base32Dekodieren($geheimnisBase32), $zeitschritt, $stellen, $verfahren);
    }

    public static function codeAusSchluessel(string $schluessel, int $zeitschritt, int $stellen = self::STELLEN, string $verfahren = 'sha1'): string
    {
        $zaehler = pack('N2', ($zeitschritt >> 32) & 0xFFFFFFFF, $zeitschritt & 0xFFFFFFFF);
        $hash = hash_hmac($verfahren, $zaehler, $schluessel, true);
        $versatz = ord($hash[strlen($hash) - 1]) & 0x0F;
        $wert = ((ord($hash[$versatz]) & 0x7F) << 24)
            | (ord($hash[$versatz + 1]) << 16)
            | (ord($hash[$versatz + 2]) << 8)
            | ord($hash[$versatz + 3]);

        return str_pad((string) ($wert % (10 ** $stellen)), $stellen, '0', STR_PAD_LEFT);
    }

    /**
     * Prüft einen Code und erlaubt eine Abweichung von ±1 Zeitschritt
     * (Uhren von Handy und Server laufen nie exakt gleich).
     *
     * @return int|null gefundener Zeitschritt oder null, wenn ungültig
     */
    public static function pruefe(string $geheimnisBase32, string $code, ?int $zeitpunkt = null, int $toleranz = 1): ?int
    {
        $code = preg_replace('/\s+/', '', $code);
        if (! preg_match('/^\d{'.self::STELLEN.'}$/', (string) $code)) {
            return null;
        }

        $jetzt = self::zeitschritt($zeitpunkt);
        for ($abweichung = -$toleranz; $abweichung <= $toleranz; $abweichung++) {
            if (hash_equals(self::code($geheimnisBase32, $jetzt + $abweichung), $code)) {
                return $jetzt + $abweichung;
            }
        }

        return null;
    }

    /** Link, den die Authenticator-App beim Scannen des QR-Codes versteht. */
    public static function einrichtungsLink(string $geheimnis, string $konto, string $aussteller): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($aussteller),
            rawurlencode($konto),
            $geheimnis,
            rawurlencode($aussteller),
            self::STELLEN,
            self::SCHRITT_SEKUNDEN,
        );
    }

    public static function base32Kodieren(string $daten): string
    {
        $bits = '';
        foreach (str_split($daten) as $zeichen) {
            $bits .= str_pad(decbin(ord($zeichen)), 8, '0', STR_PAD_LEFT);
        }

        $ergebnis = '';
        foreach (str_split($bits, 5) as $gruppe) {
            $ergebnis .= self::BASE32[bindec(str_pad($gruppe, 5, '0'))];
        }

        return $ergebnis;
    }

    public static function base32Dekodieren(string $text): string
    {
        $text = strtoupper(preg_replace('/[\s=-]/', '', $text));
        $bits = '';
        foreach (str_split($text) as $zeichen) {
            $wert = strpos(self::BASE32, $zeichen);
            if ($wert === false) {
                throw new InvalidArgumentException('Ungültiges Zeichen im Geheimnis.');
            }
            $bits .= str_pad(decbin($wert), 5, '0', STR_PAD_LEFT);
        }

        $daten = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $daten .= chr(bindec($byte));
            }
        }

        return $daten;
    }
}
