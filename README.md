# Vereinsplattform

Modulare, mandantenfähige Plattform für Vereine und Veranstaltungen:

- **Ticketverwaltung** – Vorverkaufsstellen, Druck auf Etiketten, Reservierungen, Abendkasse, Einlass, Abschluss
- **Saalbestellungen** – Bedienung → Ausschank/Küche
- **Digitales Gästeportal** – QR-Code am Tisch, Karte, Gästebestellung
- **Werbung** – Betreiber- und Vereinswerbung mit Zählung

Betreiber: regional-meine-wahl.de

## Stand

**Stufe 0 – Grundgerüst.** Die Anwendung zeigt eine Startseite; es gibt noch keine Fachfunktionen.

## Technik

- PHP 8.4 (mindestens 8.3), Laravel 13, MySQL
- Hosting: lima-city (Test: `test.regional-meine-wahl.de`, Live: `regional-meine-wahl.de`)
- Automatische Tests bei jedem Hochladen über GitHub Actions

## Aufbau

| Ordner | Inhalt |
|---|---|
| `app/Modules/` | Die Module der Plattform (siehe `app/Modules/README.md`) |
| `resources/views/` | Seitenvorlagen |
| `public/` | Öffentlich erreichbarer Ordner (Startverzeichnis der Website) |
| `tests/` | Automatische Tests |
| `deploy/lima-city/` | Vorlagen und Anleitungen für den Betrieb bei lima-city |
| `docs/` | Technische Notizen zur Umsetzung |

## Lokal starten (für Entwickler)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Tests: `php artisan test`
