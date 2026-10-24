# Technische Notizen – Stufe 4a „Der Abend“ (Reservierungen, Vorkasse, Abendkasse, Stornieren, Einlass)

Freigegeben am 24.10.2026 (P-5.4). Fachliche Grundlage: E1, E4b, E40–E46a, E114, E114a sowie die vorab geklärten Punkte E119–E127 (Scanner, Telefonnummer, Einlass-Anzeige, Vorkasse mit Adressaufkleber).

## Technische Entscheidungen innerhalb der Stufe

| Nr. | Entscheidung | Begründung |
|---|---|---|
| T4a-1 | **Neues Paket jsQR 1.4.0** (Apache-2.0) als einzelne Datei unter `public/js/vendor/`, Lizenztext daneben. Eigener kleiner Scanner `public/js/scanner.js` (Kamera, Ton/Vibration, Hand-Scanner/Tastatur). Kein Build-Schritt. | E119. Funktioniert auf iPhone und Android ohne App. |
| T4a-2 | Die Kamera schickt denselben Code **nur erneut**, wenn er mindestens 2 Sekunden nicht vor der Kamera war. | Sonst würde eine länger gehaltene Karte nach dem Grün sofort als „schon eingelassen“ (Gelb) erscheinen. |
| T4a-3 | Karten entstehen an **einer Stelle** (`Kartenerzeugung`) – für Aufträge, Reservierungen, Vorkasse und Abendkasse; immer in einer Transaktion mit gesperrter Vorstellung. | Fortlaufende Nummern und Kontingent bleiben überall gleich sicher. |
| T4a-4 | **Kontingent** = Karten in belegenden Zuständen **plus** vorgemerkte Plätze aus digitalen und noch nicht bezahlten Vorkasse-Reservierungen. | E43: Reservierungen belegen sofort, auch ohne Karten. |
| T4a-5 | Gedruckte Reservierungen und bezahlte Vorkasse erzeugen einen **Druckauftrag ohne Bestätigungsschritt** (Art „Reservierung“ bzw. „Versand“). Die Druckstelle druckt; bei Reservierungen heißt es danach „an der Abendkasse hinterlegt“, bei Vorkasse „Versendet“. | Die Plätze sind schon gesichert – ein zweites Bestätigen wäre doppelt. |
| T4a-6 | **Vorkasse:** Betrag und Preise werden bei der Reservierung festgehalten. Verwendungszweck „KÜRZEL R-NNNN Name“. Reservierungsnummer je Verein fortlaufend. Die Karten gelten nach „Bezahlt“ als bezahlt (Überweisung) und nach „Versendet“ als **verkauft**. | E124, eindeutige Zuordnung auf dem Kontoauszug. |
| T4a-7 | Erscheint ein Vorkasse-Gast **ohne Zahlung** an der Abendkasse, kann die Reservierung dort wie eine digitale ausgegeben und bar/mit Karte kassiert werden (mit deutlichem Hinweis). | Kein Gast wird wegen einer verspäteten Überweisung abgewiesen. |
| T4a-8 | **Reservierung stornieren/umstellen** dürfen: Recht „Reservierungen“, Recht „Zahlungseingang vermerken“ oder die Vorverkaufsstelle, die sie angenommen hat. Nicht abgeholte am Abend: Recht „Abendkasse“ (E41). | Wer annimmt, kann auch korrigieren; Karten-Storno bleibt beim sensiblen Recht. |
| T4a-9 | **Ticket-Einstellungen:** Voreinstellung, Minuten für „nicht abgeholt“ und Zahlungsfrist mit Recht „Reservierungen“; **Bankverbindung nur mit Recht „Zahlungseingang vermerken“**. IBAN wird geprüft (Prüfsumme), Änderungen werden protokolliert (nur die letzten 4 Stellen). | Schutz gegen das unbemerkte Ändern der Kontonummer. |
| T4a-10 | **Stornieren** sammelt Karten zuerst in einer Liste (Scan oder Eintippen), storniert wird erst mit einem eigenen Knopf. Eintippen der Nummer **ohne Prüfzeichen** ist hier erlaubt; beim Einlass nie. Verkaufte Karten zeigen den Hinweis „Geld zurückgeben“. | Ein versehentlicher Scan richtet keinen Schaden an; beschädigte Karten bleiben stornierbar. |
| T4a-11 | **Einlass** gültig für gedruckte, ausgegebene und verkaufte Karten der angemeldeten Vorstellung. Gleichzeitige Scans derselben Karte: nur einer wird grün. Am Einlass werden **keine Namen** angezeigt. Einzelne Scans werden nicht ins Protokoll geschrieben – Zeitpunkt und Station stehen an der Karte. | E122; das Protokoll bleibt lesbar. |
| T4a-12 | Abendkasse-Verkauf und abgeholte digitale Reservierungen bekommen Ticketnummern (ohne Etikett) und gelten sofort als eingelassen. Teilabholung: Übrige Plätze werden frei, übrige hinterlegte Karten storniert. | E44, E114a, E123; die Zahlen für 4b stimmen. |
| T4a-13 | Hinterlegte **Freikarten-Stapel des Vereins** können an der Abendkasse ausgegeben werden (auch teilweise). | E114. |
| T4a-15 | **Abendkasse mit Startmenü** (Wunsch bei der Abnahme, 24.10.2026): große Knöpfe „Karten direkt verkaufen“, „Reservierung abholen“, „Gedruckte Karte verkaufen“, „Freikarten ausgeben“ (nur wenn vorhanden), „Nicht abgeholte Reservierungen“. Jede Aufgabe hat eine eigene kurze Seite; nach jedem Vorgang geht es zurück ins Menü. | Am Handy schneller, kein Scrollen. |
| T4a-16 | **Plus-/Minus-Knöpfe** an allen Mengenfeldern (Abendkasse, Abholung, Reservierung, Bestellung, Freikarten) – kleines Skript `public/js/mengen.js`; ohne JavaScript bleibt das normale Zahlenfeld. | Wunsch bei der Abnahme: am Handy schneller. |
| T4a-17 | **Sofort-Prüfung gegen die freien Plätze** (Abendkasse, Reservierung, Bestätigen der Druckstelle): Übersteigt die gewählte Menge die freien Plätze, erscheint sofort ein roter Hinweis, Plus und Absenden sind gesperrt. Der Server prüft beim Absenden weiterhin unter Sperre. | Wunsch bei der Abnahme: Überbuchung sofort sehen. |
| T4a-18 | **Kompakter Kopf der Abendkasse:** „Abendkasse“ groß mit Wechsel-Symbol rechts, darunter nur Datum und Uhrzeit (Veranstaltungsname als Hinweis beim Antippen/Überfahren), „Frei“ groß mit belegt/eingelassen klein in einer Zeile. | Wunsch bei der Abnahme: Knöpfe rücken nach oben. |
| T4a-19 | **Abendkasse nur im Zeitfenster** der Vorstellung (E100: 3 Stunden vor Einlass bis 3 Stunden nach Ende bzw. 4:00 Uhr) – wie die Stationen. Außerhalb: Hinweis „öffnet am … um …“, alle Kassenvorgänge gesperrt. | Wunsch bei der Abnahme: kein Verkauf für eine Abendkasse in 3 Tagen. |
| T4a-20 | **Einlass auch an der Abendkasse** (O50, Variante A): Knopf „Einlass scannen“ im Abendkassen-Menü für Personen mit Recht „Abendkasse“, ohne Einsatz-Code; vermerkt als „Abendkasse (Name)“. Die Einlass-Station bleibt. | Kleine Abende mit einer Person für Kasse und Einlass. |
| T4a-21 | **Einlass mit Kamera hält nach jedem Code an:** Ergebnis bildschirmfüllend Grün/Gelb/Rot, Kamera aus, Knopf „Weiter scannen“. Eine noch vor der Kamera liegende Karte wird erst nach dem Wegnehmen erneut geprüft. Stornieren bleibt Dauer-Scan. | Wunsch bei der Abnahme: Ergebnis war unten nicht zu sehen. |
| T4a-22 | **Kartenbestellung und Freikarten-Anforderung höchstens so viele wie frei:** Sofort-Hinweis beim Eintippen und Sperre auf dem Server. Belegt wird weiterhin erst mit der Bestätigung (E30). | Wunsch bei der Abnahme. |
| T4a-23 | **Verkaufsstelle druckt selbst** (E128): Haken je Verkaufsstelle; Seite „Karten verkaufen und drucken“ (Plus/Minus, Sofort-Prüfung, optional Name). Karten entstehen sofort als „ausgegeben an Stelle X“ (ohne Druckstelle, unter Sperre gegen Überbuchung), Etiketten-PDF und Nachdruck eigener Karten. Eigener Kartenauftrag mit Vermerk „Selbstdruck“, damit die Abrechnung je Stelle (4b) stimmt. | Freigegeben am 24.10.2026. |
| T4a-14 | Rechte: „Abendkasse“ und „Stornieren“ aktiv; **neues sensibles Recht „Zahlungseingang vermerken“** (E125). „Auswertungen“ und „Abschluss“ folgen mit Stufe 4b. | |

