#!/usr/bin/env bash
#
# vp-einspielen.sh – spielt die Vereinsplattform bei lima-city ein.
#
# Variante B (beschlossen am 23.10.2026): Der Server holt sich den Code selbst
# von GitHub und darf dort NUR LESEN. GitHub hat keinen Zugang zum Server.
#
# Aufruf:
#   ~/vp-einspielen.sh test [ZWEIG]     Test-Umgebung (Standard-Zweig: main)
#   ~/vp-einspielen.sh live             Live-Umgebung (immer Zweig main, mit Rückfrage)
#   ~/vp-einspielen.sh aktualisieren    dieses Skript selbst aus main aktualisieren
#
# Sicherheitsregeln:
#   - Das Skript schreibt AUSSCHLIESSLICH in ~/vereinsplattform_test bzw.
#     ~/vereinsplattform. Andere Ordner im Konto werden nie verändert.
#   - Zugangsdaten (.env) bleiben auf dem Server und werden nie überschrieben.
#   - Vor jedem Einspielen auf Live wird eine Sicherung angelegt (Dateien + Datenbank).

set -euo pipefail
umask 077

# ---------------------------------------------------------------------------
# Feste Einstellungen
# ---------------------------------------------------------------------------
REPO_SSH="git@github.com:muellejr74-sudo/vereinsplattform.git"
DEPLOY_KEY="$HOME/.ssh/vp_github_deploy"
QUELLE="$HOME/.vp-quelle.git"                 # Nur-Lese-Kopie des Repositorys
BAUORDNER_BASIS="$HOME/.vp-bau"               # Arbeitsordner beim Einspielen
SICHERUNGEN_BASIS="$HOME/backups/vereinsplattform"
PHP_BIN="/opt/lima-php/8.4/bin/php"
PHP_ORDNER="$(dirname "$PHP_BIN")"
ZIEL_TEST="$HOME/vereinsplattform_test"
ZIEL_LIVE="$HOME/vereinsplattform"
SICHERUNGEN_BEHALTEN=10

export GIT_SSH_COMMAND="ssh -i $DEPLOY_KEY -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new"

# ---------------------------------------------------------------------------
# Hilfsfunktionen
# ---------------------------------------------------------------------------
info()   { printf '\n\033[1;34m▶ %s\033[0m\n' "$*"; }
ok()     { printf '\033[1;32m✔ %s\033[0m\n' "$*"; }
warnung(){ printf '\033[1;33m! %s\033[0m\n' "$*"; }
abbruch(){ printf '\n\033[1;31m✘ ABBRUCH: %s\033[0m\n' "$*" >&2; exit 1; }

hilfe() {
    awk 'NR > 1 && /^#/ { sub(/^# ?/, ""); print; next } NR > 1 { exit }' "$0"
    exit 1
}

php84() { "$PHP_BIN" "$@"; }

# Liest einen Wert aus einer .env-Datei (ohne Anführungszeichen).
env_wert() {
    local datei="$1" schluessel="$2"
    grep -E "^${schluessel}=" "$datei" | tail -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

pruefe_voraussetzungen() {
    [[ -x "$PHP_BIN" ]] || abbruch "PHP 8.4 nicht gefunden unter $PHP_BIN"
    command -v git >/dev/null || abbruch "git nicht gefunden"
    command -v rsync >/dev/null || abbruch "rsync nicht gefunden"
    command -v composer >/dev/null || abbruch "composer nicht gefunden"
    [[ -f "$DEPLOY_KEY" ]] || abbruch "Leseschlüssel fehlt: $DEPLOY_KEY (siehe Einrichtungsanleitung, Schritt 1)"
}

# Stellt sicher, dass nur die beiden erlaubten Ordner verwendet werden.
pruefe_ziel() {
    local ziel="$1"
    case "$ziel" in
        "$ZIEL_TEST"|"$ZIEL_LIVE") ;;
        *) abbruch "Unzulässiger Zielordner: $ziel" ;;
    esac
    [[ -d "$ziel" ]] || abbruch "Zielordner existiert nicht: $ziel (wird in der lima-city-Verwaltung angelegt)"
    [[ -d "$ziel/public" ]] || abbruch "Ordner $ziel/public fehlt (Startverzeichnis der Website)"
    [[ ! -L "$ziel" ]] || abbruch "Zielordner ist eine Verknüpfung – aus Sicherheitsgründen nicht erlaubt: $ziel"
}

