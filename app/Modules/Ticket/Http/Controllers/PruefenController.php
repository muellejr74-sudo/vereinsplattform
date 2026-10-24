<?php

namespace App\Modules\Ticket\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Ticket\Models\Ticket;
use Illuminate\View\View;

/**
 * Öffentliche Prüfseite des Ticket-QR-Codes (E112): zeigt nur „gültig für …“, keine Namen.
 * Ohne passendes Prüfzeichen wird keine Nummer anerkannt. Der Einlass folgt in Stufe 4.
 */
class PruefenController extends Controller
{
    public function __invoke(string $code, Mandantenkontext $kontext): View
    {
        $ticket = null;
        if (preg_match('/^([A-Z0-9]{2,6})-(\d{2})-(\d{5,})\.([A-Z0-9]{6})$/', strtoupper($code), $teile)) {
            $verein = Mandant::where('kuerzel', $teile[1])->where('aktiv', true)->first();
            if ($verein) {
                $ticket = $kontext->fuer($verein, function () use ($teile) {
                    $kandidat = Ticket::with('vorstellung.veranstaltung')->where('nummer', $teile[1].'-'.$teile[2].'-'.$teile[3])->first();

                    return $kandidat && hash_equals($kandidat->pruefzeichen, $teile[4]) ? $kandidat : null;
                });
                if ($ticket) {
                    $kontext->setze($verein); // Vereinsdesign anzeigen
                }
            }
        }

        return view('ticket::pruefen', ['ticket' => $ticket]);
    }
}
