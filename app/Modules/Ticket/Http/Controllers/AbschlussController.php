<?php

namespace App\Modules\Ticket\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Dienste\Abschlussverwaltung;
use App\Modules\Ticket\Dienste\Auswertung;
use App\Modules\Ticket\Models\Abschluss;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/** Abschluss je Vorstellung (E4, E4a, E4b, E131) – Recht „Abschluss durchführen“; Rückgängig nur Super-Admin. */
class AbschlussController extends Controller
{
    public function __construct(private Abschlussverwaltung $verwaltung) {}

    public function show(Vorstellung $vorstellung, Auswertung $auswertung): View
    {
        return view('ticket::auswertung.abschluss', [
            'v' => $vorstellung->load('veranstaltung'),
            'abschluss' => Abschluss::with('durch')->where('vorstellung_id', $vorstellung->id)->first(),
            'reste' => $this->verwaltung->reste($vorstellung),
            'z' => $auswertung->zahlen($vorstellung),
        ]);
    }

    public function bereinigen(Request $request, Vorstellung $vorstellung): RedirectResponse
    {
        $art = $request->validate(['art' => ['required', Rule::in(['reservierungen', 'auftraege', 'karten'])]])['art'];

        return $this->ausfuehren(fn () => $this->verwaltung->bereinigen($vorstellung, $art, $request->user()).' '.match ($art) {
            'reservierungen' => 'Reservierungen storniert.',
            'auftraege' => 'Aufträge abgelehnt.',
            default => 'Karten storniert.',
        });
    }

    public function abschliessen(Request $request, Vorstellung $vorstellung): RedirectResponse
    {
        return $this->ausfuehren(function () use ($vorstellung, $request) {
            $this->verwaltung->abschliessen($vorstellung, $request->user());

            return 'Abschluss durchgeführt. Die Vorstellung ist jetzt gesperrt.';
        });
    }

    public function rueckgaengig(Request $request, Vorstellung $vorstellung): RedirectResponse
    {
        abort_unless($request->user()->istSuperAdmin(), 403);
        $grund = (string) $request->validate(['grund' => ['required', 'string', 'min:5', 'max:255']], [], ['grund' => 'Grund'])['grund'];

        return $this->ausfuehren(function () use ($vorstellung, $grund, $request) {
            $this->verwaltung->rueckgaengig($vorstellung, $grund, $request->user());

            return 'Abschluss rückgängig gemacht. Der Vorgang steht im Protokoll.';
        });
    }

    private function ausfuehren(callable $schritt): RedirectResponse
    {
        try {
            $text = $schritt();
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['abschluss' => $e->getMessage()]);
        }

        return back()->with('hinweis', $text);
    }
}
