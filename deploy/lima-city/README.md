# Betrieb bei lima-city

**Status:** Entwurf (Stufe 0). Der Einspielweg wird fertiggestellt, sobald alle Angaben zu lima-city vorliegen.

## Umgebungen

| Umgebung | Adresse | Datenbank |
|---|---|---|
| Test | `test.regional-meine-wahl.de` | `db_425945_32` (noch zu bestätigen) |
| Live | `regional-meine-wahl.de` | `db_425945_31` (noch zu bestätigen) |

## Geplanter Aufbau auf dem Server

```
~/vereinsplattform-test/          ← Anwendung (nicht öffentlich)
    public/                       ← Startverzeichnis der Website test.regional-meine-wahl.de
    .env                          ← Zugangsdaten, nur auf dem Server, nie im Repository
~/vereinsplattform-live/
    public/                       ← Startverzeichnis der Website regional-meine-wahl.de
    .env
```

Wichtig: Nur der Ordner `public/` darf von außen erreichbar sein. Alles andere (Programmcode, `.env`, Protokolle) liegt darüber und ist so geschützt.

## PHP-Version

Die Plattform nutzt PHP 8.4 **nur in ihrem eigenen Ordner** (Vorlage: `php84.htaccess.vorlage`). Andere Seiten im Konto bleiben auf ihrer bisherigen Version.

Auf der SSH-Konsole und im Cronjob wird PHP immer mit vollem Pfad aufgerufen:
`/opt/lima-php/8.4/bin/php`

## Cronjob (Minutentakt)

Shell-Cronjob, jede Minute, mit Sperre gegen Doppelausführung:

```
cd ~/vereinsplattform-test && flock -n ~/.vp-test-cron.lock /opt/lima-php/8.4/bin/php artisan schedule:run &>> storage/logs/cron.log
```

## Noch offen

- [ ] lima-city-Benutzername (für die PHP-Vorlage)
- [ ] Bestätigung der Datenbank-Zuordnung Test/Live
- [ ] Prüfen, ob `regional-meine-wahl.de` frei ist
- [ ] MySQL-Version
- [ ] Startverzeichnis `…/public` in der Verwaltung einstellbar?
- [ ] Einspielweg festlegen (GitHub → lima-city per SSH)
