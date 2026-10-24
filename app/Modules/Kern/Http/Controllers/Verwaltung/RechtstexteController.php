<?php

namespace App\Modules\Kern\Http\Controllers\Verwaltung;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Models\Plattformtext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Plattform-Impressum und zentrale Datenschutzerklärung pflegen (E83). */
class RechtstexteController extends Controller
{
    public function bearbeiten(): View
    {
        return view('kern::verwaltung.rechtstexte', [
            'texte' => Plattformtext::pluck('inhalt', 'art'),
        ]);
    }

    public function speichern(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            'texte' => ['array'],
            'texte.*' => ['nullable', 'string', 'max:100000'],
        ]);

        foreach (array_keys(Plattformtext::ARTEN) as $art) {
            $inhalt = trim((string) ($daten['texte'][$art] ?? ''));
            if ($inhalt === (string) Plattformtext::inhalt($art)) {
                continue;
            }

            $text = Plattformtext::updateOrCreate(['art' => $art], ['inhalt' => $inhalt]);
            Protokoll::schreibe('rechtstext.geaendert', $text, ['art' => $art], null, null);
        }

        return back()->with('hinweis', 'Die Rechtstexte wurden gespeichert.');
    }
}
