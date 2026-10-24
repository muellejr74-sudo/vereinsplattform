# Technische Notizen – Stufe 2 (Gemeinsame Stammdaten, Stationen, QR-Codes)

Freigegeben am 24.10.2026 (P-5.2). Fachliche Grundlage: E29–E36, E52–E54a, E68–E74 sowie die vorab geklärten Punkte E100–E109.

## Technische Entscheidungen innerhalb der Stufe

| Nr. | Entscheidung | Begründung |
|---|---|---|
| T2-1 | **Neues Paket `setasign/fpdf`** (PDF-Erzeugung, keine weiteren Pakete). Benötigt die PHP-Erweiterungen GD und zlib. | Schlankste Lösung für die A4-Aufkleber (E104); wird ab Stufe 3 für Ticket-Etiketten und Berichte gebraucht. |
| T2-2 | QR-Codes im PDF werden als **Vektorgrafik** gezeichnet (keine Bilder). Geprüft: Alle Codes einer gedruckten Seite werden von einem QR-Leser erkannt. | Gestochen scharf in jeder Größe, kleine Dateien, keine Bildbearbeitung auf dem Server. |
| T2-3 | Code-Länge 10 Zeichen aus 31 gut lesbaren Zeichen (ohne 0/O, 1/I/L). Link-Form `/t/CODE`. | E105: kurz, nicht erratbar, ohne Vereins- oder Tischdaten. |
| T2-4 | **Zeitfenster** (E100) zentral in der Vorstellung (`fensterBeginn`, `fensterEnde`, `istImZeitfenster`); „laufende Vorstellung“ einer Veranstaltung als Grundlage für E37a. | Eine Stelle für alle späteren Module (Saal-Code, Gästeportal). |
| T2-5 | Vorstellungen speichern Einlass, Beginn und Ende als Datum+Uhrzeit; ein Ende vor dem Beginn bedeutet „nach Mitternacht“. | Vorstellungen über Mitternacht (z. B. Bälle). |
| T2-6 | **Bewusste vereinsübergreifende Zugriffe** (sonst gilt immer der Mandantenfilter): Einsatz-Code suchen (Personal wählt keinen Verein), Scan-Weiche, Übernahme eines Tisches (E74) und die Betreiber-Übersicht. Jeweils genau an einer Stelle im Dienst `Einsatz` bzw. `Tischverwaltung`. | Fachlich nötig; durch die Bündelung überprüfbar. |
| T2-7 | Übernahme eines Tisches: still protokolliert beim abgebenden **und** beim übernehmenden Verein, jeweils **ohne Namen** des anderen Vereins (nur Kennnummern; der Super-Admin kann sie zuordnen). Die Tischnummer des anderen Vereins wird nicht vorbelegt. | E74a ohne gegenseitige Einblicke der Vereine. |
| T2-8 | Tischnummer muss **innerhalb einer Veranstaltung eindeutig** sein (Einheitscode ohne Nummer). | Verhindert zwei „Tisch 5“ am selben Abend. |
| T2-9 | Eine Zuordnung gilt bis einschließlich Enddatum; ab Mitternacht danach wird sie bei jeder Abfrage als frei behandelt. Der Cronjob (täglich 00:05) räumt die Einträge auf. | E107 – korrekt auch, wenn der Cronjob einmal später läuft. |
| T2-10 | **Einsatz-Code** mit Gate `einsatz.verwalten` = Recht „Stationen verwalten“ **oder** Saal-Recht „Saal-Code/Einsatz-Code erzeugen“. | E73 ordnet den Einsatz-Code dem Saal zu; Vereine ohne Saalmodul brauchen ihn aber auch für den Einlass. Nichts wird weggenommen, nur zusätzlich erlaubt. |
| T2-11 | Sperre beim Einsatz-Code: **5 Fehlversuche je Gerät/Anschluss → 15 Minuten**, bewusst ohne gemeinsamen Zähler für alle. | E102; ein gemeinsamer Zähler würde es Unbefugten erlauben, alle Stationen auszusperren. |
| T2-12 | Ein neu erzeugter Einsatz-Code meldet bereits angemeldete Geräte **nicht** ab; dafür gibt es „Gerät abmelden“. | Kein versehentliches Aussperren mitten im Abend. |
| T2-13 | Stationsgerät: verschlüsseltes Cookie (nur HTTPS, nicht per JavaScript lesbar), gültig bis Ende des Zeitfensters, serverseitig widerrufbar. | E29a. |
| T2-14 | **Vorstellungsbezogene Rechte** (E95): auf der Rechte-Seite einer Person „Rechte nur für einzelne Vorstellungen“; nur Modulrechte (Ticket, Saal, Gästeportal, Werbung), nur im Rahmen der eigenen Rechte. | Verwaltungsrechte für einzelne Abende ergeben keinen Sinn. |
| T2-15 | **Bilder für Veranstaltungen** kommen mit Stufe 7 (Recht „Gästeportal-Inhalte pflegen“). In Stufe 2: Name, Kurzname (für das Ticket-Etikett, E77) und Beschreibung. | Bilder erscheinen nur im Gästeportal. |
| T2-16 | Standard-Stationen werden genau einmal angelegt (Vermerk am Verein); gelöschte Stationen kommen nicht zurück. Der letzte Saal kann nicht gelöscht werden, ebenso kein Saal, der von Vorstellungen benutzt wird. | E101, E103. |
| T2-17 | Datensätze aus der Adresse werden erst geladen, **nachdem** der aktive Verein feststeht (Reihenfolge der Prüfschritte). | Sonst würde der Mandantenfilter alles als „nicht gefunden“ melden. |

