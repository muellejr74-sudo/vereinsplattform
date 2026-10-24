# Technische Notizen – Stufe 1a (Plattformkern, Teil 1)

Freigegeben am 24.10.2026 (P-5.1a). Umfang laut P-5.1: **Vereine inkl. Mandantentrennung, Benutzer, Anmeldung inkl. Zwei-Faktor, Passwort-Zurücksetzen, Protokoll, Rechtstexte.**
Nicht enthalten (folgt in 1b): Rechte und Rollen, Vereinsadmin, Modulfreischaltung, Vereinsdesign (Logo, Farben).

## Technische Entscheidungen innerhalb der Stufe

| Nr. | Entscheidung | Begründung |
|---|---|---|
| T1a-1 | **Mandantentrennung** über den Baustein `GehoertZuMandant` + `MandantScope` + `Mandantenkontext`. Ohne aktiven Verein liefern Abfragen **nichts** und Speichern ist **nicht möglich**; die Vereinskennung eines Datensatzes ist nachträglich unveränderbar. | E14a; „im Zweifel nichts zeigen“ statt „im Zweifel alles zeigen“. |
| T1a-2 | Der aktive Verein steht in der Sitzung und wird **bei jeder Anfrage** gegen die Mitgliedschaft geprüft. Super-Admin darf jeden aktiven Verein wählen. | E19a (Prüfung auf dem Server), E21a. |
| T1a-3 | Bewusst **nicht** mandantengebunden: Mandanten, Benutzerkonten, Mitgliedschaften, Plattformtexte, Protokoll. Das Protokoll wird in der Ansicht ausdrücklich gefiltert (Super-Admin alles; Vereinsadmin-Ansicht folgt in 1b). | Architektur Abschnitt 4 (übergreifende Daten des Super-Admins). |
| T1a-4 | **Neue Benutzer** erhalten eine **Einladungs-E-Mail** (Link 3 Tage gültig) und legen ihr Passwort selbst fest. Niemand sonst kennt das Passwort. Bis dahin ist ein zufälliges, unbekanntes Passwort gesetzt. | Sicherheit; keine Passwörter per Telefon/Zettel. |
| T1a-5 | **Passwortregel:** mindestens 10 Zeichen (Super-Admin beim Anlegen per Server-Befehl: 12). | Einfach merkbar (Satz aus Wörtern), ausreichend sicher. |
| T1a-6 | **Sperre nach Fehlversuchen:** 5 Fehlversuche je E-Mail + Gerät → 15 Minuten; 10 Fehlversuche je Konto (alle Geräte) → 60 Minuten. Gleiche Regeln getrennt für den Zwei-Faktor-Code. Immer dieselbe Fehlermeldung (verrät nicht, ob es ein Konto gibt). | E16a, Architektur Abschnitt 13. |
| T1a-7 | **Zwei-Faktor (TOTP)** ohne Zusatzpaket selbst umgesetzt (RFC 6238, SHA-1, 6 Stellen, 30 s, ±1 Zeitschritt Toleranz). Jeder Code gilt nur einmal. Geprüft mit den offiziellen RFC-Prüfwerten. | Wenige Zeilen, keine Abhängigkeit, vollständig testbar. |
| T1a-8 | **Notfall-Codes:** 8 Stück, Form `XXXXX-XXXXX`, ohne verwechselbare Zeichen, nur als Prüfsumme gespeichert, einmalig angezeigt. Neue Codes nur mit Passwortbestätigung. | E16c. |
| T1a-9 | **„Gerät 30 Tage merken“** über ein verschlüsseltes Cookie (nur HTTPS, nicht per JavaScript lesbar) plus Eintrag in `vertraute_geraete`; jederzeit widerrufbar („Alle gemerkten Geräte abmelden“, Server-Befehl). | E16d. |
| T1a-10 | **Neues Paket `bacon/bacon-qr-code`** (QR-Codes als SVG, ohne Internetdienst). Wird ab Stufe 2/3 ohnehin für Tisch- und Ticket-Codes gebraucht. Abhängigkeit `dasprid/enum`. | Architektur: „QR/PDF-Erzeugung“ im Kern. Weit verbreitet, keine weiteren Abhängigkeiten. |
| T1a-11 | **Rechtstexte** in einfacher Textform (Markdown). Eingegebenes HTML wird nicht ausgeführt, unsichere Links (`javascript:`) werden entfernt. Öffentliche Adressen: `/impressum`, `/datenschutz`, `/verein/KÜRZEL/impressum`, `/verein/KÜRZEL/datenschutz`. | E83; Schutz vor eingeschleusten Inhalten (XSS). |
| T1a-12 | **Protokoll** unveränderbar auf Programmebene (Ändern/Einzellöschen wirft einen Fehler). Löschung nach 2 Jahren täglich um 03:15 über `model:prune` (Cronjob nötig, O40). Gespeichert werden u. a. wer, wann, was, Verein, IP. Keine Passwörter oder Codes. | E84, E89. |
| T1a-13 | Das **Vereinskürzel** (2–6 Großbuchstaben/Ziffern) ist in 1a noch änderbar. Sobald es Tickets gibt (Stufe 3), wird es gesperrt. | E45. |
| T1a-14 | Vereine werden **nicht gelöscht, nur deaktiviert**. Deaktivierte Benutzer werden sofort abgemeldet. | E11; Nachvollziehbarkeit. |
| T1a-15 | Deutsche Sprachdateien (`lang/de`) und deutsche Fehlerseiten. | Oberflächentexte deutsch. |
| T1a-16 | Einspielskript um `email` (Postfach eintragen + Test-Mail) und `befehl` (nur freigegebene Plattform-Befehle) erweitert – weiterhin ohne Texteditor. | Bedienbarkeit für den Betreiber. |

