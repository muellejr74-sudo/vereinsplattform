<?php

namespace App\Modules\Ticket\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Dienste\Auftragsverwaltung;
use App\Modules\Ticket\Dienste\EtikettPdf;
use App\Modules\Ticket\Dienste\Kontingent;
use App\Modules\Ticket\Dienste\Selbstdruck;
use App\Modules\Ticket\Dienste\TicketZugriff;
use App\Modules\Ticket\Models\Kartenauftrag;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Models\Ticketkategorie;
use App\Modules\Ticket\Models\Verkaufsstelle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/** Verkaufsstelle druckt selbst (E128): verkaufen, Etiketten drucken, eigene Karten nachdrucken. */
class SelbstdruckController extends Controller
{
    public function __construct(private TicketZugriff $zugriff) {}

    public function create(Request $request, Kontingent $kontingent): View
    {
        $stellen = $this->zugriff->selbstdruckStellen($request->user());
        abort_if($stellen->isEmpty(), 403, 'Ihre Verkaufsstelle druckt nicht selbst.');

        $vorstellungen = Vorstellung::with('veranstaltung')->where('beginn_um', '>=', now()->startOfDay())->orderBy('beginn_um')->get()
            ->reject(fn ($v) => $v->istVorbei())->values();

        return view('ticket::selbstdruck.formular', [
            'stellen' => $stellen,
            'vorstellungen' => $vorstellungen,
            'kategorien' => Ticketkategorie::whereIn('veranstaltung_id', $vorstellungen->pluck('veranstaltung_id')->unique())
                ->where('ist_freikarte', false)->orderBy('reihenfolge')->get()->groupBy('veranstaltung_id'),
            'frei' => $vorstellungen->mapWithKeys(fn ($v) => [$v->id => $kontingent->frei($v)])->all(),
            'vorauswahl' => (int) $request->query('vorstellung'),
        ]);
    }

    public function store(Request $request, Selbstdruck $dienst): RedirectResponse
    {
        $daten = $request->validate([
            'verkaufsstelle_id' => ['required', 'integer'],
            'vorstellung_id' => ['required', 'integer'],
            'mengen' => ['array'], 'mengen.*' => ['nullable', 'integer', 'min:0', 'max:500'],
            'name' => ['nullable', 'string', 'max:80'],
        ], [], ['vorstellung_id' => 'Vorstellung', 'name' => 'Name']);

        $stelle = Verkaufsstelle::findOrFail($daten['verkaufsstelle_id']);
        abort_unless($this->zugriff->selbstdruckStellen($request->user())->contains('id', $stelle->id), 403);

        try {
            $auftrag = $dienst->verkaufen($stelle, Vorstellung::findOrFail($daten['vorstellung_id']), $daten['mengen'] ?? [], $daten['name'] ?? null, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['verkauf' => $e->getMessage()]);
        }

        return redirect()->route('tickets.selbstdruck.show', $auftrag);
    }

    public function show(Request $request, Kartenauftrag $auftrag): View
    {
        $this->pruefen($request, $auftrag);
        $auftrag->load(['vorstellung.veranstaltung', 'tickets.kategorie', 'verkaufsstelle']);

        return view('ticket::selbstdruck.show', ['auftrag' => $auftrag]);
    }

    public function etiketten(Request $request, Kartenauftrag $auftrag, EtikettPdf $pdf): Response
    {
        $this->pruefen($request, $auftrag);
        $tickets = Ticket::with(['vorstellung.veranstaltung', 'kategorie'])->where('kartenauftrag_id', $auftrag->id)
            ->whereIn('status', ['ausgegeben', 'verkauft'])->orderBy('id')->get();
        abort_if($tickets->isEmpty(), 404);

        return response($pdf->erzeuge($tickets), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="etiketten-'.$auftrag->id.'.pdf"',
        ]);
    }

    public function nachdruck(Request $request, Ticket $ticket, Auftragsverwaltung $auftraege, EtikettPdf $pdf): Response
    {
        abort_unless($ticket->kartenauftrag && $this->darf($request, $ticket->kartenauftrag), 404);
        try {
            $auftraege->nachdruck($ticket, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['verkauf' => $e->getMessage()]);
        }

        return response($pdf->erzeuge(collect([$ticket->load(['vorstellung.veranstaltung', 'kategorie'])])), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="nachdruck-'.$ticket->nummer.'.pdf"',
        ]);
    }

    private function darf(Request $request, Kartenauftrag $auftrag): bool
    {
        return $auftrag->verkaufsstelle_id !== null
            && $this->zugriff->selbstdruckStellen($request->user())->contains('id', $auftrag->verkaufsstelle_id);
    }

    private function pruefen(Request $request, Kartenauftrag $auftrag): void
    {
        abort_unless($this->darf($request, $auftrag), 404);
    }
}
