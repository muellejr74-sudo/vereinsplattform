# Module der Plattform

Die Plattform ist ein **modularer Monolith**: eine Anwendung, innen klar in Module getrennt (Entscheidung E14).

| Ordner | Modul | Inhalt | Umsetzung |
|---|---|---|---|
| `Kern` | Plattformkern + gemeinsame Stammdaten | Mandanten, Benutzer, Rechte, Design, Protokoll, Rechtstexte, Veranstaltungen, Vorstellungen, Stationen, QR-Codes | Stufe 1–2 |
| `Ticket` | Ticketverwaltung | Kategorien, Kartenaufträge, Druck, Reservierungen, Abendkasse, Storno, Einlass, Abschluss | Stufe 3–4 |
| `Saal` | Saalbestellungen | Artikel, Bestellungen, Ausgabestellen, Tischbereiche | Stufe 6 |
| `Gaesteportal` | Digitales Gästeportal | Scan-Weiche, Karte, Gästebestellung | Stufe 7 |
| `Werbung` | Werbeverwaltung | Anzeigen, Rotation, Zählung | Stufe 8 |

## Regeln

1. **Fachmodule greifen nur auf den Kern zu, nicht direkt aufeinander.** Ausnahme laut Architektur: Das Gästeportal übergibt Gästebestellungen an das Saalmodul, die Werbung wird im Gästeportal angezeigt. Solche Verbindungen laufen über klar benannte Schnittstellen.
2. **Jede Tabelle eines Moduls trägt die Mandantenkennung** und wird über den zentralen Filter des Kerns getrennt (E14a).
3. **Jedes Modul bringt seine eigenen Tests mit.**
4. Aufbau eines Moduls (festgelegt mit Stufe 1a am Kern, gilt für alle Module):

   | Ordner/Datei | Inhalt |
   |---|---|
   | `XyServiceProvider.php` | bindet Migrationen, Ansichten, Adressen und Befehle ein (Eintrag in `bootstrap/providers.php`) |
   | `routes.php` | Adressen des Moduls |
   | `Datenbank/Migrationen` | Tabellen des Moduls |
   | `Models` | Datenmodelle |
   | `Dienste` | wiederverwendbare Fachlogik |
   | `Http/Controllers`, `Http/Middleware` | Seitensteuerung, Zugriffsprüfungen |
   | `Konsole` | Server-Befehle (`php artisan vp:…`) |
   | `Nachrichten` | E-Mails |
   | `Ansichten` | Seitenvorlagen, Namensraum `modulname::` |

5. **Mandantengebundene Modelle** verwenden den Baustein `GehoertZuMandant` (Kern). Er filtert jede Abfrage automatisch auf den aktiven Verein, setzt die Vereinskennung beim Anlegen und verweigert Abfragen und Speichern ohne aktiven Verein. Den Filter nie mit `withoutGlobalScopes()` umgehen; für Arbeiten in einem bestimmten Verein gibt es `Mandantenkontext::fuer($verein, fn () => …)`.
6. Wichtige Vorgänge werden mit `Protokoll::schreibe('bereich.vorgang', $ziel, [...])` ins unveränderbare Protokoll geschrieben.

Die vollständige Architektur steht im Architekturdokument (Claude-Projekt „Ticketverkauf“, `claude/Architekturdokument.md`).
