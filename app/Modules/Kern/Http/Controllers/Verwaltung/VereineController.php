<?php

namespace App\Modules\Kern\Http\Controllers\Verwaltung;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Vereinstext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Vereine anlegen, bearbeiten, deaktivieren (nur Super-Admin).
 * Vereine werden nicht gelöscht, nur deaktiviert (E11: Löschung nur bewusst und manuell).
 */
class VereineController extends Controller
{
    public function index(): View
    {
        return view('kern::verwaltung.vereine.index', [
            'vereine' => Mandant::withCount('benutzer')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('kern::verwaltung.vereine.formular', ['verein' => new Mandant(['aktiv' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $verein = Mandant::create($this->pruefe($request) + ['typ' => Mandant::TYP_VEREIN, 'aktiv' => true]);
        Protokoll::schreibe('verein.angelegt', $verein, ['name' => $verein->name, 'kuerzel' => $verein->kuerzel]);

        return redirect()->route('verwaltung.vereine.index')->with('hinweis', 'Verein „'.$verein->name.'“ wurde angelegt.');
    }

    public function edit(Mandant $verein): View
    {
        return view('kern::verwaltung.vereine.formular', ['verein' => $verein]);
    }

    public function update(Request $request, Mandant $verein): RedirectResponse
    {
        $verein->fill($this->pruefe($request, $verein));
        $aenderungen = $verein->getDirty();
        $verein->save();

        if ($aenderungen) {
            Protokoll::schreibe('verein.geaendert', $verein, ['geaendert' => $aenderungen]);
        }

        return redirect()->route('verwaltung.vereine.index')->with('hinweis', 'Verein „'.$verein->name.'“ wurde gespeichert.');
    }

    public function aktivUmschalten(Mandant $verein): RedirectResponse
    {
        $verein->forceFill(['aktiv' => ! $verein->aktiv])->save();
        Protokoll::schreibe($verein->aktiv ? 'verein.aktiviert' : 'verein.deaktiviert', $verein, ['name' => $verein->name]);

        return back()->with('hinweis', 'Verein „'.$verein->name.'“ ist jetzt '.($verein->aktiv ? 'aktiv' : 'deaktiviert').'.');
    }

    public function texte(Mandant $verein, Mandantenkontext $kontext): View
    {
        $texte = $kontext->fuer($verein, fn () => Vereinstext::pluck('inhalt', 'art'));

        return view('kern::verwaltung.vereine.texte', ['verein' => $verein, 'texte' => $texte]);
    }

    public function texteSpeichern(Request $request, Mandant $verein, Mandantenkontext $kontext): RedirectResponse
    {
        $daten = $request->validate([
            'texte' => ['array'],
            'texte.*' => ['nullable', 'string', 'max:50000'],
        ]);

        $kontext->fuer($verein, function () use ($daten, $verein) {
            foreach (array_keys(Vereinstext::ARTEN) as $art) {
                $inhalt = trim((string) ($daten['texte'][$art] ?? ''));
                $vorhanden = Vereinstext::where('art', $art)->first();

                if ($inhalt === '') {
                    if ($vorhanden) {
                        $vorhanden->delete();
                        Protokoll::schreibe('rechtstext.geaendert', $verein, ['art' => $art, 'geleert' => true]);
                    }

                    continue;
                }

                if ($vorhanden?->inhalt === $inhalt) {
                    continue;
                }

                Vereinstext::updateOrCreate(['art' => $art], ['inhalt' => $inhalt]);
                Protokoll::schreibe('rechtstext.geaendert', $verein, ['art' => $art]);
            }
        });

        return redirect()->route('verwaltung.vereine.texte', $verein)->with('hinweis', 'Die Texte wurden gespeichert.');
    }

    /** @return array<string, mixed> */
    private function pruefe(Request $request, ?Mandant $verein = null): array
    {
        $request->merge(['kuerzel' => mb_strtoupper(trim((string) $request->input('kuerzel')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kuerzel' => [
                'required', 'regex:/^[A-Z0-9]{2,6}$/',
                Rule::unique('mandanten', 'kuerzel')->ignore($verein?->id),
            ],
        ], [
            'kuerzel.regex' => 'Das Kürzel besteht aus 2 bis 6 Großbuchstaben oder Ziffern (z. B. TVM).',
            'kuerzel.unique' => 'Dieses Kürzel ist bereits vergeben.',
        ], ['name' => 'Name', 'kuerzel' => 'Kürzel']);
    }
}
