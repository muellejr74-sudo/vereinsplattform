<?php

namespace App\Modules\Kern\Rechte;

/**
 * Rechtekatalog (E73, E94) und Rollenvorlage „Vereinsadmin“.
 *
 * Jedes Recht gehört zu einem Bereich und ggf. zu einem Modul. Ist das Modul
 * für einen Verein nicht freigeschaltet, ruht das Recht (E96).
 *
 * Bewusst NICHT im Katalog, weil nie delegierbar (nur Super-Admin):
 * Vereinsdesign, zentrale Werbung, Modulfreischaltungen, Abschluss rückgängig
 * machen, Verein anlegen/deaktivieren, Plattformeinstellungen, Betreiber-Tische.
 */
final class Rechtekatalog
{
    public const MODULE = [
        'ticket' => 'Ticketverwaltung',
        'saal' => 'Saalbestellungen',
        'gaesteportal' => 'Gästeportal',
        'werbung' => 'Werbung',
    ];

    /**
     * Schlüssel => [Bezeichnung, Bereich, Modul|null, Stufe ab der es wirkt, in Vorlage Vereinsadmin]
     *
     * @var array<string, array{0: string, 1: string, 2: ?string, 3: string, 4: bool}>
     */
    private const RECHTE = [
        // Verwaltung
        'benutzer.verwalten' => ['Personen anlegen, hinzufügen und entfernen', 'Verwaltung', null, '1b', true],
        'rechte.vergeben' => ['Rechte vergeben (nur eigene)', 'Verwaltung', null, '1b', true],
        'protokoll.ansehen' => ['Protokoll des Vereins ansehen', 'Verwaltung', null, '1b', true],
        'stationen.verwalten' => ['Stationen verwalten', 'Verwaltung', null, '2', true],
        'orte.verwalten' => ['Orte und Säle verwalten', 'Verwaltung', null, '2', true],
        'veranstaltungen.verwalten' => ['Veranstaltungen und Vorstellungen anlegen', 'Verwaltung', null, '2', true],
        'qrcodes.verwalten' => ['QR-Codes erstellen und Tische zuordnen', 'Verwaltung', null, '2', true],

        // Ticket
        'ticket.kategorien' => ['Kategorien und Preise', 'Ticket', 'ticket', '3', true],
        'ticket.kontingente' => ['Kontingente', 'Ticket', 'ticket', '3', true],
        'ticket.reservierungen' => ['Reservierungen und Freikarten-Stapel des Vereins', 'Ticket', 'ticket', '3', true],
        'ticket.verkaufsstellen' => ['Vorverkaufsstellen verwalten', 'Ticket', 'ticket', '3', true],
        'ticket.abendkasse' => ['Abendkasse', 'Ticket', 'ticket', '4a', true],
        'ticket.auswertungen' => ['Auswertungen ansehen', 'Ticket', 'ticket', '4b', true],

        // Ticket (sensibel) – in der Vorlage standardmäßig aus
        'ticket.drucken' => ['Druckaufträge drucken', 'Ticket (sensibel)', 'ticket', '3', false],
        'ticket.stornieren' => ['Stornieren', 'Ticket (sensibel)', 'ticket', '4a', false],
        'ticket.zahlungseingang' => ['Zahlungseingang vermerken (Vorkasse)', 'Ticket (sensibel)', 'ticket', '4a', false],
        'ticket.abschluss' => ['Abschluss durchführen', 'Ticket (sensibel)', 'ticket', '4b', false],

        // Saal
        'saal.artikel' => ['Artikel pflegen', 'Saal', 'saal', '6', true],
        'saal.tischbereiche' => ['Tischbereiche', 'Saal', 'saal', '6', true],
        'saal.bestellfunktion' => ['Bestellfunktion öffnen/schließen', 'Saal', 'saal', '7', true],
        'saal.codes' => ['Saal-Code und Einsatz-Code erzeugen', 'Saal', 'saal', '7', true],

        // Gästeportal / Werbung
        'gaesteportal.inhalte' => ['Gästeportal-Inhalte pflegen', 'Gästeportal/Werbung', 'gaesteportal', '7', true],
        'werbung.vereinswerbung' => ['Vereinswerbung pflegen', 'Gästeportal/Werbung', 'werbung', '8', true],
        'verein.impressum' => ['Vereinsimpressum pflegen', 'Gästeportal/Werbung', null, '1b', true],
    ];

    /** Stufen, deren Rechte bereits eine Funktion haben. */
    private const AKTIVE_STUFEN = ['1a', '1b', '2', '3', '4a'];

    /** @return list<string> */
    public static function alle(): array
    {
        return array_keys(self::RECHTE);
    }

    public static function gibt(string $recht): bool
    {
        return isset(self::RECHTE[$recht]);
    }

    public static function bezeichnung(string $recht): string
    {
        return self::RECHTE[$recht][0] ?? $recht;
    }

    public static function modul(string $recht): ?string
    {
        return self::RECHTE[$recht][2] ?? null;
    }

    public static function stufe(string $recht): string
    {
        return self::RECHTE[$recht][3] ?? '';
    }

    /** Hat das Recht schon eine Funktion? Sonst Hinweis „folgt in Stufe X“ (E94). */
    public static function istVerfuegbar(string $recht): bool
    {
        return in_array(self::stufe($recht), self::AKTIVE_STUFEN, true);
    }

    /** @return list<string> */
    public static function vorlageVereinsadmin(): array
    {
        return array_keys(array_filter(self::RECHTE, fn ($r) => $r[4]));
    }

    /** @return array<string, list<string>> Bereich => Rechte (für die Anzeige) */
    public static function nachBereich(): array
    {
        $gruppen = [];
        foreach (self::RECHTE as $schluessel => $r) {
            $gruppen[$r[1]][] = $schluessel;
        }

        return $gruppen;
    }

    /** Rechte, die die Vereinsverwaltung öffnen. */
    public const VERWALTUNG = [
        'benutzer.verwalten', 'rechte.vergeben', 'protokoll.ansehen', 'verein.impressum',
        'orte.verwalten', 'veranstaltungen.verwalten', 'stationen.verwalten', 'qrcodes.verwalten', 'saal.codes',
    ];
}
