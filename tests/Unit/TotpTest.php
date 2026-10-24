<?php

namespace Tests\Unit;

use App\Modules\Kern\Dienste\Totp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Prüft die Zwei-Faktor-Codes gegen die offiziellen Prüfwerte aus RFC 6238 (Anhang B)
 * sowie gegen das bekannte Beispiel aus RFC 4226.
 */
class TotpTest extends TestCase
{
    /** @return array<string, array{int, string}> */
    public static function rfc6238Werte(): array
    {
        return [
            '59' => [59, '94287082'],
            '1111111109' => [1111111109, '07081804'],
            '1111111111' => [1111111111, '14050471'],
            '1234567890' => [1234567890, '89005924'],
            '2000000000' => [2000000000, '69279037'],
            '20000000000' => [20000000000, '65353130'],
        ];
    }

    #[DataProvider('rfc6238Werte')]
    public function test_offizielle_pruefwerte_sha1(int $zeit, string $erwartet): void
    {
        $this->assertSame($erwartet, Totp::codeAusSchluessel('12345678901234567890', Totp::zeitschritt($zeit), 8));
    }

    public function test_rfc4226_beispiel(): void
    {
        $erwartet = ['755224', '287082', '359152', '969429', '338314'];
        foreach ($erwartet as $zaehler => $code) {
            $this->assertSame($code, Totp::codeAusSchluessel('12345678901234567890', $zaehler));
        }
    }

    public function test_base32_hin_und_zurueck(): void
    {
        $daten = random_bytes(20);
        $this->assertSame($daten, Totp::base32Dekodieren(Totp::base32Kodieren($daten)));
        $this->assertSame('12345678901234567890', Totp::base32Dekodieren('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'));
    }

    public function test_neues_geheimnis_hat_160_bit(): void
    {
        $geheimnis = Totp::neuesGeheimnis();
        $this->assertSame(32, strlen($geheimnis));
        $this->assertSame(20, strlen(Totp::base32Dekodieren($geheimnis)));
        $this->assertNotSame($geheimnis, Totp::neuesGeheimnis());
    }

    public function test_pruefung_mit_toleranz_von_einem_schritt(): void
    {
        $geheimnis = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $jetzt = 1_800_000_000;
        $schritt = Totp::zeitschritt($jetzt);

        $this->assertSame($schritt, Totp::pruefe($geheimnis, Totp::code($geheimnis, $schritt), $jetzt));
        $this->assertSame($schritt - 1, Totp::pruefe($geheimnis, Totp::code($geheimnis, $schritt - 1), $jetzt));
        $this->assertSame($schritt + 1, Totp::pruefe($geheimnis, Totp::code($geheimnis, $schritt + 1), $jetzt));
        $this->assertNull(Totp::pruefe($geheimnis, Totp::code($geheimnis, $schritt - 2), $jetzt));
        $this->assertNull(Totp::pruefe($geheimnis, Totp::code($geheimnis, $schritt + 2), $jetzt));
    }

    public function test_ungueltige_eingaben_werden_abgelehnt(): void
    {
        $geheimnis = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->assertNull(Totp::pruefe($geheimnis, ''));
        $this->assertNull(Totp::pruefe($geheimnis, '12345'));
        $this->assertNull(Totp::pruefe($geheimnis, 'abcdef'));
        $this->assertNull(Totp::pruefe($geheimnis, '1234567'));
    }

    public function test_einrichtungslink(): void
    {
        $link = Totp::einrichtungsLink('ABCDEFGH', 'admin@beispiel.de', 'Vereinsplattform');
        $this->assertStringStartsWith('otpauth://totp/Vereinsplattform:admin%40beispiel.de?secret=ABCDEFGH', $link);
        $this->assertStringContainsString('digits=6', $link);
        $this->assertStringContainsString('period=30', $link);
    }
}
