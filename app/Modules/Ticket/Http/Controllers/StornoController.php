<?php

namespace App\Modules\Ticket\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Ticket\Dienste\Stornierung;
use App\Modules\Ticket\Dienste\TicketCode;
use App\Modules\Ticket\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Stornieren mit dem sensiblen Recht „Stornieren“ (E1, E4b, E119): Karten werden gesammelt
 * (einzeln eingetippt oder per Dauer-Scan) und dann gemeinsam storniert.
 */
class StornoController extends Controller
{
    public function __construct(private Stornierung $stornierung, private Mandantenkontext $kontext) {}

    public function index(Request $request): View
    {
        return view('ticket::storno.index', ['liste' => $this->liste($request), 'dienst' => $this->stornierung]);
    }

    public function erfassen(Request $request): JsonResponse|RedirectResponse
    {
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:200']])['code'];
        $ticket = TicketCode::finde($code, true);

        if (! $ticket) {
            $ergebnis = ['farbe' => 'rot', 'titel' => 'Nicht gefunden', 'text' => 'Keine gültige Karte dieses Vereins.'];
        } elseif ($grund = $this->stornierung->hinderungsgrund($ticket)) {
            $ergebnis = ['farbe' => 'rot', 'titel' => $ticket->nummer, 'text' => $grund];
        } elseif (in_array($ticket->id, $this->ids($request), true)) {
            $ergebnis = ['farbe' => 'gelb', 'titel' => $ticket->nummer, 'text' => 'ist schon in der Liste'];
        } else {
            $request->session()->put($this->schluessel(), array_merge($this->ids($request), [$ticket->id]));
            $ergebnis = ['farbe' => 'gruen', 'titel' => $ticket->nummer, 'text' => 'zur Liste hinzugefügt'.($this->stornierung->hinweis($ticket) ? ' – '.$this->stornierung->hinweis($ticket) : '')];
        }

        if ($request->expectsJson()) {
            return response()->json($ergebnis + ['html' => view('ticket::storno.liste', ['liste' => $this->liste($request), 'dienst' => $this->stornierung])->render()]);
        }

        return $ergebnis['farbe'] === 'gruen'
            ? back()->with('hinweis', $ergebnis['titel'].' '.$ergebnis['text'])
            : back()->withErrors(['code' => $ergebnis['titel'].': '.$ergebnis['text']]);
    }

    public function entfernen(Request $request, Ticket $ticket): RedirectResponse
    {
        $request->session()->put($this->schluessel(), array_values(array_diff($this->ids($request), [$ticket->id])));

        return back();
    }

    public function leeren(Request $request): RedirectResponse
    {
        $request->session()->forget($this->schluessel());

        return back()->with('hinweis', 'Liste geleert – nichts wurde storniert.');
    }

    public function ausfuehren(Request $request): RedirectResponse
    {
        try {
            $nummern = $this->stornierung->stornieren($this->ids($request), $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }
        $request->session()->forget($this->schluessel());

        return back()->with('hinweis', count($nummern).' Karten storniert. Die Plätze sind wieder frei.');
    }

    /** @return list<int> */
    private function ids(Request $request): array
    {
        return array_values(array_map('intval', (array) $request->session()->get($this->schluessel(), [])));
    }

    private function liste(Request $request): Collection
    {
        return Ticket::with(['vorstellung.veranstaltung', 'kategorie'])->whereIn('id', $this->ids($request))->orderBy('nummer')->get();
    }

    private function schluessel(): string
    {
        return 'storno_liste.'.$this->kontext->id();
    }
}
