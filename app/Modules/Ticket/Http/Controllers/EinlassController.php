<?php

namespace App\Modules\Ticket\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Dienste\Einsatz;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Stationsgeraet;
use App\Modules\Ticket\Dienste\Einlass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Einlass an der Station „Einlass“ – ohne Benutzerkonto, über den Einsatz-Code (E29, E119, E122). */
class EinlassController extends Controller
{
    public function __construct(private Einsatz $einsatz, private Einlass $einlass) {}

    public function show(Request $request): View|RedirectResponse
    {
        $geraet = $this->geraet($request);
        if (! $geraet) {
            return redirect()->route('station');
        }

        return view('ticket::einlass', [
            'geraet' => $geraet,
            'akteur' => $this->einsatz->akteur($geraet),
            'zaehler' => $this->einlass->zaehler($geraet),
        ]);
    }

    public function scannen(Request $request): JsonResponse|RedirectResponse
    {
        $geraet = $this->geraet($request);
        if (! $geraet) {
            return $request->expectsJson()
                ? response()->json(['farbe' => 'rot', 'titel' => 'Abgemeldet', 'text' => 'Dieses Gerät ist nicht mehr als Einlass angemeldet.'], 403)
                : redirect()->route('station');
        }

        $code = (string) $request->validate(['code' => ['required', 'string', 'max:200']])['code'];
        $ergebnis = $this->einlass->scannen($geraet, $code, $this->einsatz->akteur($geraet));
        $zaehler = $this->einlass->zaehler($geraet);

        if ($request->expectsJson()) {
            return response()->json($ergebnis + ['zaehler' => $zaehler]);
        }

        return redirect()->route('station.einlass')->with('scan', $ergebnis);
    }

    private function geraet(Request $request): ?Stationsgeraet
    {
        $geraet = $this->einsatz->geraetAusAnfrage($request);
        if (! $geraet || $geraet->station->art !== 'einlass' || ! Mandant::find($geraet->mandant_id)?->hatModul('ticket')) {
            return null;
        }

        return $geraet;
    }
}
