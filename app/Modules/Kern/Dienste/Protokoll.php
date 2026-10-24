<?php

namespace App\Modules\Kern\Dienste;

use App\Models\User;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Protokolleintrag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Schreibt Einträge ins unveränderbare Protokoll (E84).
 *
 * Beispiel: Protokoll::schreibe('verein.angelegt', $verein, ['name' => $verein->name]);
 */
class Protokoll
{
    /**
     * @param  array<string, mixed>  $details
     */
    public static function schreibe(
        string $aktion,
        ?Model $ziel = null,
        array $details = [],
        ?User $benutzer = null,
        ?int $mandantId = null,
        ?string $akteur = null,
    ): Protokolleintrag {
        $benutzer ??= Auth::user();

        // Vermerkt wird der Verein des Ziels; plattformweite Ziele (Benutzerkonto,
        // Plattformtext) gehören keinem Verein. Ohne Ziel zählt der aktuelle Verein.
        $mandantId ??= match (true) {
            $ziel instanceof Mandant => $ziel->id,
            $ziel !== null && isset($ziel->mandant_id) => (int) $ziel->mandant_id,
            $ziel !== null => null,
            default => app(Mandantenkontext::class)->id(),
        };

        return Protokolleintrag::create([
            'zeitpunkt' => now(),
            'mandant_id' => $mandantId,
            'benutzer_id' => $benutzer?->id,
            'akteur' => $akteur ?? $benutzer?->name,
            'aktion' => $aktion,
            'ziel_typ' => $ziel ? class_basename($ziel) : null,
            'ziel_id' => $ziel?->getKey(),
            'details' => $details ?: null,
            'ip' => app()->runningInConsole() && ! app()->runningUnitTests() ? 'Server-Befehl' : request()->ip(),
        ]);
    }

    /** Verständliche Bezeichnungen für die Protokollansicht. */
    public const BEZEICHNUNGEN = [
        'anmeldung.erfolgreich' => 'Anmeldung',
        'anmeldung.fehlgeschlagen' => 'Anmeldung fehlgeschlagen',
        'anmeldung.gesperrt' => 'Anmeldung gesperrt (zu viele Fehlversuche)',
        'abmeldung' => 'Abmeldung',
        'zweifaktor.eingerichtet' => 'Zwei-Faktor eingerichtet',
        'zweifaktor.fehlgeschlagen' => 'Zwei-Faktor-Code falsch',
        'zweifaktor.notfallcode_benutzt' => 'Notfall-Code benutzt',
        'zweifaktor.notfallcodes_neu' => 'Neue Notfall-Codes erzeugt',
        'zweifaktor.zurueckgesetzt' => 'Zwei-Faktor zurückgesetzt (Server)',
        'zweifaktor.geraete_abgemeldet' => 'Vertraute Geräte abgemeldet',
        'passwort.link_angefordert' => 'Passwort-Link angefordert',
        'passwort.zurueckgesetzt' => 'Passwort zurückgesetzt',
        'passwort.geaendert' => 'Passwort geändert',
        'passwort.festgelegt' => 'Passwort nach Einladung festgelegt',
        'benutzer.angelegt' => 'Benutzer angelegt',
        'benutzer.geaendert' => 'Benutzer geändert',
        'benutzer.aktiviert' => 'Benutzer aktiviert',
        'benutzer.deaktiviert' => 'Benutzer deaktiviert',
        'benutzer.einladung_gesendet' => 'Einladung gesendet',
        'benutzer.verein_zugeordnet' => 'Benutzer einem Verein zugeordnet',
        'benutzer.verein_entfernt' => 'Benutzer aus Verein entfernt',
        'superadmin.angelegt' => 'Super-Admin angelegt (Server)',
        'verein.angelegt' => 'Verein angelegt',
        'verein.geaendert' => 'Verein geändert',
        'verein.aktiviert' => 'Verein aktiviert',
        'verein.deaktiviert' => 'Verein deaktiviert',
        'verein.design_geaendert' => 'Vereinsdesign geändert',
        'verein.module_geaendert' => 'Module geändert',
        'rechte.geaendert' => 'Rechte geändert',
        'rechtstext.geaendert' => 'Rechtstext geändert',
        'ort.angelegt' => 'Ort angelegt',
        'ort.geaendert' => 'Ort geändert',
        'saal.angelegt' => 'Saal angelegt',
        'saal.geaendert' => 'Saal geändert',
        'saal.geloescht' => 'Saal gelöscht',
        'veranstaltung.angelegt' => 'Veranstaltung angelegt',
        'veranstaltung.geaendert' => 'Veranstaltung geändert',
        'veranstaltung.geloescht' => 'Veranstaltung gelöscht',
        'vorstellung.angelegt' => 'Vorstellung angelegt',
        'vorstellung.geaendert' => 'Vorstellung geändert',
        'vorstellung.geloescht' => 'Vorstellung gelöscht',
        'station.angelegt' => 'Station angelegt',
        'station.geaendert' => 'Station geändert',
        'station.geloescht' => 'Station gelöscht',
        'station.besetzung' => 'Stationsnamen eingetragen',
        'station.angemeldet' => 'Gerät an Station angemeldet',
        'station.abgemeldet' => 'Gerät von Station abgemeldet',
        'station.anmeldung_fehlgeschlagen' => 'Einsatz-Code falsch',
        'einsatzcode.erzeugt' => 'Einsatz-Code erzeugt',
        'qrcodes.erzeugt' => 'QR-Codes erzeugt',
        'qrcode.geloescht' => 'QR-Code gelöscht',
        'tisch.zugeordnet' => 'Tisch zugeordnet',
        'tisch.uebernommen' => 'Tisch übernommen',
        'tisch.abgegeben' => 'Tisch an andere Veranstaltung abgegeben',
        'tisch.geloest' => 'Tischzuordnung gelöst',
        'ticket.preis_geaendert' => 'Ticketpreis/Kategorie geändert',
        'ticket.kontingent_geaendert' => 'Kontingent geändert',
        'ticket.verkaufsstelle_angelegt' => 'Verkaufsstelle angelegt',
        'ticket.verkaufsstelle_geaendert' => 'Verkaufsstelle geändert',
        'ticket.auftrag_angefordert' => 'Kartenauftrag angefordert',
        'ticket.auftrag_bestaetigt' => 'Kartenauftrag bestätigt (Karten erzeugt)',
        'ticket.auftrag_abgelehnt' => 'Kartenauftrag abgelehnt',
        'ticket.auftrag_zurueckgezogen' => 'Kartenauftrag zurückgezogen',
        'ticket.gedruckt' => 'Karten gedruckt',
        'ticket.nachdruck' => 'Karte nachgedruckt',
        'ticket.ausgegeben' => 'Karten übergeben',
    ];

    public static function bezeichnung(string $aktion): string
    {
        return self::BEZEICHNUNGEN[$aktion] ?? $aktion;
    }
}
