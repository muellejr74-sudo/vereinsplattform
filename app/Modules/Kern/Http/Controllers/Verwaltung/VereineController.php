<?php

namespace App\Modules\Kern\Http\Controllers\Verwaltung;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Dienste\Vereinsdesign;
use App\Modules\Kern\Http\Middleware\SetzeMandantenkontext;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Vereinstext;
use App\Modules\Kern\Rechte\Rechtekatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    /** Design (E76, E98, E99) und Modulfreischaltung – nie delegierbar, nur Super-Admin. */
    public function design(Mandant $verein): View
    {
        return view('kern::verwaltung.vereine.design', [
            'verein' => $verein,
            'module' => Rechtekatalog::MODULE,
            'hinweise' => Vereinsdesign::hinweise($verein->farbe_primaer, $verein->farbe_sekundaer),
        ]);
    }

    public function designSpeichern(Request $request, Mandant $verein): RedirectResponse
    {
        $daten = $request->validate([
            'farbe_primaer' => ['nullable', 'hex_color', 'size:7'],
            'farbe_sekundaer' => ['nullable', 'hex_color', 'size:7'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'mimetypes:image/png,image/jpeg,image/webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'logo_entfernen' => ['nullable', 'boolean'],
            'module' => ['array'],
            'module.*' => ['string', Rule::in(array_keys(Rechtekatalog::MODULE))],
        ], [
            'logo.mimes' => 'Das Logo muss ein PNG-, JPG- oder WebP-Bild sein (kein SVG).',
            'logo.mimetypes' => 'Das Logo muss ein PNG-, JPG- oder WebP-Bild sein (kein SVG).',
            'logo.max' => 'Das Logo darf höchstens 2 MB groß sein.',
            'farbe_primaer.hex_color' => 'Bitte eine Farbe im Format #RRGGBB angeben.',
            'farbe_sekundaer.hex_color' => 'Bitte eine Farbe im Format #RRGGBB angeben.',
        ], ['farbe_primaer' => 'Primärfarbe', 'farbe_sekundaer' => 'Sekundärfarbe', 'logo' => 'Logo']);

        $vorher = $verein->only(['farbe_primaer', 'farbe_sekundaer', 'logo_datei']);
        $verein->farbe_primaer = isset($daten['farbe_primaer']) ? strtolower($daten['farbe_primaer']) : null;
        $verein->farbe_sekundaer = isset($daten['farbe_sekundaer']) ? strtolower($daten['farbe_sekundaer']) : null;

        $alteDatei = $verein->logo_datei;
        if ($request->hasFile('logo')) {
            $datei = $request->file('logo');
            $verein->logo_datei = $datei->storeAs('vereinslogos', $verein->id.'-'.Str::random(16).'.'.$datei->extension(), 'local');
            $verein->logo_typ = $datei->getMimeType();
        } elseif ($request->boolean('logo_entfernen')) {
            $verein->logo_datei = null;
            $verein->logo_typ = null;
        }
        $verein->save();

        if ($alteDatei && $alteDatei !== $verein->logo_datei) {
            Storage::disk('local')->delete($alteDatei);
        }

        $nachher = $verein->only(['farbe_primaer', 'farbe_sekundaer', 'logo_datei']);
        if ($vorher !== $nachher) {
            Protokoll::schreibe('verein.design_geaendert', $verein, [
                'farbe_primaer' => $nachher['farbe_primaer'],
                'farbe_sekundaer' => $nachher['farbe_sekundaer'],
                'logo' => $nachher['logo_datei'] !== $vorher['logo_datei'] ? ($nachher['logo_datei'] ? 'neu' : 'entfernt') : 'unverändert',
            ]);
        }

        $module = $verein->setzeModule($daten['module'] ?? []);
        if ($module['an'] || $module['aus']) {
            Protokoll::schreibe('verein.module_geaendert', $verein, ['an' => $module['an'], 'aus' => $module['aus']]);
        }

        $antwort = redirect()->route('verwaltung.vereine.design', $verein)->with('hinweis', 'Design und Module wurden gespeichert.');
        $hinweise = Vereinsdesign::hinweise($verein->farbe_primaer, $verein->farbe_sekundaer);

        return $hinweise ? $antwort->with('designhinweise', $hinweise) : $antwort;
    }

    /** Super-Admin: in den Verein wechseln und dort Personen & Rechte verwalten. */
    public function personen(Request $request, Mandant $verein): RedirectResponse
    {
        abort_unless($verein->aktiv, 422, 'Der Verein ist deaktiviert.');
        $request->session()->put(SetzeMandantenkontext::SITZUNG, $verein->id);

        return redirect()->route('verein.personen.index');
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
