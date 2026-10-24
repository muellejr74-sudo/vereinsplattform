<?php

namespace App\Modules\Kern\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Http\Middleware\SetzeMandantenkontext;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Startseite: für Gäste ein Willkommen mit Anmeldelink, nach der Anmeldung die
 * Übersicht mit Vereinsauswahl (E21a).
 */
class StartseiteController extends Controller
{
    public function anzeigen(Request $request, Mandantenkontext $kontext): View
    {
        $benutzer = $request->user();

        if (! $benutzer) {
            return view('startseite');
        }

        return view('kern::start', [
            'benutzer' => $benutzer,
            'vereine' => $benutzer->waehlbareVereine(),
            'aktiverVerein' => $kontext->mandant(),
        ]);
    }

    public function vereinWechseln(Request $request): RedirectResponse
    {
        $daten = $request->validate(['mandant_id' => ['nullable', 'integer']]);

        if (empty($daten['mandant_id'])) {
            $request->session()->forget(SetzeMandantenkontext::SITZUNG);

            return redirect()->route('startseite');
        }

        $mandant = Mandant::find($daten['mandant_id']);

        // Prüfung auf dem Server: Nur Vereine, in denen die Person Mitglied ist (Super-Admin: alle aktiven).
        abort_unless($mandant && $request->user()->darfInVerein($mandant), 403, 'Kein Zugriff auf diesen Verein.');

        $request->session()->put(SetzeMandantenkontext::SITZUNG, $mandant->id);

        return redirect()->route('startseite')->with('hinweis', 'Sie arbeiten jetzt im '.$mandant->typBezeichnung().' „'.$mandant->name.'“.');
    }
}