aktualisiere_quelle() {
    if [[ ! -d "$QUELLE" ]]; then
        info "Lege Nur-Lese-Kopie des Repositorys an"
        git clone --quiet --mirror "$REPO_SSH" "$QUELLE"
    else
        info "Hole aktuellen Stand von GitHub"
        git --git-dir="$QUELLE" remote update --prune >/dev/null
    fi
    ok "Quelle aktuell"
}

# ---------------------------------------------------------------------------
# Selbst-Aktualisierung
# ---------------------------------------------------------------------------
if [[ "${1:-}" == "aktualisieren" ]]; then
    pruefe_voraussetzungen
    aktualisiere_quelle
    neu="$(mktemp)"
    git --git-dir="$QUELLE" show "main:deploy/lima-city/vp-einspielen.sh" > "$neu" \
        || abbruch "Skript im Zweig main nicht gefunden"
    bash -n "$neu" || abbruch "Neue Skriptversion ist fehlerhaft – nicht übernommen"
    install -m 700 "$neu" "$0"
    rm -f "$neu"
    ok "Skript aktualisiert: $0"
    exit 0
fi

# ---------------------------------------------------------------------------
# Parameter
# ---------------------------------------------------------------------------
UMGEBUNG="${1:-}"
case "$UMGEBUNG" in
    test) ZIEL="$ZIEL_TEST"; ZWEIG="${2:-main}"; URL="https://test.regional-meine-wahl.de" ;;
    live) ZIEL="$ZIEL_LIVE"; ZWEIG="main";       URL="https://regional-meine-wahl.de"
          [[ -z "${2:-}" || "${2:-}" == "main" ]] || abbruch "Live wird ausschließlich aus dem Zweig main eingespielt." ;;
    *) hilfe ;;
esac

# Nur ein Einspielvorgang gleichzeitig
exec 9>"$HOME/.vp-einspielen.lock"
flock -n 9 || abbruch "Es läuft bereits ein Einspielvorgang."

pruefe_voraussetzungen
pruefe_ziel "$ZIEL"

if [[ "$UMGEBUNG" == "live" ]]; then
    warnung "Du spielst auf LIVE ($URL) ein."
    read -r -p "Zum Fortfahren bitte LIVE eingeben: " bestaetigung
    [[ "$bestaetigung" == "LIVE" ]] || abbruch "Nicht bestätigt."
fi

aktualisiere_quelle

COMMIT="$(git --git-dir="$QUELLE" rev-parse --verify --quiet "refs/heads/$ZWEIG^{commit}")" \
    || abbruch "Zweig '$ZWEIG' gibt es bei GitHub nicht."
KURZ="${COMMIT:0:7}"
ZEIT="$(date +%Y%m%d-%H%M%S)"
info "Spiele Zweig '$ZWEIG' (Stand $KURZ) auf $UMGEBUNG ein"