## Ehrlicher Hinweis zu den Tests

Alle Abläufe sind mit automatischen Tests abgedeckt (u. a. Kontingent mit Reservierungen, Teilabholung, Vorkasse mit Adressaufkleber, Rechte, Storno-Regeln, Einlass Grün/Gelb/Rot). Der Kamera-Scanner wurde im Browser mit einer simulierten Kamera geprüft (QR-Code einer echten Karte → Grün, erneut vorgehalten → Gelb). Auf echten Handys (iPhone/Android) ist er noch nicht ausprobiert – das gehört zur Abnahme. Gleichzeitige Zugriffe sind durch Datenbanksperren abgesichert; die Tests prüfen sie nacheinander.

## Neue Tabellen und Felder

`verkaufsstellen.druckt_selbst` (E128).

`ticket_einstellungen`, `reservierungen`, `reservierungspositionen`; an `tickets`: `reservierung_id`, `zahlungsart`, `verkauft_am`, `verkauft_von`, `verkauf_ueber`, `eingelassen_am`, `eingelassen_durch`, `storniert_am`, `storniert_von`.

## Abnahmeliste Stufe 4a (auf test.regional-meine-wahl.de)

Vorbereitung: Einspielen des Zweigs `stufe-4a-abend` (Anleitung im Chat). Als Vereinsadmin dir unter Verein → Personen & Rechte zusätzlich geben: **„Stornieren“** und **„Zahlungseingang vermerken“** (beide sensibel). Eine Vorstellung **für heute** anlegen, Beginn in etwa 30 Minuten, Kontingent z. B. 300.

