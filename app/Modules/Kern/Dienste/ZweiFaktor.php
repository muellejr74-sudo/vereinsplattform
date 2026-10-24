<?php

namespace App\Modules\Kern\Dienste;

use App\Models\User;
use App\Modules\Kern\Models\VertrautesGeraet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as CookieObjekt;

/**
 * Zwei-Faktor-Anmeldung für den Super-Admin (E16a–E16d):
 * Authenticator-App, Notfall-Codes und "Gerät 30 Tage merken".
 */
class ZweiFaktor
{
    public const ANZAHL_NOTFALLCODES = 8;

    public const GERAET_TAGE = 30;

    public const GERAET_COOKIE = 'vp_vertrautes_geraet';

    /**
     * Schließt die Einrichtung ab, wenn der eingegebene Code zum neuen Geheimnis passt.
     *
     * @return list<string>|null die neuen Notfall-Codes (nur jetzt im Klartext sichtbar) oder null bei falschem Code
     */
    public function einrichten(User $benutzer, string $geheimnis, string $code): ?array
    {
        $schritt = Totp::pruefe($geheimnis, $code);
        if ($schritt === null) {
            return null;
        }

        $codes = $this->neueNotfallcodes();
        $benutzer->forceFill([
            'zwei_faktor_geheimnis' => $geheimnis,
            'zwei_faktor_notfallcodes' => array_map([$this, 'pruefsumme'], $codes),
            'zwei_faktor_aktiv_seit' => now(),
            'zwei_faktor_letzter_schritt' => $schritt,
        ])->save();

        return $codes;
    }

    /**
     * Prüft einen App-Code oder einen Notfall-Code.
     *
     * @return string|null 'app', 'notfallcode' oder null, wenn ungültig
     */
    public function pruefe(User $benutzer, string $eingabe): ?string
    {
        if (! $benutzer->hatZweiFaktor()) {
            return null;
        }

        $eingabe = trim($eingabe);

        if (preg_match('/^\d[\d ]*$/', $eingabe)) {
            return $this->pruefeAppCode($benutzer, $eingabe) ? 'app' : null;
        }

        return $this->loeseNotfallcodeEin($benutzer, $eingabe) ? 'notfallcode' : null;
    }

    /** App-Code prüfen; jeder Code gilt nur einmal (Schutz gegen Wiederverwendung). */
    private function pruefeAppCode(User $benutzer, string $code): bool
    {
        return DB::transaction(function () use ($benutzer, $code) {
            $frisch = User::whereKey($benutzer->id)->lockForUpdate()->first();
            $schritt = Totp::pruefe($frisch->zwei_faktor_geheimnis, $code);

            if ($schritt === null || ($frisch->zwei_faktor_letzter_schritt !== null && $schritt <= $frisch->zwei_faktor_letzter_schritt)) {
                return false;
            }

            $frisch->forceFill(['zwei_faktor_letzter_schritt' => $schritt])->save();

            return true;
        });
    }

    private function loeseNotfallcodeEin(User $benutzer, string $code): bool
    {
        return DB::transaction(function () use ($benutzer, $code) {
            $frisch = User::whereKey($benutzer->id)->lockForUpdate()->first();
            $vorhanden = $frisch->zwei_faktor_notfallcodes ?? [];
            $gesucht = $this->pruefsumme($code);

            foreach ($vorhanden as $index => $summe) {
                if (hash_equals($summe, $gesucht)) {
                    unset($vorhanden[$index]);
                    $frisch->forceFill(['zwei_faktor_notfallcodes' => array_values($vorhanden)])->save();

                    return true;
                }
            }

            return false;
        });
    }

    /** @return list<string> */
    public function erneuereNotfallcodes(User $benutzer): array
    {
        $codes = $this->neueNotfallcodes();
        $benutzer->forceFill(['zwei_faktor_notfallcodes' => array_map([$this, 'pruefsumme'], $codes)])->save();

        return $codes;
    }

    public function anzahlNotfallcodes(User $benutzer): int
    {
        return count($benutzer->zwei_faktor_notfallcodes ?? []);
    }

    /** Setzt die Zwei-Faktor-Anmeldung vollständig zurück (Server-Befehl, E16c). */
    public function zuruecksetzen(User $benutzer): void
    {
        $benutzer->forceFill([
            'zwei_faktor_geheimnis' => null,
            'zwei_faktor_notfallcodes' => null,
            'zwei_faktor_aktiv_seit' => null,
            'zwei_faktor_letzter_schritt' => null,
        ])->save();

        $this->alleGeraeteAbmelden($benutzer);
    }

    // ------------------------------------------------------------------
    // Gerät 30 Tage merken
    // ------------------------------------------------------------------

    public function merkeGeraet(User $benutzer, Request $anfrage): CookieObjekt
    {
        $token = Str::random(64);

        VertrautesGeraet::create([
            'user_id' => $benutzer->id,
            'token_pruefsumme' => hash('sha256', $token),
            'bezeichnung' => Str::limit((string) $anfrage->userAgent(), 180, ''),
            'gueltig_bis' => now()->addDays(self::GERAET_TAGE),
        ]);

        // Laravel verschlüsselt Cookies automatisch; zusätzlich nur per HTTPS und nicht für JavaScript lesbar.
        return Cookie::make(self::GERAET_COOKIE, $benutzer->id.'|'.$token, self::GERAET_TAGE * 24 * 60, null, null, null, true, false, 'lax');
    }

    public function istVertrautesGeraet(User $benutzer, Request $anfrage): bool
    {
        $wert = (string) $anfrage->cookie(self::GERAET_COOKIE, '');
        if (! str_contains($wert, '|')) {
            return false;
        }

        [$benutzerId, $token] = explode('|', $wert, 2);
        if ((int) $benutzerId !== $benutzer->id) {
            return false;
        }

        return VertrautesGeraet::where('user_id', $benutzer->id)
            ->where('token_pruefsumme', hash('sha256', $token))
            ->where('gueltig_bis', '>', now())
            ->exists();
    }

    public function alleGeraeteAbmelden(User $benutzer): int
    {
        return VertrautesGeraet::where('user_id', $benutzer->id)->delete();
    }

    // ------------------------------------------------------------------

    /** @return list<string> */
    private function neueNotfallcodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::ANZAHL_NOTFALLCODES; $i++) {
            // Ohne leicht verwechselbare Zeichen (0/O, 1/I/L).
            $zeichen = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
            $code = '';
            for ($j = 0; $j < 10; $j++) {
                $code .= $zeichen[random_int(0, strlen($zeichen) - 1)];
            }
            $codes[] = substr($code, 0, 5).'-'.substr($code, 5);
        }

        return $codes;
    }

    private function pruefsumme(string $code): string
    {
        $normalisiert = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));

        return hash_hmac('sha256', $normalisiert, (string) config('app.key'));
    }
}
