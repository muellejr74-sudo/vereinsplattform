<?php

namespace App\Modules\Kern\Http\Controllers\Verein;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Dienste\Stammdaten;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Ort;
use App\Modules\Kern\Models\Saal;
use App\Modules\Kern\Models\Vorstellung;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Orte und Säle (E35, E101) – Recht „Orte und Säle verwalten“. */
class OrteController extends Controller
{
    public function index(Mandantenkontext $kontext, Stammdaten $stammdaten): View
    {
        $stammdaten->sicherstellen($kontext->mandant());

        return view('kern::verein.orte', ['orte' => Ort::with('saele')->orderBy('name')->get()]);
    }

    public function ortSpeichern(Request $request, ?Ort $ort = null): RedirectResponse
    {
        $daten = $request->validate(['name' => ['required', 'string', 'max:120']], [], ['name' => 'Name des Ortes']);
        $neu = ! $ort?->exists;
        $ort = $ort?->exists ? tap($ort)->update($daten) : Ort::create($daten);
        Protokoll::schreibe($neu ? 'ort.angelegt' : 'ort.geaendert', $ort, ['name' => $ort->name]);

        return back()->with('hinweis', 'Ort „'.$ort->name.'“ gespeichert.');
    }

    public function saalSpeichern(Request $request, ?Saal $saal = null): RedirectResponse
    {
        $daten = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'ort_id' => ['required', 'integer', Rule::exists('orte', 'id')->where('mandant_id', app(Mandantenkontext::class)->id())],
        ], [], ['name' => 'Name des Saals', 'ort_id' => 'Ort']);
        $neu = ! $saal?->exists;
        $saal = $saal?->exists ? tap($saal)->update($daten) : Saal::create($daten);
        Protokoll::schreibe($neu ? 'saal.angelegt' : 'saal.geaendert', $saal, ['name' => $saal->name]);

        return back()->with('hinweis', 'Saal „'.$saal->name.'“ gespeichert.');
    }

    public function saalLoeschen(Saal $saal): RedirectResponse
    {
        if (Saal::count() <= 1) {
            return back()->withErrors(['saal' => 'Der letzte Saal kann nicht gelöscht werden.']);
        }
        if (Vorstellung::where('saal_id', $saal->id)->exists()) {
            return back()->withErrors(['saal' => 'Der Saal wird von Vorstellungen verwendet und kann nicht gelöscht werden.']);
        }

        Protokoll::schreibe('saal.geloescht', $saal, ['name' => $saal->name]);
        $saal->delete();

        return back()->with('hinweis', 'Saal gelöscht.');
    }
}
