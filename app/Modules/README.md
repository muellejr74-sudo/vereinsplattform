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
4. Ein Modul enthält bei Bedarf eigene Unterordner (z. B. `Models`, `Http`, `Dienste`, `Datenbank/Migrationen`, `Ansichten`, `routes.php`). Der genaue Aufbau wird mit Stufe 1 am Kern festgelegt und dann für alle Module übernommen.

Die vollständige Architektur steht im Architekturdokument (Claude-Projekt „Ticketverkauf“, `claude/Architekturdokument.md`).
