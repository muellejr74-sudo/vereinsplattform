<?php

namespace App\Modules\Kern\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Dienste\Tischverwaltung;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Tischcode;
use App\Modules\Kern\Models\Veranstaltung;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Scan-Weiche für Tisch-QR-Codes (E53, E70, E72b, E106) – in Stufe 2 noch mit Platzhaltern (E109).
 *
 * Wer angemeldet ist und Tische zuordnen darf, bekommt statt der Gästeansicht das
 * Zuordnungsformular (E106). Alle anderen sehen die Gästeseite.
 */
class ScanController extends Controller
{
    public function __invoke(Request $request, string $code, Tischverwaltung $tische, Mandantenkontext $kontext): View|RedirectResponse
    {
        $tischcode = Tischcode::where('code', strtoupper($code))->first();

        if (! $tischcode) {
            return view('kern::scan.werbung', ['ungueltig' => true]);
        }

        $benutzer = $request->user();
        if ($benutzer && ! $request->boolean('gast')) {
            $darfZuordnen = $kontext->istGesetzt() && $benutzer->can('qrcodes.verwalten')
                && ($tischcode->istBetreiberTisch() || $tischcode->mandant_id === $kontext->id());

            if ($darfZuordnen) {
                return redirect()->route('verein.tische.zuordnen', ['code' => $tischcode->code]);
            }
            if (! $kontext->istGesetzt() && $benutzer->waehlbareVereine()->isNotEmpty()) {
                return view('kern::scan.verein-waehlen', ['code' => $tischcode->code]);
            }
        }

        $zuordnung = $tische->aktuelleZuordnung($tischcode);
        if (! $zuordnung) {
            return view('kern::scan.werbung', ['ungueltig' => false]);
        }

        $verein = Mandant::where('aktiv', true)->find($zuordnung->mandant_id);
        if (! $verein) {
            return view('kern::scan.werbung', ['ungueltig' => false]);
        }

        // Gästeansicht im Design des Vereins (Platzhalter bis Stufe 7).
        $kontext->setze($verein);
        $veranstaltung = Veranstaltung::find($zuordnung->veranstaltung_id);

        return view('kern::scan.veranstaltung', [
            'verein' => $verein,
            'veranstaltung' => $veranstaltung,
            'tischnummer' => $zuordnung->tischnummer,
            'laufend' => $veranstaltung?->laufendeVorstellung(),
        ]);
    }
}