# ---------------------------------------------------------------------------
# 1. Erster Aufruf: Zugangsdaten-Datei vorbereiten
# ---------------------------------------------------------------------------
if [[ ! -f "$ZIEL/.env" ]]; then
    info "Erster Aufruf: lege Vorlage für die Zugangsdaten an"
    git --git-dir="$QUELLE" show "$COMMIT:.env.example" > "$ZIEL/.env"
    chmod 600 "$ZIEL/.env"
    sed -i \
        -e "s|^APP_ENV=.*|APP_ENV=$([[ $UMGEBUNG == live ]] && echo production || echo staging)|" \
        -e "s|^APP_DEBUG=.*|APP_DEBUG=false|" \
        -e "s|^APP_URL=.*|APP_URL=$URL|" \
        -e "s|^LOG_LEVEL=.*|LOG_LEVEL=warning|" \
        -e "s|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=true|" \
        -e "s|^DB_HOST=.*|DB_HOST=BITTE_EINTRAGEN|" \
        -e "s|^DB_DATABASE=.*|DB_DATABASE=BITTE_EINTRAGEN|" \
        -e "s|^DB_USERNAME=.*|DB_USERNAME=BITTE_EINTRAGEN|" \
        -e "s|^DB_PASSWORD=.*|DB_PASSWORD=BITTE_EINTRAGEN|" \
        "$ZIEL/.env"
    warnung "Bitte jetzt die Datenbank-Zugangsdaten eintragen:"
    warnung "   nano $ZIEL/.env"
    warnung "Danach dieses Skript erneut starten."
    exit 0
fi

grep -q "BITTE_EINTRAGEN" "$ZIEL/.env" && abbruch "In $ZIEL/.env sind noch Platzhalter 'BITTE_EINTRAGEN'. Bitte Zugangsdaten eintragen."

# ---------------------------------------------------------------------------
# 2. Stand auspacken und Abhängigkeiten installieren (in eigenem Arbeitsordner)
# ---------------------------------------------------------------------------
BAU="$BAUORDNER_BASIS/$UMGEBUNG-$ZEIT"
mkdir -p "$BAU"
trap 'rm -rf "$BAU"' EXIT

info "Packe Stand $KURZ aus"
git --git-dir="$QUELLE" archive "$COMMIT" | tar -x -C "$BAU"

info "Installiere Abhängigkeiten (composer, ohne Entwicklungswerkzeuge)"
[[ -f "$BAU/composer.lock" ]] || abbruch "composer.lock fehlt im Stand $KURZ"
( cd "$BAU" && PATH="$PHP_ORDNER:$PATH" composer install \
    --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --no-scripts )
ok "Abhängigkeiten installiert"

info "Setze PHP 8.4 nur für diesen Ordner (.htaccess)"
BENUTZER="$(id -un)"
{
    echo "# --- PHP 8.4 nur für die Vereinsplattform (automatisch erzeugt) ---"
    echo '<Files ~ (\.php$)>'
    echo '    <If "-f %{REQUEST_FILENAME}">'
    echo "        SetHandler \"proxy:unix:/usr/share/lima/php-$BENUTZER/php-fpm-84|fcgi://localhost-$BENUTZER-php84\""
    echo '    </If>'
    echo '</Files>'
    echo
    cat "$BAU/public/.htaccess"
} > "$BAU/public/.htaccess.neu"
mv "$BAU/public/.htaccess.neu" "$BAU/public/.htaccess"
chmod 644 "$BAU/public/.htaccess"

# ---------------------------------------------------------------------------
# 3. Sicherung (Live: immer; Test: wenn bereits installiert)
# ---------------------------------------------------------------------------
BEREITS_INSTALLIERT=0
[[ -f "$ZIEL/artisan" ]] && BEREITS_INSTALLIERT=1