1. Tickets → **Einstellungen**: Bankverbindung eintragen.
2. **Vorverkauf:** Die Bäckerei bestellt 20 Karten → bestätigen, drucken, übergeben.
3. **Rückgabe:** Tickets → **Stornieren** → 5 dieser Karten mit dem Handy scannen (Kamera einschalten) → Liste prüfen → „5 Karten stornieren“.
4. **Reservierungen:** „Meier“ digital 4 Karten · „Liederkranz“ gedruckt 20 Karten · „Schulz“ digital 2 Karten · „Huber“ **Vorkasse** 3 Karten mit Anschrift. Bei Huber Betrag, Verwendungszweck und IBAN prüfen.
5. Huber: **„Bezahlt, zum Versand freigeben“** → Druckstelle: Etiketten-PDF beginnt mit dem **Adressaufkleber** → auf dem BP4X drucken → „Druck war erfolgreich“ → „Versendet“.
6. Liederkranz: Druckauftrag drucken („an der Abendkasse hinterlegt“).
7. **Abendkasse:** Meier holt ab und zahlt **bar** · vom Liederkranz kommen nur **17**, Zahlung **mit Karte** · **3 Spontanverkäufe**.
8. Ab Vorstellungsbeginn: Schulz unter „Nicht abgeholte Reservierungen“ stornieren.
9. **Einlass:** Auf einem zweiten Handy mit dem Einsatz-Code als „Einlass“ anmelden → Kamera einschalten → eine Bäckerei-Karte scannen (**Grün**), dieselbe noch einmal (**Gelb**), eine stornierte (**Rot**).
10. Tickets → Veranstaltung: Belegt und Frei stimmen (im Beispiel belegt 15 + 4 + 17 + 3 + 3 = 42).
