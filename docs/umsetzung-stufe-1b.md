# Technische Notizen – Stufe 1b (Plattformkern, Teil 2)

Freigegeben am 24.10.2026 (P-5.1b). Umfang: **Rechte und Rollen, Vereinsadmin, Modulfreischaltung, Vereinsdesign.**
Fachliche Grundlage: E19, E19a, E22, E22a, E73, E76, E76a sowie die vorab geklärten Punkte E92–E99.

## Technische Entscheidungen innerhalb der Stufe

| Nr. | Entscheidung | Begründung |
|---|---|---|
| T1b-1 | **Rechtekatalog im Programmcode** (`Rechte/Rechtekatalog.php`), vergebene Rechte in der Tabelle `rechtevergaben` (mandantengebunden). Rollen sind Vorlagen: Beim Übernehmen werden einzelne Rechte gesetzt, die danach pro Person anpassbar sind. | E19, E73. Ein Recht ohne passende Programmfunktion ergibt keinen Sinn – deshalb Katalog im Code, Vergabe in der Datenbank. |
| T1b-2 | Jedes Recht ist als Laravel-„Gate“ prüfbar (`can:recht`, `@can`). Die Prüfung kombiniert: Verein aktiv · Person Mitglied · Modul freigeschaltet · Recht vergeben (Super-Admin: alle). | E19a (serverseitig), E96. |
| T1b-3 | **Modulfreischaltung gilt auch für den Super-Admin:** Ist ein Modul aus, ist die Funktion im Verein für niemanden nutzbar. | Ein ausgeschaltetes Modul soll im Verein nicht „halb“ existieren. |
| T1b-4 | Zusätzliches Recht **„Protokoll des Vereins ansehen“** im Bereich Verwaltung (in der Vorlage „Vereinsadmin“ an). | Umsetzung von E84/E97, damit es gezielt vergeben werden kann. |
| T1b-5 | Schutzregeln (E19a, E93): Ein Vereinsadmin ändert nur Rechte, die er selbst hat; Rechte der anderen Person, die er nicht hat, bleiben unverändert; eigene Rechte sind gesperrt; jede Änderung wird mit „hinzu/entfernt“ protokolliert. | E19a. |
| T1b-6 | Personen eines fremden Vereins sind in der Vereinsverwaltung **nicht erreichbar (404)** – nicht einmal ihre Existenz wird bestätigt. Beim Hinzufügen über die exakte E-Mail-Adresse wird nur gemeldet, dass das vorhandene Konto hinzugefügt wurde. | E22a. |
| T1b-7 | Beim Entfernen einer Person aus einem Verein werden ihre Rechte in diesem Verein mitgelöscht – egal ob über die Vereinsverwaltung oder die Benutzerverwaltung des Super-Admins. Das Konto bleibt bestehen. | Keine „verwaisten“ Rechte. |
| T1b-8 | Grundlage für **vorstellungsbezogene Rechte**: Spalte `vorstellung_id` (leer = alle Veranstaltungen). Auswahl in der Oberfläche ab Stufe 2. | E95. |
| T1b-9 | **Logo:** Prüfung auf Dateiendung UND tatsächlichen Dateiinhalt (PNG/JPG/WebP), max. 2 MB, max. 4000 × 4000 Pixel. Ablage außerhalb des öffentlichen Ordners, Auslieferung über `/verein/KÜRZEL/logo` mit Schutz-Kopfzeilen. Alte Datei wird beim Ersetzen gelöscht. | E98, Architektur Abschnitt 13 (Uploads). |
| T1b-10 | **Farben:** nur `#RRGGBB`, als CSS-Variablen im Seitenkopf gesetzt, nur wenn ein Verein aktiv ist. Schriftfarbe automatisch nach WCAG-Kontrast (schwarz/weiß). Hinweis, wenn die Primärfarbe auf Weiß einen Kontrast unter 3 : 1 hat oder Primär- und Sekundärfarbe kaum unterscheidbar sind. | E76, E76a, E99. |
| T1b-12 | Die Prüfung „nur Super-Admin“ läuft **vor** dem Laden von Datensätzen aus der Adresse. Unbefugte erhalten immer „Kein Zugriff“ und erfahren nicht, ob ein Benutzer oder Verein existiert. | Datensparsamkeit; aufgefallen durch die MySQL-Tests auf GitHub. |
| T1b-11 | Der Super-Admin wechselt über „Personen & Rechte“ in der Vereinsliste in den Verein und nutzt dieselbe Verwaltung wie der Vereinsadmin – mit allen Rechten. | Eine Oberfläche für beide, weniger Fehlerquellen. |

## Neue Tabellen / Spalten

`rechtevergaben`, `mandant_module`; `mandanten`: `logo_datei`, `logo_typ`, `farbe_primaer`, `farbe_sekundaer`.

## Abnahmeliste Stufe 1b (auf test.regional-meine-wahl.de)

Vorbereitung auf dem Server (siehe Anleitung im Chat):
1. `~/vp-einspielen.sh aktualisieren stufe-1b-rechte-design` und `~/vp-einspielen.sh test stufe-1b-rechte-design`

Im Browser als Super-Admin:
2. Verwaltung → Vereine → bei **TVM** „Design & Module“: dunkle Primärfarbe, helle Sekundärfarbe, Logo hochladen, Module Ticket + Saal anhaken.
3. Bei **SVB** eine andere Farbe wählen. Einmal ein sehr helles Gelb probieren → Hinweis erscheint, gespeichert wird trotzdem.
4. Start → Verein „TVM“ wählen → Kopfzeile zeigt Logo, Name und Vereinsfarben. Verein „SVB“ wählen → andere Farben.
5. Vereine → TVM → „Personen & Rechte“ → Testbenutzer → **Vorlage „Vereinsadmin“ übernehmen**.

Als Testbenutzer (privates Fenster):
6. Er landet in TVM mit TVM-Farben; Menüpunkt **„Verein“** ist da, „Verwaltung“ nicht.
7. Verein → Personen & Rechte → **Person hinzufügen** mit einer weiteren E-Mail-Adresse (+ Name) → Einladung kommt an.
8. Der neuen Person ein paar Rechte geben → „Druckaufträge drucken“ ist ausgegraut („können Sie nicht vergeben“).
9. Die eigenen Rechte sind nicht änderbar.
10. Vereinsimpressum pflegen → `/verein/TVM/impressum` zeigt den Text.
11. Verein → Protokoll zeigt nur TVM-Vorgänge.
12. `test.regional-meine-wahl.de/verwaltung` → „Kein Zugriff“. SVB taucht nirgends auf.
13. Als Super-Admin das Modul **Ticket** bei TVM ausschalten → beim Testbenutzer steht bei den Ticket-Rechten „Modul nicht freigeschaltet, Recht ruht“; wieder einschalten → alles wie vorher.
