# Betrieb bei lima-city

## Umgebungen

| Umgebung | Adresse | Ordner auf dem Server | Startverzeichnis | Datenbank |
|---|---|---|---|---|
| Test | `test.regional-meine-wahl.de` | `~/vereinsplattform_test` | `vereinsplattform_test/public` | `db_425945_32` |
| Live | `regional-meine-wahl.de` | `~/vereinsplattform` | `vereinsplattform/public` | `db_425945_31` |

SSH: `ssh muellejr@muellejr.lima-ssh.de` · PHP der Plattform: `/opt/lima-php/8.4/bin/php`

Nur der Ordner `public/` ist von außen erreichbar. Programmcode, Zugangsdaten (`.env`) und Protokolle liegen darüber und sind so geschützt.

## Einspielweg (Variante B)

Der **Server holt sich den Code selbst** von GitHub und darf dort **nur lesen**. GitHub hat **keinen** Zugang zum Server. Das Skript `vp-einspielen.sh` schreibt ausschließlich in die beiden Ordner oben; alle anderen Projekte im Konto bleiben unberührt.

```
~/vp-einspielen.sh test                 Test aus Zweig main
~/vp-einspielen.sh test ZWEIGNAME       Test aus einem bestimmten Zweig (z. B. zur Abnahme)
~/vp-einspielen.sh live                 Live aus main – mit Rückfrage und Sicherung vorab
~/vp-einspielen.sh aktualisieren        das Skript selbst aktualisieren
```

Ablauf beim Einspielen: Stand von GitHub holen → Abhängigkeiten installieren → **Sicherung** (Dateien + Datenbank, die letzten 10 werden aufbewahrt unter `~/backups/vereinsplattform/`) → Wartungsmodus → Dateien übertragen → Datenbank aktualisieren → Wartungsmodus aus → Kurzbericht.

---

## Einmalige Einrichtung

Alle Befehle werden **auf dem Server** eingegeben (`ssh muellejr@muellejr.lima-ssh.de`).

### Schritt 1 – Leseschlüssel für GitHub erzeugen

```bash
ssh-keygen -t ed25519 -f ~/.ssh/vp_github_deploy -N "" -C "vereinsplattform-lesen"
cat ~/.ssh/vp_github_deploy.pub
```

Die angezeigte Zeile (beginnt mit `ssh-ed25519`) kopieren.

### Schritt 2 – Leseschlüssel bei GitHub eintragen

GitHub → Repository `vereinsplattform` → **Settings** → **Deploy keys** → **Add deploy key**
- Title: `lima-city (nur lesen)`
- Key: die kopierte Zeile einfügen
- **„Allow write access“ NICHT anhaken**
- **Add key**

### Schritt 3 – Einspielskript installieren

```bash
export GIT_SSH_COMMAND="ssh -i ~/.ssh/vp_github_deploy -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new"
git clone --mirror git@github.com:muellejr74-sudo/vereinsplattform.git ~/.vp-quelle.git
git --git-dir ~/.vp-quelle.git show ZWEIG:deploy/lima-city/vp-einspielen.sh > ~/vp-einspielen.sh
chmod 700 ~/vp-einspielen.sh
```
(`ZWEIG` = `main`, solange die Stufe noch nicht übernommen ist der Abnahme-Zweig, z. B. `stufe-0-grundgeruest`.)

### Schritt 4 – Erster Aufruf: Vorlage für Zugangsdaten

```bash
~/vp-einspielen.sh test ZWEIG
```
Das Skript legt `~/vereinsplattform_test/.env` an und stoppt.

### Schritt 5 – Datenbank-Zugangsdaten eintragen

```bash
nano ~/vereinsplattform_test/.env
```
Die vier Zeilen mit `BITTE_EINTRAGEN` ersetzen (Werte aus der lima-city-Verwaltung → Datenbanken):
```
DB_HOST=…
DB_DATABASE=db_425945_32
DB_USERNAME=…
DB_PASSWORD=…
```
Speichern: **Strg + O**, **Enter** · Beenden: **Strg + X**

Die Zugangsdaten bleiben ausschließlich auf dem Server.

### Schritt 6 – Einspielen

```bash
~/vp-einspielen.sh test ZWEIG
```
Danach `https://test.regional-meine-wahl.de` im Browser aufrufen.

### Schritt 7 – Cronjob (Minutentakt)

lima-city-Verwaltung → **Cronjobs** → **Cronjob erstellen** → **Shell-Cronjob**, jede Minute, Befehl:

```
cd ~/vereinsplattform_test && flock -n ~/.vp-test-cron.lock /opt/lima-php/8.4/bin/php artisan schedule:run >> storage/logs/cron.log 2>&1
```

---

## Wiederherstellen einer Sicherung (Notfall)

Sicherungen liegen unter `~/backups/vereinsplattform/test|live/DATUM-UHRZEIT/`
(`dateien.tar.gz`, `datenbank.sql.gz`). Eine Wiederherstellung wird **nur nach Rücksprache** durchgeführt; das Verfahren wird in Stufe 5 (Probe-Wiederherstellung) erprobt und hier ergänzt.
