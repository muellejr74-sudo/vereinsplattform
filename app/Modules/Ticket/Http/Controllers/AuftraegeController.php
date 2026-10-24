<?php

namespace App\Modules\Ticket\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Dienste\Auftragsverwaltung;
use App\Modules\Ticket\Dienste\EtikettPdf;
use App\Modules\Ticket\Dienste\Kontingent;
use App\Modules\Ticket\Dienste\TicketZugriff;
use App\Modules\Ticket\Models\Kartenauftrag;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Models\Ticketkategorie;
use App\Modules\Ticket\Models\Verkaufsstelle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kartenaufträge und Freikarten-Stapel (E24–E31, E114, E114c, E116, E117).
 * Anfordern: zugeordnete Personen einer Verkaufsstelle bzw. Recht „Reservierungen“ (intern, nur Freikarten).
 * Bestätigen, Drucken, Übergeben: Recht „Druckaufträge drucken“ (Druckstelle).
 */
class AuftraegeController extends Controller
{
    public function __construct(private TicketZugriff $zugriff, private Auftragsverwaltung $auftraege) {}

    public function index(Request $request): View
    {
        $benutzer = $request->user();
        $filter = $request->validate(['status' => ['nullable', Rule::in(array_merge(['offen', 'alle'], array_keys(Kartenauftrag::STATUS)))]]);
        $status = $filter['status'] ?? 'offen';

        $abfrage = Kartenauftrag::with(['vorstellung.veranstaltung', 'verkaufsstelle', 'positionen.kategorie'])
            ->when($status === 'offen', fn ($q) => $q->whereIn('status', ['angefordert', 'bestaetigt', 'gedruckt']))
            ->when(! in_array($status, ['offen', 'alle'], true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('id');

        if (! $this->zugriff->istDruckstelle($benutzer)) {
            $stellen = $this->zugriff->stellenVon($benutzer)->pluck('id');
            $intern = $this->zugriff->darfIntern($benutzer);
            $abfrage->where(fn ($q) => $q->whereIn('verkaufsstelle_id', $stellen)->when($intern, fn ($q) => $q->orWhereNull('verkaufsstelle_id')));
        }

        return view('ticket::auftraege.index', [
            'auftraege' => $abfrage->paginate(30)->withQueryString(),
            'status' => $status,
            'druckstelle' => $this->zugriff->istDruckstelle($benutzer),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $benutzer = $request->user();
        $art = $request->query('art') === 'freikarten' ? 'freikarten' : 'karten';
        $stellen = $this->zugriff->stellenVon($benutzer);
        $intern = $art === 'freikarten' && $this->zugriff->darfIntern($benutzer);

        if ($stellen->isEmpty() && ! $intern) {
            abort(403, 'Sie sind keiner Verkaufsstelle zugeordnet.');
        }

        $vorstellungen = Vorstellung::with('veranstaltung')->where('beginn_um', '>=', now()->startOfDay())->orderBy('beginn_um')->get();
        $kategorien = Ticketkategorie::whereIn('veranstaltung_id', $vorstellungen->pluck('veranstaltung_id')->unique())
            ->where('ist_freikarte', false)->orderBy('reihenfolge')->get()->groupBy('veranstaltung_id');

        return view('ticket::auftraege.formular', [
            'art' => $art,
            'stellen' => $stellen,
            'intern' => $intern,
            'vorstellungen' => $vorstellungen,
            'kategorien' => $kategorien,
            'vorauswahl' => (int) $request->query('vorstellung'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $benutzer = $request->user();
        $daten = $request->validate([
            'art' => ['required', Rule::in(['karten', 'freikarten'])],
            'verkaufsstelle_id' => ['nullable', 'integer'],
            'vorstellung_id' => ['required', 'integer'],
            'mengen' => ['array'],
            'mengen.*' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'anzahl' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'name' => ['nullable', 'string', 'max:80'],
            'bemerkung' => ['nullable', 'string', 'max:255'],
        ], [], ['vorstellung_id' => 'Vorstellung', 'anzahl' => 'Anzahl', 'name' => 'Name']);

        $stelle = filled($daten['verkaufsstelle_id'] ?? null) ? Verkaufsstelle::findOrFail($daten['verkaufsstelle_id']) : null;
        abort_unless($this->zugriff->darfAnfordern($benutzer, $stelle, $daten['art']), 403, 'Keine Berechtigung, für diese Stelle anzufordern.');
        $vorstellung = Vorstellung::findOrFail($daten['vorstellung_id']);

        $mengen = $daten['art'] === 'freikarten'
            ? [Ticketkategorie::freikarte($vorstellung->veranstaltung)->id => (int) ($daten['anzahl'] ?? 0)]
            : ($daten['mengen'] ?? []);

        try {
            $auftrag = $this->auftraege->anfordern($vorstellung, $stelle, $daten['art'], $mengen, $daten['name'] ?? null, $daten['bemerkung'] ?? null, $benutzer);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['auftrag' => $e->getMessage()]);
        }

        return redirect()->route('tickets.auftraege.show', $auftrag)
            ->with('hinweis', 'Der Auftrag wurde an die Druckstelle übermittelt. Plätze werden erst mit der Bestätigung belegt.');
    }

    public function show(Request $request, Kartenauftrag $auftrag, Kontingent $kontingent): View
    {
        abort_unless($this->zugriff->darfAuftragSehen($request->user(), $auftrag), 404);
        $auftrag->load(['vorstellung.veranstaltung', 'verkaufsstelle', 'positionen.kategorie', 'tickets.kategorie', 'anfordernder']);

        return view('ticket::auftraege.show', [
            'auftrag' => $auftrag,
            'druckstelle' => $this->zugriff->istDruckstelle($request->user()),
            'zahlen' => $kontingent->uebersicht($auftrag->vorstellung),
        ]);
    }

    public function bestaetigen(Request $request, Kartenauftrag $auftrag): RedirectResponse
    {
        $daten = $request->validate(['mengen' => ['array'], 'mengen.*' => ['integer', 'min:0', 'max:2000']]);

        try {
            $tickets = $this->auftraege->bestaetigen($auftrag, array_map('intval', $daten['mengen'] ?? []), $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['auftrag' => $e->getMessage()]);
        }

        return back()->with('hinweis', $tickets->count().' Karten erzeugt ('.$tickets->first()->nummer.' bis '.$tickets->last()->nummer.'). Jetzt die Etiketten drucken.');
    }

    public function ablehnen(Request $request, Kartenauftrag $auftrag): RedirectResponse
    {
        try {
            $this->auftraege->ablehnen($auftrag, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['auftrag' => $e->getMessage()]);
        }

        return back()->with('hinweis', 'Der Auftrag wurde abgelehnt.');
    }

    public function zurueckziehen(Request $request, Kartenauftrag $auftrag): RedirectResponse
    {
        abort_unless($this->zugriff->darfAuftragSehen($request->user(), $auftrag), 404);

        try {
            $this->auftraege->ablehnen($auftrag, $request->user(), 'zurueckgezogen');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['auftrag' => $e->getMessage()]);
        }

        return back()->with('hinweis', 'Der Auftrag wurde zurückgezogen.');
    }

    /** Etiketten-PDF aller noch nicht gedruckten Karten des Auftrags (E111). */
    public function etiketten(Kartenauftrag $auftrag, EtikettPdf $pdf): Response
    {
        $tickets = Ticket::with(['vorstellung.veranstaltung', 'kategorie'])->where('kartenauftrag_id', $auftrag->id)
            ->whereIn('status', $auftrag->status === 'bestaetigt' ? ['erzeugt'] : ['gedruckt', 'ausgegeben'])->orderBy('id')->get();
        abort_if($tickets->isEmpty(), 422, 'Keine Karten zum Drucken.');

        return $this->pdfAntwort($pdf->erzeuge($tickets), 'etiketten-auftrag-'.$auftrag->id.'.pdf');
    }

    public function gedruckt(Request $request, Kartenauftrag $auftrag): RedirectResponse
    {
        $anzahl = $this->auftraege->alsGedrucktMarkieren($auftrag, $request->user());

        return back()->with('hinweis', $anzahl.' Karten als gedruckt markiert. Sie können jetzt übergeben werden.');
    }

    public function uebergeben(Request $request, Kartenauftrag $auftrag): RedirectResponse
    {
        try {
            $anzahl = $this->auftraege->uebergeben($auftrag, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['auftrag' => $e->getMessage()]);
        }

        return back()->with('hinweis', $anzahl.' Karten übergeben – sie gelten jetzt als „ausgegeben“.');
    }

    /** Nachdruck einer einzelnen Karte mit derselben Nummer (E117). */
    public function nachdruck(Request $request, Ticket $ticket, EtikettPdf $pdf): Response
    {
        try {
            $this->auftraege->nachdruck($ticket, $request->user());
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return $this->pdfAntwort($pdf->erzeuge(collect([$ticket->load(['vorstellung.veranstaltung', 'kategorie'])])), 'nachdruck-'.$ticket->nummer.'.pdf');
    }

    private function pdfAntwort(string $inhalt, string $datei): Response
    {
        return response($inhalt, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$datei.'"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