## Neue Tabellen

`mandanten`, `mitgliedschaften`, `vertraute_geraete`, `einladung_tokens`, `protokoll`, `plattformtexte`, `vereinstexte`; Erweiterung `users` (Super-Admin-Kennzeichen, aktiv, Zwei-Faktor-Felder, letzte Anmeldung).

## Abnahmeliste Stufe 1a (auf test.regional-meine-wahl.de)

Vorbereitung auf dem Server:
1. `~/vp-einspielen.sh aktualisieren stufe-1a-plattformkern` und `~/vp-einspielen.sh test stufe-1a-plattformkern`
2. `~/vp-einspielen.sh email test` – Postfach eintragen, Test-E-Mail kommt an
3. `~/vp-einspielen.sh befehl test vp:superadmin-anlegen`

Im Browser:
4. Anmelden → Zwei-Faktor mit der Authenticator-App einrichten → Notfall-Codes ausdrucken.
5. Abmelden, erneut anmelden → Code wird abgefragt. Einmal „Diesem Gerät 30 Tage vertrauen“ anhaken → beim nächsten Anmelden auf diesem Gerät kein Code.
6. Zwei Testvereine anlegen (z. B. `TVM`, `SVB`).
7. Einen Testbenutzer mit **eigener zweiter E-Mail-Adresse** anlegen und nur Verein `TVM` zuordnen → Einladungs-E-Mail kommt an → Passwort festlegen.
8. In einem anderen Browser (oder privatem Fenster) als Testbenutzer anmelden: kein Code nötig, landet direkt in `TVM`, sieht `SVB` nicht, „Verwaltung“ ist nicht sichtbar und `/verwaltung` zeigt „Kein Zugriff“.
9. „Passwort vergessen?“ für den Testbenutzer → E-Mail kommt an → neues Passwort funktioniert.
10. Fünfmal falsches Passwort → Sperre-Hinweis.
11. Plattform-Impressum/Datenschutz und das Vereinsimpressum von `TVM` pflegen → Links unten auf jeder Seite bzw. `/verein/TVM/impressum` zeigen die Texte.
12. Testbenutzer deaktivieren → er wird abgemeldet und kann sich nicht mehr anmelden.
13. Protokoll zeigt alle Vorgänge, Filter nach Verein funktioniert.

## Hinweise

- **Cronjob** (O40) muss eingerichtet sein, damit die 2-Jahres-Löschung läuft (README Schritt 7).
- Rechtstexte bitte rechtlich prüfen lassen (E83a).
