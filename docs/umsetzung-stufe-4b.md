# Technische Notizen – Stufe 4b „Der Abschluss“

Freigegeben am 24.10.2026 (P-5.4). Fachliche Grundlage: E2a, E4, E4a, E4b, E79, E79c sowie die vorab geklärten Punkte E129–E132.

## Technische Entscheidungen innerhalb der Stufe

| Nr. | Entscheidung | Begründung |
|---|---|---|
| T4b-1 | **Abschluss** als eigener Eintrag je Vorstellung (`ticket_abschluesse`). Eine **zentrale Sperre** (`Abschlusssperre`) wird in allen Kartenvorgängen geprüft: Anfordern, Bestätigen, Reservierungen, Abendkasse, Selbstdruck, Stornieren, Einlass, Nachbuchen, Kontingent. | E4b an einer Stelle, überall gleich. |
| T4b-2 | **Vorab-Prüfung** (E131) zeigt vier Arten offener Reste: offene Reservierungen, nie bestätigte Kartenaufträge, nie ausgegebene Karten (erzeugt/gedruckt ohne Zahlung, dazu der nicht verkaufte Bestand der Abendkasse) und bezahlte, noch nicht versendete Vorkasse. Die ersten drei lassen sich mit einem Klick stornieren bzw. ablehnen; bezahlte Vorkasse muss erst gedruckt und versendet werden. | Abschluss nur mit sauberem Stand. |
| T4b-3 | Beim Abschluss werden **ausgegebene Karten zu „verkauft“** (E4) und mit `verkauf_ueber = abschluss` markiert. **Rückgängig** (nur Super-Admin, Grund mind. 5 Zeichen) setzt genau diese Karten wieder auf „ausgegeben“; Grund und Zeitpunkt des ursprünglichen Abschlusses stehen im Protokoll. | E4a ohne Datenverlust umkehrbar. |
| T4b-4 | **Nachbuchen** (E129) ab Beginn der Vorstellung bis zum Abschluss, Recht „Abschluss durchführen“ oder „Abendkasse“. Verkäufe mit Zahlungsart bar/Karte/Überweisung, Freikarten mit Namen; Karten mit Nummer ohne Etikett, als eingelassen; Kontingent wird geprüft. | Internetausfall, vergessene Verkäufe, Freikartenliste vollständig. |
| T4b-5 | **Auswertung** (E79): „zählende“ Karten = nicht storniert und an Gäste/Stellen gegangen oder bezahlt; nicht verkaufter Abendkassen-Bestand zählt nicht. **Zahlungsarten**: bar, Karte, Überweisung und „Abrechnung Vorverkaufsstellen“ (Karten der Stellen ohne eigene Zahlungsart). **Abrechnung je Stelle**: ausgegeben − zurückgegeben (storniert) = verkauft; Freikarten getrennt. | „bar“ = was in der Kasse sein muss. |
| T4b-6 | **Berichte**: Abschlussbericht und Abrechnung je Stelle als A4-PDF (mit Unterschriftszeile), CSV für Excel (Semikolon, UTF-8 mit BOM, Werte mit = + - @ werden entschärft): alle Karten, Abrechnung, Freikarten. Rechte: „Auswertungen ansehen“ oder „Abschluss durchführen“. | E79c. |
| T4b-7 | **Notfallliste** (E2a) als A4-PDF: offene Reservierungen mit Name, Nummer, Karten, Betrag (ohne Telefon, E120) und alle gültigen Kartennummern zum Abhaken. Rechte: Abendkasse, Auswertungen oder Abschluss. Link im Abendkassen-Menü und bei jeder Vorstellung. | Internetausfall. |
| T4b-8 | **E-Mail an die Druckstelle** (E132) an alle Vereinsmitglieder mit dem Recht „Druckaufträge drucken“ (nicht vorstellungsbezogen), aktiv und nicht abgemeldet; nach erfolgreichem Speichern; Fehler beim Versand werden nur protokolliert (Log), der Auftrag bleibt. Inhalt: Art, Stelle bzw. Reservierungsnummer, Vorstellung, Anzahl, Link – **keine Namen, Telefonnummern oder Anschriften**. Abschalten unter „Mein Konto“ (Voreinstellung an). Nicht bei Selbstdruck. | Wunsch des Betreibers. |
| T4b-9 | **Erinnerung** „Abschluss offen“ (E130) in der Ticket-Übersicht für vergangene Vorstellungen mit Karten, mit Tagen seit der Vorstellung. | Kein Druck, nur Hinweis. |

## Neue Tabellen

`ticket_abschluesse`, `ticket_mail_abmeldungen`.

## Abnahmeliste Stufe 4b (auf test.regional-meine-wahl.de)

Vorbereitung: Einspielen des Zweigs `stufe-4b-abschluss`. Dir selbst die Rechte **„Auswertungen ansehen“** und **„Abschluss durchführen“** geben (letzteres ist sensibel). Für die E-Mail-Probe: eine Person mit „Druckaufträge drucken“ und echter E-Mail-Adresse.

1. Als Verkaufsstelle eine Kartenbestellung anlegen → die Druckstelle erhält eine **E-Mail** (ohne Kundennamen). Unter „Mein Konto“ abschalten → keine E-Mail mehr.
2. Bei einer begonnenen oder vergangenen Vorstellung → **Nachbuchen**: 3 Karten bar und 2 Freikarten „Sponsor“.
3. **Auswertung** öffnen: Einnahmen nach bar / Karte / Überweisung / Vorverkaufsstellen prüfen, Abrechnung der Bäckerei prüfen, **Abschlussbericht (PDF)** und **CSV** öffnen (Excel).
4. **Notfallliste** öffnen (Abendkassen-Menü oder bei der Vorstellung): keine Telefonnummern.
5. **Abschluss**: offene Reste werden angezeigt → „Alle stornieren“ / „Alle ablehnen“ → **Abschluss durchführen**.
6. Danach versuchen zu stornieren, nachzubuchen oder an der Abendkasse zu verkaufen → **gesperrt**. In der Ticket-Übersicht verschwindet „Abschluss offen“.
7. Als **Super-Admin**: Abschluss mit Grund rückgängig machen → steht im Protokoll; die Vorstellung ist wieder bearbeitbar.