## Neue Tabellen

`orte`, `saele`, `veranstaltungen`, `vorstellungen`, `stationen`, `stationsbesetzungen`, `stationsgeraete`, `qr_codes`, `tischzuordnungen`; `mandanten.stationen_angelegt_am`.

## Abnahmeliste Stufe 2 (auf test.regional-meine-wahl.de)

Vorbereitung: Einspielen des Zweigs `stufe-2-stammdaten` (Anleitung im Chat).

Als Vereinsadmin von TVM (Menü „Verein“):
1. **Veranstaltungen** → Veranstaltung anlegen → **drei Vorstellungen** anlegen. Eine davon **heute** mit Einlass in etwa einer Stunde (für den Einsatz-Test).
2. **QR-Codes & Tische** → 10 Codes erzeugen → „Ausgewählte als PDF drucken“ → auf Papier ausdrucken (Schnittlinien an).
3. Auf dem **Handy** im Browser anmelden (Verein TVM aktiv). Dann mit der **normalen Kamera-App** einen ausgedruckten Code scannen → Formular „Tisch zuordnen“ → Tischnummer eingeben → Speichern. Für **5 Tische** wiederholen.
4. Veranstaltung öffnen → 5 Tische mit „Gültig bis“ = Tag nach der letzten Vorstellung.
5. Einen Code auf einem Handy **ohne Anmeldung** scannen → Platzhalterseite der Veranstaltung mit Tischnummer.
6. Vorstellung von heute → **Personal & Einsatz-Code** → Code erzeugen → bei „Bedienung 1“ den Namen **Petra** eintragen.
7. **Testhandy** (ohne Anmeldung): `test.regional-meine-wahl.de/einsatz` → Code eingeben → **Bedienung 1** wählen → Seite zeigt „Bedienung 1 (Petra)“.
8. Auf einem zweiten Handy erneut anmelden → „Bedienung 1“ ist **belegt**, „Ausschank“ lässt sich mehrfach wählen.
9. In „Personal & Einsatz-Code“ das Gerät abmelden → das Testhandy landet wieder bei der Code-Eingabe.

Als Super-Admin:
10. Verwaltung → **Betreiber-Tische** → z. B. 3 Tische anlegen und als PDF drucken.
11. Einen Betreiber-Tisch als TVM-Vereinsadmin scannen und zuordnen → in der Betreiber-Übersicht steht „TVM · Veranstaltung · Tisch …“.
12. Denselben Tisch als Admin eines anderen Vereins scannen und zuordnen → er wechselt **ohne Rückfrage** dorthin; im Protokoll steht „Tisch übernommen“ bzw. „abgegeben“.

> Hinweis: Auf Test gedruckte Aufkleber zeigen auf die Test-Adresse (E105). Die endgültigen Aufkleber werden erst aus der Live-Umgebung gedruckt.