if [[ $BEREITS_INSTALLIERT -eq 1 ]]; then
    SICHERUNG="$SICHERUNGEN_BASIS/$UMGEBUNG/$ZEIT"
    info "Lege Sicherung an: $SICHERUNG"
    mkdir -p "$SICHERUNG"
    tar -czf "$SICHERUNG/dateien.tar.gz" -C "$ZIEL" --exclude=./vendor --exclude=./storage/framework .
    if command -v mysqldump >/dev/null; then
        cnf="$(mktemp)"; chmod 600 "$cnf"
        {
            echo "[client]"
            echo "host=$(env_wert "$ZIEL/.env" DB_HOST)"
            echo "port=$(env_wert "$ZIEL/.env" DB_PORT)"
            echo "user=$(env_wert "$ZIEL/.env" DB_USERNAME)"
            echo "password=$(env_wert "$ZIEL/.env" DB_PASSWORD)"
        } > "$cnf"
        if mysqldump --defaults-extra-file="$cnf" --single-transaction --no-tablespaces \
             "$(env_wert "$ZIEL/.env" DB_DATABASE)" | gzip > "$SICHERUNG/datenbank.sql.gz"; then
            ok "Datenbank gesichert"
        else
            rm -f "$cnf"
            [[ "$UMGEBUNG" == "live" ]] && abbruch "Datenbanksicherung fehlgeschlagen – Live wird ohne Sicherung NICHT eingespielt."
            warnung "Datenbanksicherung fehlgeschlagen (Test – wird fortgesetzt)"
        fi
        rm -f "$cnf"
    else
        [[ "$UMGEBUNG" == "live" ]] && abbruch "mysqldump nicht vorhanden – Live wird ohne Datenbanksicherung NICHT eingespielt."
        warnung "mysqldump nicht vorhanden – nur Dateien gesichert"
    fi
    # Alte Sicherungen aufräumen (die neuesten $SICHERUNGEN_BEHALTEN bleiben)
    ls -1d "$SICHERUNGEN_BASIS/$UMGEBUNG"/*/ 2>/dev/null | sort | head -n -"$SICHERUNGEN_BEHALTEN" | xargs -r rm -rf
    ok "Sicherung fertig"

    info "Wartungsmodus an"
    ( cd "$ZIEL" && php84 artisan down --retry=30 ) || warnung "Wartungsmodus konnte nicht gesetzt werden"
elif [[ "$UMGEBUNG" == "live" ]]; then
    warnung "Erste Installation auf Live – es gibt noch nichts zu sichern."
fi

# ---------------------------------------------------------------------------
# 4. Dateien übertragen (Zugangsdaten und Laufzeitdaten bleiben unberührt)
# ---------------------------------------------------------------------------
info "Übertrage Dateien nach $ZIEL"
if [[ ! -d "$ZIEL/storage" ]]; then
    cp -a "$BAU/storage" "$ZIEL/storage"
fi
rsync -a --delete \
    --exclude='/.env' \
    --exclude='/.env.*' \
    --exclude='/storage/' \
    --exclude='/.vp-version' \
    "$BAU/" "$ZIEL/"
mkdir -p "$ZIEL/storage/framework/"{cache/data,sessions,views,testing} "$ZIEL/storage/logs" "$ZIEL/storage/app/public" "$ZIEL/bootstrap/cache"
ok "Dateien übertragen"

# ---------------------------------------------------------------------------
# 5. Anwendung einrichten
# ---------------------------------------------------------------------------
cd "$ZIEL"
if [[ -z "$(env_wert .env APP_KEY)" ]]; then
    info "Erzeuge Anwendungsschlüssel (einmalig)"
    php84 artisan key:generate --force
fi

info "Pakete erkennen und Datenbank aktualisieren"
php84 artisan package:discover --ansi >/dev/null
php84 artisan migrate --force

info "Zwischenspeicher neu aufbauen"
php84 artisan optimize:clear >/dev/null
php84 artisan optimize

printf 'zweig=%s\nstand=%s\neingespielt=%s\n' "$ZWEIG" "$COMMIT" "$(date -Iseconds)" > "$ZIEL/.vp-version"

if [[ $BEREITS_INSTALLIERT -eq 1 ]]; then
    info "Wartungsmodus aus"
    php84 artisan up
fi

info "Kurzbericht"
echo "  Umgebung:   $UMGEBUNG ($URL)"
echo "  Zweig:      $ZWEIG"
echo "  Stand:      $KURZ"
echo -n "  PHP:        "; php84 -r 'echo PHP_VERSION, PHP_EOL;'
echo -n "  Datenbank:  "; php84 artisan tinker --execute='echo \Illuminate\Support\Facades\DB::selectOne("select version() as v")->v;' 2>/dev/null || echo "(nicht ermittelbar)"
echo
ok "Fertig. Bitte im Browser prüfen: $URL"
