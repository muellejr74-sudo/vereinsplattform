# Technische Notizen – Stufe 0

Diese Datei hält technische Entscheidungen fest, die **innerhalb** der freigegebenen Stufe 0 getroffen wurden. Fachliche und architektonische Entscheidungen stehen im Entscheidungsprotokoll des Claude-Projekts.

| Nr. | Entscheidung | Begründung |
|---|---|---|
| T0-1 | **Laravel 13** (aktuelle Hauptversion, setzt PHP ≥ 8.3 voraus) | Passt zu PHP 8.4 auf lima-city; lange Unterstützung. |
| T0-2 | **Kein Node/Vite-Build.** Gestaltung über eine schlichte CSS-Datei (`public/css/plattform.css`) | Entspricht E14c (wenig JavaScript) und „keine unnötigen Abhängigkeiten“; vereinfacht das Einspielen auf lima-city. |
| T0-3 | Vereinsfarben nur als CSS-Variablen, die später aus der Datenbank überschrieben werden | E14c, E76 – keine Vereinsfarben im Code. |
| T0-4 | Sprache Deutsch, Zeitzone `Europe/Berlin` | Grundeinstellung der Plattform. |
| T0-5 | **Automatische Tests über GitHub Actions** mit PHP 8.3 und 8.4 gegen **MySQL** | In der Cloud-Arbeitsumgebung ist packagist.org gesperrt; GitHub prüft stattdessen bei jedem Hochladen. MySQL statt SQLite, damit die Tests der Datenbank bei lima-city entsprechen. |
| T0-6 | `composer.lock` wird beim ersten Lauf automatisch von GitHub erzeugt und ins Repository übernommen | Gleiche Paketstände auf Test, Live und in den Tests. |
| T0-7 | Arbeit je Stufe auf eigenem Zweig, Übernahme nach `main` erst nach Abnahme (Pull Request) | Entspricht dem Update-Ablauf E87. |
| T0-8 | Sitzungsdaten verschlüsselt (`SESSION_ENCRYPT=true`) | Sicherheit ohne Mehraufwand. |
| T0-9 | Laravel-eigene Vorlagen-Workflows, KI-Anleitungen und Installationsskripte der Vorlage wurden **nicht** übernommen | Nicht benötigt bzw. Sicherheitsrisiko (Skripte aus dem Internet). |
| T0-10 | **Einspielweg Variante B:** Der Server holt den Code per Nur-Lese-Schlüssel (GitHub „Deploy key“ ohne Schreibrecht) und installiert ihn mit `deploy/lima-city/vp-einspielen.sh` | Im lima-city-Konto liegen viele weitere Projekte; ein SSH-Schlüssel bei GitHub hätte Vollzugriff auf alle. Bei B hat GitHub keinen Zugang zum Server (Entscheidung des Betreibers am 23.10.2026). |
| T0-11 | Das Skript schreibt nur in `~/vereinsplattform_test` und `~/vereinsplattform`, überschreibt nie die `.env`, sichert vor dem Einspielen und spielt Live nur aus `main` mit ausdrücklicher Bestätigung ein | Schutz der anderen Projekte im Konto und der Live-Daten (E85, E87). |
| T0-12 | Die PHP-8.4-Einstellung in `public/.htaccess` wird beim Einspielen automatisch erzeugt | Andere Webseiten im Konto bleiben auf ihrer PHP-Version. |

