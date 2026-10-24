<?php

namespace App\Modules\Kern\Dienste;

use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Station;
use App\Modules\Kern\Models\Stationsbesetzung;
use App\Modules\Kern\Models\Stationsgeraet;
use App\Modules\Kern\Models\Vorstellung;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Cookie as CookieObjekt;

/**
 * Einsatz-Code und Stationsanmeldung für Abendpersonal ohne Konto (E29, E29a, E33, E102).
 */
class Einsatz
{
    public const COOKIE = 'vp_station';

    public function __construct(private Mandantenkontext $kontext) {}

    /** Neuen 6-stelligen Code erzeugen – eindeutig unter allen noch nicht beendeten Vorstellungen. */
    public function codeErzeugen(Vorstellung $vorstellung): string
    {
        $vergeben = DB::table('vorstellungen')
            ->whereNotNull('einsatz_code')
            ->where('beginn_um', '>=', now()->subDays(2))
            ->pluck('einsatz_code')->all();

        do {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (in_array($code, $vergeben, true));

        $vorstellung->forceFill(['einsatz_code' => $code])->save();

        // Ein neuer Code beendet bewusst NICHT die bestehenden Anmeldungen – dafür gibt es „Geräte abmelden“.
        return $code;
    }

    /**
     * Sucht die Vorstellung zu einem Einsatz-Code, deren Zeitfenster gerade läuft.
     * Vereinsübergreifend, weil das Personal keinen Verein auswählt – nur hier.
     */
    public function vorstellungZuCode(string $code): ?Vorstellung
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return null;
        }

        $kandidaten = DB::table('vorstellungen')->where('einsatz_code', $code)->pluck('mandant_id', 'id');

        foreach ($kandidaten as $id => $mandantId) {
            $verein = Mandant::where('aktiv', true)->find($mandantId);
            if (! $verein) {
                continue;
            }
            $vorstellung = $this->kontext->fuer($verein, fn () => Vorstellung::find($id));
            if ($vorstellung && $vorstellung->istImZeitfenster()) {
                return $vorstellung;
            }
        }

        return null;
    }

    /**
     * @return list<array{station: Station, name: ?string, belegt: bool}>
     */
    public function stationsuebersicht(Vorstellung $vorstellung): array
    {
        $namen = Stationsbesetzung::where('vorstellung_id', $vorstellung->id)->pluck('name', 'station_id');
        $belegt = Stationsgeraet::aktiv()->where('vorstellung_id', $vorstellung->id)->pluck('station_id')->all();

        return Station::orderBy('reihenfolge')->orderBy('name')->get()
            ->map(fn (Station $s) => [
                'station' => $s,
                'name' => $namen[$s->id] ?? null,
                'belegt' => ! $s->mehrere_geraete && in_array($s->id, $belegt, true),
            ])->all();
    }

    /**
     * Gerät an eine Station binden. Bedienungsstationen nur mit einem Gerät (E33).
     *
     * @return array{geraet: Stationsgeraet, cookie: CookieObjekt}
     */
    public function anmelden(Vorstellung $vorstellung, Station $station, Request $anfrage): array
    {
        return DB::transaction(function () use ($vorstellung, $station, $anfrage) {
            Station::whereKey($station->id)->lockForUpdate()->first();

            if (! $station->mehrere_geraete
                && Stationsgeraet::aktiv()->where('vorstellung_id', $vorstellung->id)->where('station_id', $station->id)->exists()) {
                throw new InvalidArgumentException('Diese Station ist bereits belegt.');
            }

            $token = Str::random(64);
            $geraet = Stationsgeraet::create([
                'vorstellung_id' => $vorstellung->id,
                'station_id' => $station->id,
                'token_pruefsumme' => hash('sha256', $token),
                'geraet' => Str::limit((string) $anfrage->userAgent(), 175, ''),
                'angemeldet_am' => now(),
                'zuletzt_aktiv_am' => now(),
            ]);

            $minuten = max(1, (int) ceil(now()->diffInMinutes($vorstellung->fensterEnde(), true)));
            $cookie = Cookie::make(self::COOKIE, $vorstellung->mandant_id.'|'.$token, $minuten, null, null, null, true, false, 'lax');

            Protokoll::schreibe('station.angemeldet', $geraet, ['vorstellung_id' => $vorstellung->id], null, $vorstellung->mandant_id, $this->akteur($geraet));

            return ['geraet' => $geraet, 'cookie' => $cookie];
        });
    }

    /** Angemeldetes Gerät aus dem Cookie – nur solange das Zeitfenster läuft und nicht abgemeldet. */
    public function geraetAusAnfrage(Request $anfrage): ?Stationsgeraet
    {
        $wert = (string) $anfrage->cookie(self::COOKIE, '');
        if (! str_contains($wert, '|')) {
            return null;
        }

        [$mandantId, $token] = explode('|', $wert, 2);
        $verein = Mandant::where('aktiv', true)->find((int) $mandantId);
        if (! $verein) {
            return null;
        }

        $this->kontext->setze($verein);
        $geraet = Stationsgeraet::aktiv()->with(['station', 'vorstellung.veranstaltung'])
            ->where('token_pruefsumme', hash('sha256', $token))->first();

        if (! $geraet || ! $geraet->station || ! $geraet->vorstellung || ! $geraet->vorstellung->istImZeitfenster()) {
            $this->kontext->leeren();

            return null;
        }

        if (! $geraet->zuletzt_aktiv_am || $geraet->zuletzt_aktiv_am->lt(now()->subMinute())) {
            $geraet->forceFill(['zuletzt_aktiv_am' => now()])->save();
        }

        return $geraet;
    }

    public function abmelden(Stationsgeraet $geraet, string $durch): void
    {
        if ($geraet->abgemeldet_am) {
            return;
        }
        $geraet->forceFill(['abgemeldet_am' => now()])->save();
        Protokoll::schreibe('station.abgemeldet', $geraet, ['durch' => $durch], null, $geraet->mandant_id, $durch === 'selbst' ? $this->akteur($geraet) : null);
    }

    /** „Bedienung 1 (Petra)“ – so erscheint das Gerät im Protokoll (E29b). */
    public function akteur(Stationsgeraet $geraet): string
    {
        $station = $geraet->station ?? Station::find($geraet->station_id);
        $name = Stationsbesetzung::where('vorstellung_id', $geraet->vorstellung_id)->where('station_id', $geraet->station_id)->value('name');

        return trim(($station?->name ?? 'Station').($name ? ' ('.$name.')' : ''));
    }
}
