# Technische Notizen – Stufe 3 (Ticket A: Kategorien, Kontingent, Kartenaufträge, Etiketten)

Freigegeben am 24.10.2026 (P-5.3). Fachliche Grundlage: E24–E31, E39, E45, E77 sowie die vorab geklärten Punkte E110–E118 (BP4X-Etiketten, Stapel mit Namen, Freikarten intern).

## Technische Entscheidungen innerhalb der Stufe

| Nr. | Entscheidung | Begründung |
|---|---|---|
| T3-1 | **Ticketnummer** `KÜRZEL-JJ-NNNNN` (z. B. `TVM-26-00001`), fortlaufend je Verein, jedes Jahr neu ab 00001. Maßgeblich ist das Jahr der **Erzeugung**. Die Vergabe läuft in derselben Datenbank-Transaktion wie die Ticketanlage, mit gesperrter Zählerzeile (`nummernkreise`). | E113. Keine doppelten Nummern, auch wenn zwei Druckstellen gleichzeitig bestätigen. |
| T3-2 | **Prüfzeichen**: 6 zufällige, gut lesbare Zeichen je Karte, in der Datenbank gespeichert. Der QR-Code enthält `/k/NUMMER.PRÜFZEICHEN`. Die Prüfung vergleicht zeitsicher (`hash_equals`). | E14d/E112: Eine abgetippte oder erratene Nummer ist ohne Prüfzeichen wertlos. Gespeichert statt berechnet: bleibt gültig, auch wenn der Server-Schlüssel einmal erneuert werden muss. |
| T3-3 | Die **Prüfseite** `/k/…` zeigt nur „gültig / nicht gültig / storniert“, Veranstaltung, Termin und Nummer – **keine Namen**. Begrenzung: 60 Abrufe pro Minute und Anschluss. | Datenschutz; der eigentliche Einlass-Scan kommt mit Stufe 5. |
| T3-4 | **Freikarte ist eine Eigenschaft der Karte** (`ist_freikarte`, Preis 0), kein eigener Status. Je Veranstaltung gibt es automatisch die Kategorie „Freikarte“; sie kann nicht von Hand angelegt oder gelöscht werden. | Eine Freikarte durchläuft denselben Weg (gedruckt, ausgegeben, eingelassen) wie jede andere Karte. |
| T3-5 | Kartenstatus in dieser Stufe: **erzeugt → gedruckt → ausgegeben**. „Erzeugt“ ist ein interner Zwischenstand zwischen Bestätigung und erfolgreichem Druck. | Ein fehlgeschlagener Druck kann einfach wiederholt werden, ohne neue Nummern. |
| T3-6 | Erst die **Bestätigung durch die Druckstelle** belegt das Kontingent (E30). Ohne eingetragenes Kontingent kann nicht bestätigt werden. Die Druckstelle kann die Menge beim Bestätigen verringern. | Kein Überverkauf; klare Verantwortung. |
| T3-7 | Bestätigung in einer **Transaktion mit Sperren** auf Auftrag und Vorstellung: Freie Plätze werden erst nach dem Sperren gezählt. | Zwei gleichzeitige Bestätigungen können das Kontingent nicht gemeinsam überschreiten. |
| T3-8 | **Stapel mit Namen** (E114–E114b): Alle Karten eines Auftrags mit Namen (immer bei Freikarten) erhalten den Namen und die Position „3/20“. Die Position steht auf dem Etikett hinter dem Namen. | Abholung an der Abendkasse; auch Teilabholung ist erkennbar. |
| T3-9 | **Freikarten des Vereins** (E114c) laufen als Auftrag **ohne Verkaufsstelle** („Verein (intern)“) und brauchen das Recht „Reservierungen“. Verkaufskarten können nicht intern angefordert werden. | Freikarten für Ehrengäste, Sponsoren usw. ohne Vorverkaufsstelle. |
| T3-10 | **Etiketten-PDF** (E110/E111): eine Seite je Etikett, genau 101,6 × 38,1 mm, nur Schwarz, QR-Code als Vektorgrafik rechts in voller Höhe, Text links (Kurzname, Termin, Kategorie · Preis bzw. „Freikarte“, Name · Stapelposition, Ticketnummer). Zu lange Texte werden mit „…“ gekürzt. | Passend für den Labelident BP4X mit Thermo-Top-Etiketten. |
| T3-11 | **Nachdruck** (E117) mit derselben Nummer und demselben QR-Code; die Druckanzahl wird hochgezählt und protokolliert. | Ein beschädigtes Etikett darf keine zweite gültige Karte erzeugen. |
| T3-12 | Recht „Druckaufträge drucken“ ist **sensibel** und steckt nicht in der Vorlage für Vereinsadmins; es wird bewusst einzeln vergeben. Personen einer Verkaufsstelle brauchen kein eigenes Recht – die Zuordnung zur Stelle genügt. Sie sehen nur Aufträge ihrer eigenen Stellen. | E39/E45. |
| T3-13 | Preise werden in **Cent** gespeichert; Eingabe „12,50“ oder „12.50“. Abweichende Preise je Vorstellung überschreiben den Grundpreis der Kategorie. Eine bereits benutzte Kategorie kann nicht gelöscht werden; ein Kontingent kann nicht unter die belegten Plätze gesenkt werden. | Datenintegrität. |

