<?php

namespace App\Modules\Kern\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Dienste\Einsatz;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Station;
use App\Modules\Kern\Models\Vorstellung;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Anmeldung des Abendpersonals ohne Konto (E29, E29a, E33, E102):
 * Einsatz-Code eingeben → Station wählen → Gerät ist bis zum Ende des Zeitfensters gebunden.
 */
class EinsatzController extends Controller
{
    private const SITZUNG = 'einsatz_vorstellung';

    private const VERSUCHE = 5;

    public function __construct(private Einsatz $einsatz, private Mandantenkontext $kontext) {}

    public function codeFormular(Request $request): View|RedirectResponse
    {
        if ($this->einsatz->geraetAusAnfrage($request)) {
            return redirect()->route('station');
        }

        return view('kern::einsatz.code');
    }

    public function codePruefen(Request $request): RedirectResponse
    {
        $daten = $request->validate(['code' => ['required', 'string', 'max:12']], [], ['code' => 'Einsatz-Code']);
        // Sperre je Gerät/Anschluss (E102): 5 Fehlversuche → 15 Minuten. Bewusst kein
        // gemeinsamer Zähler für alle, sonst könnte ein Unbefugter alle Stationen aussperren.
        $schluessel = 'einsatz:'.$request->ip();
        if (RateLimiter::tooManyAttempts($schluessel, self::VERSUCHE)) {
            return back()->withErrors(['code' => 'Zu viele falsche Codes. Bitte in '.max(1, (int) ceil(RateLimiter::availableIn($schluessel) / 60)).' Minuten erneut versuchen.']);
        }

        $vorstellung = $this->einsatz->vorstellungZuCode($daten['code']);
        if (! $vorstellung) {
            RateLimiter::hit($schluessel, 15 * 60);
            Protokoll::schreibe('station.anmeldung_fehlgeschlagen', null, [], null, null, 'unbekanntes Gerät');

            return back()->withErrors(['code' => 'Dieser Code ist nicht gültig – oder die Vorstellung hat noch nicht begonnen bzw. ist schon vorbei.']);
        }

        RateLimiter::clear($schluessel);
        $request->session()->put(self::SITZUNG, ['mandant_id' => $vorstellung->mandant_id, 'vorstellung_id' => $vorstellung->id, 'bis' => now()->addMinutes(15)->getTimestamp()]);

        return redirect()->route('einsatz.station');
    }

    public function stationFormular(Request $request): View|RedirectResponse
    {
        $vorstellung = $this->vorstellungAusSitzung($request);
        if (! $vorstellung) {
            return redirect()->route('einsatz');
        }

        return view('kern::einsatz.station', [
            'vorstellung' => $vorstellung->load('veranstaltung'),
            'stationen' => $this->einsatz->stationsuebersicht($vorstellung),
        ]);
    }

    public function stationWaehlen(Request $request): RedirectResponse
    {
        $vorstellung = $this->vorstellungAusSitzung($request);
        if (! $vorstellung) {
            return redirect()->route('einsatz');
        }

        $daten = $request->validate(['station_id' => ['required', 'integer']]);
        $station = Station::find($daten['station_id']);
        abort_unless($station, 404);

        try {
            $ergebnis = $this->einsatz->anmelden($vorstellung, $station, $request);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['station_id' => $e->getMessage()]);
        }

        $request->session()->forget(self::SITZUNG);

        return redirect()->route('station')->withCookie($ergebnis['cookie']);
    }

    public function station(Request $request): View|RedirectResponse
    {
        $geraet = $this->einsatz->geraetAusAnfrage($request);
        if (! $geraet) {
            return redirect()->route('einsatz')->withCookie(Cookie::forget(Einsatz::COOKIE));
        }

        // Station „Einlass“ öffnet direkt den Scanner, sobald das Ticketmodul aktiv ist (Stufe 4a).
        if ($geraet->station->art === 'einlass' && Route::has('station.einlass')
            && app(Mandantenkontext::class)->mandant()?->hatModul('ticket')) {
            return redirect()->route('station.einlass');
        }

        return view('kern::einsatz.start', [
            'geraet' => $geraet,
            'akteur' => $this->einsatz->akteur($geraet),
        ]);
    }

    public function abmelden(Request $request): RedirectResponse
    {
        if ($geraet = $this->einsatz->geraetAusAnfrage($request)) {
            $this->einsatz->abmelden($geraet, 'selbst');
        }

        return redirect()->route('einsatz')->withCookie(Cookie::forget(Einsatz::COOKIE))->with('hinweis', 'Sie wurden von der Station abgemeldet.');
    }

    private function vorstellungAusSitzung(Request $request): ?Vorstellung
    {
        $wert = $request->session()->get(self::SITZUNG);
        if (! is_array($wert) || ($wert['bis'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        $verein = Mandant::where('aktiv', true)->find($wert['mandant_id'] ?? 0);
        if (! $verein) {
            return null;
        }
        $this->kontext->setze($verein);
        $vorstellung = Vorstellung::find($wert['vorstellung_id'] ?? 0);

        return $vorstellung && $vorstellung->istImZeitfenster() ? $vorstellung : null;
    }
}
