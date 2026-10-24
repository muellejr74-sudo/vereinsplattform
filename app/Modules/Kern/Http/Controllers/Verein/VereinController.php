<?php

namespace App\Modules\Kern\Http\Controllers\Verein;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Protokolleintrag;
use App\Modules\Kern\Models\Vereinstext;
use App\Modules\Kern\Rechte\Rechtekatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Vereinsverwaltung: Übersicht, Vereinsimpressum (E83), Protokoll des Vereins (E97). */
class VereinController extends Controller
{
    public function __construct(private Mandantenkontext $kontext) {}

    public function uebersicht(): View
    {
        $verein = $this->kontext->mandant();

        return view('kern::verein.uebersicht', [
            'verein' => $verein,
            'module' => array_intersect_key(Rechtekatalog::MODULE, array_flip($verein->module())),
        ]);
    }

    public function rechtstexte(): View
    {
        return view('kern::verein.rechtstexte', [
            'verein' => $this->kontext->mandant(),
            'texte' => Vereinstext::pluck('inhalt', 'art'),
        ]);
    }

    public function rechtstexteSpeichern(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            'texte' => ['array'],
            'texte.*' => ['nullable', 'string', 'max:50000'],
        ]);
        $verein = $this->kontext->mandant();

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

        return back()->with('hinweis', 'Die Texte wurden gespeichert.');
    }

    /** Nur Einträge dieses Vereins – Plattformvorgänge und andere Vereine bleiben unsichtbar (E97). */
    public function protokoll(Request $request): View
    {
        $filter = $request->validate(['aktion' => ['nullable', 'string', 'max:60']]);
        $verein = $this->kontext->mandant();

        $eintraege = Protokolleintrag::where('mandant_id', $verein->id)
            ->when($filter['aktion'] ?? null, fn ($q, $aktion) => $q->where('aktion', $aktion))
            ->orderByDesc('zeitpunkt')->orderByDesc('id')
            ->paginate(50)->withQueryString();

        return view('kern::verein.protokoll', [
            'verein' => $verein,
            'eintraege' => $eintraege,
            'aktionen' => Protokoll::BEZEICHNUNGEN,
            'filter' => $filter,
        ]);
    }
}