## Ehrlicher Hinweis zu den Tests

Die automatischen Tests prüfen, dass das Kontingent nie überschritten wird und die Nummern lückenlos und eindeutig sind – aber **nacheinander**. Ein echter Test mit gleichzeitigen Zugriffen ist in der Testumgebung nicht möglich. Die Absicherung dafür sind die Datenbanksperren (T3-1, T3-7), die MySQL zuverlässig umsetzt.

## Neue Tabellen

`ticketkategorien`, `preisabweichungen`, `verkaufsstellen`, `verkaufsstellen_benutzer`, `kartenauftraege`, `auftragspositionen`, `tickets`, `nummernkreise`.

## Abnahmeliste Stufe 3 (auf test.regional-meine-wahl.de)

Vorbereitung: Einspielen des Zweigs `stufe-3-ticket-auftraege` (Anleitung im Chat). Beim Verein TVM muss das Modul **Ticket** eingeschaltet sein (Verwaltung → Verein → Module).

Als Vereinsadmin von TVM:
1. Verein → **Personen & Rechte** → dir selbst zusätzlich **„Druckaufträge drucken“** geben (sensibles Recht, nicht automatisch dabei).
2. Eine Veranstaltung mit einer Vorstellung und **Kontingent** (z. B. 300) anlegen – oder eine vorhandene nehmen.
3. Menü **Tickets** → Veranstaltung → Kategorien **Erwachsene 12,00** und **Kinder 6,00** anlegen. „Freikarte“ ist schon da.
4. Tickets → **Verkaufsstellen** → „Bäckerei Müller“ (Vorverkauf) anlegen und eine Testperson ankreuzen.

Als Testperson der Bäckerei (zweiter Browser oder Handy):
5. Tickets → **Karten bestellen**: 20 Erwachsene + 5 Kinder → Anfordern.
6. **Freikarten anfordern**: Name „Meier“, Anzahl 2 → Anfordern.
7. Unter „Aufträge“ stehen beide als „angefordert“; das Kontingent ist **noch nicht** belegt.

Wieder als Vereinsadmin (Druckstelle):
8. Tickets → Aufträge → Auftrag öffnen → **Bestätigen und Karten erzeugen**.
9. **Etiketten-PDF** öffnen und auf dem BP4X drucken. Druckeinstellungen: Papierformat **101,6 × 38,1 mm**, Skalierung **100 % / „Tatsächliche Größe“** (nicht „An Seite anpassen“), Ausrichtung nach Druckertreiber.
10. Etiketten prüfen: Text vollständig, Freikarten mit „Name: Meier · 1/2“ bzw. „2/2“. Einen QR-Code mit dem Handy scannen → „Gültige Eintrittskarte“, ohne Namen.
11. **Druck war erfolgreich** → **Karten übergeben**.
12. Tickets → Veranstaltung: **Belegt 27 (davon 2 Freikarten) · Frei 273**.
13. Bei einer Karte **Nachdruck** → gleiche Nummer; im Protokoll steht „Karte nachgedruckt“.
