# Arbeitsregeln für dieses Repository

Dieses Repository enthält die **modulare Vereins- und Veranstaltungsplattform** (Betreiber: regional-meine-wahl.de).

## Verbindliche Grundregel

**Erst verstehen, dann planen, gemeinsam entscheiden, ausdrücklich freigeben und erst danach programmieren.**

- Es wird **nur innerhalb einer ausdrücklich freigegebenen Stufe** des Entwicklungsplans gearbeitet. Neue Funktionen, Module oder Architekturänderungen werden vorher mit dem Betreiber besprochen und von ihm freigegeben.
- Eine Zustimmung zu einer fachlichen Idee ist **keine** Freigabe zur Programmierung.
- Vereinbarte Geschäftsregeln werden nicht ohne Rücksprache geändert.
- Es wird niemals behauptet, etwas sei getestet, wenn kein Test tatsächlich gelaufen ist.
- Keine eigenmächtigen Änderungen an produktiven Daten. Vor riskanten Änderungen: Sicherung und ausdrückliche Freigabe.

## Maßgebliche Dokumente

Sie liegen im Claude-Projekt „Ticketverkauf“:
- `claude/Entscheidungsprotokoll.md` – alle Entscheidungen (Nummern wie E29 verweisen dorthin)
- `claude/Architekturdokument.md` – Architektur (v1.0, freigegeben)
- `claude/Entwicklungsplan.md` – Stufen 0–10 mit Abnahmelisten

## Kommunikation

- Mit dem Betreiber wird **auf Deutsch** und **ohne unnötige Fachbegriffe** kommuniziert.
- Bezeichner im Code dürfen deutsch oder englisch sein, sollen aber innerhalb eines Moduls einheitlich bleiben. Oberflächentexte sind deutsch.

## Technik (beschlossen)

- PHP 8.4 auf lima-city (mindestens 8.3), **Laravel 13**, modularer Monolith (`app/Modules/…`).
- **MySQL**, eine Datenbank für alle Vereine, Trennung über Mandantenkennung mit zentralem Filter.
- Vom Server erzeugte Seiten, smartphoneoptimiert, **wenig JavaScript, kein Node-Build-Schritt**.
- **Keine vereinsbezogenen Farbwerte im Code** – Vereinsfarben kommen aus der Datenbank.
- Keine dauerhaft laufenden Serverprozesse (lima-city): Aktualisierung per Abfrage (Polling), Hintergrundaufgaben über einen Cronjob im Minutentakt.
- Keine unnötigen Abhängigkeiten. Neue Pakete nur mit Begründung.

## Qualität

- Alle Berechtigungsprüfungen **serverseitig**.
- Kritische Ticketvorgänge in **Datenbank-Transaktionen mit Sperren**.
- Jede Stufe bringt **automatische Tests** für ihre kritischen Regeln mit. Die Tests laufen bei jedem Hochladen auf GitHub (PHP 8.3 und 8.4, MySQL).
- Arbeit erfolgt auf einem eigenen Zweig je Stufe; Übernahme in `main` erst nach Abnahme durch den Betreiber.

## Hinweis zur Entwicklungsumgebung

In der Cloud-Arbeitsumgebung von Claude ist der Paketserver packagist.org gesperrt. Abhängigkeiten werden deshalb **nicht lokal installiert**; Tests laufen über GitHub Actions (`.github/workflows/tests.yml`). Keine Installationsskripte aus dem Internet ausführen.
