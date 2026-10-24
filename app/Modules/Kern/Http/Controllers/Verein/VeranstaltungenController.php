<?php

namespace App\Modules\Kern\Http\Controllers\Verein;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Dienste\Stammdaten;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Saal;
use App\Modules\Kern\Models\Tischzuordnung;
use App\Modules\Kern\Models\Veranstaltung;
use App\Modules\Kern\Models\Vorstellung;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Veranstaltungen und Vorstellungen (E35, E36, E100) – Recht „Veranstaltungen und Vorstellungen anlegen“. */
class VeranstaltungenController extends Controller
{
    public function __construct(private Mandantenkontext $kontext, private Stammdaten $stammdaten) {}

    public function index(): View
    {
        $veranstaltungen = Veranstaltung::withCount('vorstellungen')
            ->withMin('vorstellungen', 'beginn_um')
            ->withMax('vorstellungen', 'beginn_um')
            ->orderByDesc('id')->get();

        return view('kern::verein.veranstaltungen.index', ['veranstaltungen' => $veranstaltungen]);
    }

    public function create(): View
    {
        return view('kern::verein.veranstaltungen.formular', ['veranstaltung' => new Veranstaltung]);
    }

    public function store(Request $request): RedirectResponse
    {
        $veranstaltung = Veranstaltung::create($this->pruefe($request));
        Protokoll::schreibe('veranstaltung.angelegt', $veranstaltung, ['name' => $veranstaltung->name]);

        return redirect()->route('verein.veranstaltungen.show', $veranstaltung)->with('hinweis', 'Veranstaltung angelegt. Legen Sie jetzt die Vorstellungen an.');
    }

    public function show(Veranstaltung $veranstaltung): View
    {
        $zuordnungen = Tischzuordnung::with('tischcode')->where('veranstaltung_id', $veranstaltung->id)->gueltig()->get()
            ->sortBy(fn ($z) => [$z->tischnummer === null ? 0 : 1, (int) $z->tischnummer, $z->tischnummer]);

        return view('kern::verein.veranstaltungen.show', [
            'veranstaltung' => $veranstaltung,
            'vorstellungen' => $veranstaltung->vorstellungen()->with('saal.ort')->get(),
            'laufend' => $veranstaltung->laufendeVorstellung(),
            'zuordnungen' => $zuordnungen,
            'mehrereSaele' => $this->stammdaten->mehrereSaele(),
        ]);
    }

    public function edit(Veranstaltung $veranstaltung): View
    {
        return view('kern::verein.veranstaltungen.formular', ['veranstaltung' => $veranstaltung]);
    }

    public function update(Request $request, Veranstaltung $veranstaltung): RedirectResponse
    {
        $veranstaltung->fill($this->pruefe($request));
        $aenderungen = $veranstaltung->getDirty();
        $veranstaltung->save();
        if ($aenderungen) {
            Protokoll::schreibe('veranstaltung.geaendert', $veranstaltung, ['geaendert' => array_keys($aenderungen)]);
        }

        return redirect()->route('verein.veranstaltungen.show', $veranstaltung)->with('hinweis', 'Gespeichert.');
    }

    public function destroy(Veranstaltung $veranstaltung): RedirectResponse
    {
        Protokoll::schreibe('veranstaltung.geloescht', $veranstaltung, ['name' => $veranstaltung->name, 'vorstellungen' => $veranstaltung->vorstellungen()->count()]);
        $veranstaltung->delete();

        return redirect()->route('verein.veranstaltungen.index')->with('hinweis', 'Veranstaltung gelöscht. Ihre Tische sind wieder frei.');
    }

    // ---------------- Vorstellungen ----------------

    public function vorstellungFormular(Veranstaltung $veranstaltung, ?Vorstellung $vorstellung = null): View
    {
        $this->stammdaten->sicherstellen($this->kontext->mandant());
        abort_if($vorstellung && $vorstellung->veranstaltung_id !== $veranstaltung->id, 404);

        return view('kern::verein.veranstaltungen.vorstellung', [
            'veranstaltung' => $veranstaltung,
            'vorstellung' => $vorstellung ?? new Vorstellung,
            'saele' => Saal::with('ort')->get(),
            'mehrereSaele' => $this->stammdaten->mehrereSaele(),
        ]);
    }

    public function vorstellungSpeichern(Request $request, Veranstaltung $veranstaltung, ?Vorstellung $vorstellung = null): RedirectResponse
    {
        $this->stammdaten->sicherstellen($this->kontext->mandant());
        abort_if($vorstellung && $vorstellung->veranstaltung_id !== $veranstaltung->id, 404);

        $daten = $request->validate([
            'datum' => ['required', 'date_format:Y-m-d'],
            'einlass' => ['nullable', 'date_format:H:i'],
            'beginn' => ['required', 'date_format:H:i'],
            'ende' => ['nullable', 'date_format:H:i'],
            'saal_id' => ['nullable', 'integer', Rule::exists('saele', 'id')->where('mandant_id', $this->kontext->id())],
            'kontingent' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], [], ['datum' => 'Datum', 'einlass' => 'Einlass', 'beginn' => 'Beginn', 'ende' => 'Ende', 'saal_id' => 'Saal', 'kontingent' => 'Kontingent']);

        $beginn = Carbon::parse($daten['datum'].' '.$daten['beginn']);
        $einlass = filled($daten['einlass'] ?? null) ? Carbon::parse($daten['datum'].' '.$daten['einlass']) : null;
        if ($einlass && $einlass->greaterThan($beginn)) {
            return back()->withInput()->withErrors(['einlass' => 'Der Einlass muss vor dem Beginn liegen.']);
        }
        $ende = filled($daten['ende'] ?? null) ? Carbon::parse($daten['datum'].' '.$daten['ende']) : null;
        if ($ende && $ende->lessThanOrEqualTo($beginn)) {
            $ende->addDay(); // Ende nach Mitternacht
        }

        $werte = [
            'veranstaltung_id' => $veranstaltung->id,
            'saal_id' => $daten['saal_id'] ?? Saal::orderBy('id')->value('id'),
            'einlass_um' => $einlass, 'beginn_um' => $beginn, 'ende_um' => $ende,
            'kontingent' => $daten['kontingent'] ?? null,
        ];

        $neu = ! $vorstellung;
        $vorstellung ??= new Vorstellung;
        $vorstellung->fill($werte)->save();
        Protokoll::schreibe($neu ? 'vorstellung.angelegt' : 'vorstellung.geaendert', $vorstellung, [
            'veranstaltung' => $veranstaltung->name, 'beginn' => $beginn->format('d.m.Y H:i'),
        ]);

        return redirect()->route('verein.veranstaltungen.show', $veranstaltung)->with('hinweis', 'Vorstellung am '.$vorstellung->anzeige().' gespeichert.');
    }

    public function vorstellungLoeschen(Veranstaltung $veranstaltung, Vorstellung $vorstellung): RedirectResponse
    {
        abort_if($vorstellung->veranstaltung_id !== $veranstaltung->id, 404);
        Protokoll::schreibe('vorstellung.geloescht', $vorstellung, ['beginn' => $vorstellung->beginn_um->format('d.m.Y H:i')]);
        $vorstellung->delete();

        return back()->with('hinweis', 'Vorstellung gelöscht.');
    }

    /** @return array<string, mixed> */
    private function pruefe(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'kurzname' => ['nullable', 'string', 'max:40'],
            'beschreibung' => ['nullable', 'string', 'max:20000'],
        ], [], ['name' => 'Name', 'kurzname' => 'Kurzname', 'beschreibung' => 'Beschreibung']);
    }
}
