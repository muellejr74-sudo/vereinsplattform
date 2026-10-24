# Nachtrag: Reihenfolge per Ziehen (Stationen, Ticket-Kategorien)

Freigegeben am 24.10.2026 als eigener kleiner Schritt (E133, Variante B).

| Nr. | Entscheidung | Begründung |
|---|---|---|
| TN-1 | **Neues Paket SortableJS 1.15.7** (MIT) als einzelne Datei unter `public/js/vendor/`, Lizenz daneben; im „Fallback“-Modus, damit Ziehen am PC und am Handy gleich funktioniert. Kleines eigenes Skript `public/js/reihenfolge.js`. | Ziehen mit dem Finger ist ohne Hilfsmittel auf Handys unzuverlässig. |
| TN-2 | Zusätzlich **▲ ▼** an jeder Zeile (funktioniert auch, wo Ziehen schwierig ist). Jede Änderung wird **sofort gespeichert** (Rückmeldung „Reihenfolge gespeichert“). | Einfache Bedienung, kein Speichern-Knopf vergessen. |
| TN-3 | **Kompakte Liste**: eine Zeile je Eintrag; Antippen klappt Bearbeiten (Name, Art bzw. Preis, Löschen) auf. Das Zahlenfeld „Reihenfolge“ entfällt; beim Bearbeiten bleibt die Position erhalten, neue Einträge kommen ans Ende. | Am Handy rund viermal kürzer, selbsterklärend. |
| TN-4 | Speichern der Reihenfolge nur mit den bisherigen Rechten („Stationen verwalten“ bzw. „Kategorien und Preise“) und nur für Einträge des eigenen Vereins bzw. der Veranstaltung. | Sicherheit wie bisher. |

## Abnahme

1. Verein → **Stationen**: eine Station am Griff ☰ nach oben ziehen (Handy und PC), eine mit ▲ ▼ verschieben → „Reihenfolge gespeichert“; Seite neu laden → Reihenfolge bleibt.
2. Station antippen → bearbeiten, speichern → sie bleibt an ihrer Stelle.
3. Tickets → Veranstaltung → **Kategorien** genauso; beim Karten verkaufen erscheinen sie in der neuen Reihenfolge.

## Ebenfalls in diesem Schritt: Etikett (E134)

Auf dem Ticket-Etikett entfällt das Wort „Name:“ vor dem Namen; unter Datum und Uhrzeit steht neu „Einlass ab HH:MM Uhr“ (aus der Vorstellung; ohne Einlasszeit entfällt die Zeile). Abnahme: ein Etikett mit Namen drucken und prüfen.
