<?php

namespace App\Modules\Kern\Http\Controllers\Verein;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Dienste\Einsatz;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Dienste\Stammdaten;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Station;
use App\Modules\Kern\Models\Stationsbesetzung;
use App\Modules\Kern\Models\Stationsgeraet;
use App\Modules\Kern\Models\Vorstellung;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Stationen (E29, E34, E103) sowie pro Vorstellung: Einsatz-Code, Namen des Abends
 * und angemeldete Geräte (E29a, E33, E102).
 */
class StationenController extends Controller
{
    public function __construct(private Mandantenkontext $kontext, private Einsatz $einsatz) {}

    public function index(Stammdaten $stammdaten): View
    {
        $stammdaten->sicherstellen($this->kontext->mandant());

        return view('kern::verein.stationen.index', [
            'stationen' => Station::orderBy('reihenfolge')->orderBy('name')->get(),
        ]);
    }

    public function speichern(Request $request, ?Station $station = null): RedirectResponse
    {
        $daten = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'art' => ['required', Rule::in(array_keys(Station::ARTEN))],
            'mehrere_geraete' => ['nullable', 'boolean'],
            'reihenfolge' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [], ['name' => 'Name', 'art' => 'Art']);
        $daten['mehrere_geraete'] = $request->boolean('mehrere_geraete');
        $daten['reihenfolge'] ??= ((int) Station::max('reihenfolge')) + 10;

        $neu = ! $station?->exists;
        $station = $neu ? Station::create($daten) : tap($station)->update($daten);
        Protokoll::schreibe($neu ? 'station.angelegt' : 'station.geaendert', $station, ['name' => $station->name]);

        return back()->with('hinweis', 'Station „'.$station->name.'“ gespeichert.');
    }

    public function loeschen(Station $station): RedirectResponse
    {
        Protokoll::schreibe('station.geloescht', $station, ['name' => $station->name]);
        $station->delete();

        return back()->with('hinweis', 'Station gelöscht.');
    }

    // ---------------- Einsatz einer Vorstellung ----------------

    public function einsatz(Vorstellung $vorstellung, Stammdaten $stammdaten): View
    {
        $stammdaten->sicherstellen($this->kontext->mandant());

        return view('kern::verein.stationen.einsatz', [
            'vorstellung' => $vorstellung->load('veranstaltung'),
            'stationen' => $this->einsatz->stationsuebersicht($vorstellung),
            'geraete' => Stationsgeraet::aktiv()->with('station')->where('vorstellung_id', $vorstellung->id)->orderBy('angemeldet_am')->get(),
        ]);
    }

    public function codeErzeugen(Vorstellung $vorstellung): RedirectResponse
    {
        abort_if($vorstellung->istVorbei(), 422, 'Diese Vorstellung ist bereits vorbei.');
        $this->einsatz->codeErzeugen($vorstellung);
        Protokoll::schreibe('einsatzcode.erzeugt', $vorstellung, ['beginn' => $vorstellung->beginn_um->format('d.m.Y H:i')]);

        return back()->with('hinweis', 'Neuer Einsatz-Code erzeugt. Bereits angemeldete Geräte bleiben angemeldet.');
    }

    public function besetzungSpeichern(Request $request, Vorstellung $vorstellung): RedirectResponse
    {
        $daten = $request->validate(['namen' => ['array'], 'namen.*' => ['nullable', 'string', 'max:60']]);
        $stationIds = Station::pluck('id')->all();

        foreach ($daten['namen'] ?? [] as $stationId => $name) {
            if (! in_array((int) $stationId, $stationIds, true)) {
                continue;
            }
            $name = trim((string) $name);
            if ($name === '') {
                Stationsbesetzung::where('vorstellung_id', $vorstellung->id)->where('station_id', $stationId)->delete();
            } else {
                Stationsbesetzung::updateOrCreate(['vorstellung_id' => $vorstellung->id, 'station_id' => (int) $stationId], ['name' => $name]);
            }
        }
        Protokoll::schreibe('station.besetzung', $vorstellung, ['namen' => array_filter(array_map('trim', $daten['namen'] ?? []))]);

        return back()->with('hinweis', 'Die Namen für diese Vorstellung wurden gespeichert.');
    }

    public function geraetAbmelden(Request $request, Vorstellung $vorstellung, Stationsgeraet $geraet): RedirectResponse
    {
        abort_if($geraet->vorstellung_id !== $vorstellung->id, 404);
        $this->einsatz->abmelden($geraet, $request->user()->name);

        return back()->with('hinweis', 'Das Gerät wurde abgemeldet.');
    }
}
