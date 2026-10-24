<?php

namespace App\Modules\Ticket\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Dienste\Abendkasse;
use App\Modules\Ticket\Dienste\Geld;
use App\Modules\Ticket\Dienste\Kontingent;
use App\Modules\Ticket\Dienste\Reservierungsverwaltung;
use App\Modules\Ticket\Dienste\TicketCode;
use App\Modules\Ticket\Models\Kartenauftrag;
use App\Modules\Ticket\Models\Reservierung;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Models\Ticketkategorie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/** Abendkasse mit echtem Benutzerkonto und Recht „Abendkasse“ (E32, E41–E46a, E114, E121, E123). */
class AbendkasseController extends Controller
{
    public function __construct(private Abendkasse $kasse) {}

    public function index(): View|RedirectResponse
    {
        // Geöffnet ist nur, was gerade im Zeitfenster liegt (E100); kommende werden mit Öffnungszeit gezeigt.
        $vorstellungen = Vorstellung::with('veranstaltung')->where('beginn_um', '>=', now()->subDay())->orderBy('beginn_um')->get()
            ->reject(fn ($v) => $v->istVorbei())->values();
        $offen = $vorstellungen->filter(fn ($v) => $v->istImZeitfenster())->values();
        if ($offen->count() === 1 && ! session()->has('errors')) {
            return redirect()->route('tickets.abendkasse.show', $offen->first());
        }

        return view('ticket::abendkasse.index', [
            'offen' => $offen,
            'kommend' => $vorstellungen->reject(fn ($v) => $v->istImZeitfenster())->take(5)->values(),
        ]);
    }

    /** Startmenü der Abendkasse: große Knöpfe, je Aufgabe eine eigene Seite (Wunsch des Betreibers bei der Abnahme). */
    public function show(Vorstellung $vorstellung, Kontingent $kontingent): View
    {
        $ab = $this->kasse->nichtAbgeholtAb($vorstellung);

        return view('ticket::abendkasse.menue', [
            'v' => $vorstellung->load('veranstaltung'),
            'zahlen' => $kontingent->uebersicht($vorstellung),
            'offen' => $this->kasse->offeneReservierungen($vorstellung)->count(),
            'freikarten' => $this->kasse->hinterlegteFreikarten($vorstellung)->count(),
            'nichtAbgeholtAb' => $ab,
        ]);
    }

    public function verkaufFormular(Vorstellung $vorstellung, Kontingent $kontingent): View
    {
        return view('ticket::abendkasse.verkauf', [
            'v' => $vorstellung->load('veranstaltung'),
            'zahlen' => $kontingent->uebersicht($vorstellung),
            'kategorien' => Ticketkategorie::where('veranstaltung_id', $vorstellung->veranstaltung_id)->where('ist_freikarte', false)->orderBy('reihenfolge')->get(),
        ]);
    }

    public function reservierungen(Request $request, Vorstellung $vorstellung): View
    {
        $suche = trim((string) $request->query('suche', ''));

        return view('ticket::abendkasse.reservierungen', [
            'v' => $vorstellung->load('veranstaltung'),
            'reservierungen' => $this->kasse->offeneReservierungen($vorstellung, $suche),
            'suche' => $suche,
        ]);
    }

    public function bestandFormular(Vorstellung $vorstellung): View
    {
        return view('ticket::abendkasse.bestand-scan', ['v' => $vorstellung->load('veranstaltung')]);
    }

    public function freikartenListe(Vorstellung $vorstellung): View
    {
        return view('ticket::abendkasse.freikarten', ['v' => $vorstellung->load('veranstaltung'), 'freikarten' => $this->kasse->hinterlegteFreikarten($vorstellung)]);
    }

    public function nichtAbgeholtListe(Vorstellung $vorstellung): View
    {
        $ab = $this->kasse->nichtAbgeholtAb($vorstellung);

        return view('ticket::abendkasse.nicht-abgeholt', [
            'v' => $vorstellung->load('veranstaltung'),
            'nichtAbgeholtAb' => $ab,
            'nichtAbgeholt' => now()->gte($ab) ? $this->kasse->offeneReservierungen($vorstellung) : collect(),
        ]);
    }

    public function verkauf(Request $request, Vorstellung $vorstellung): RedirectResponse
    {
        $daten = $request->validate([
            'mengen' => ['array'], 'mengen.*' => ['nullable', 'integer', 'min:0', 'max:200'],
            'zahlungsart' => ['required', Rule::in(['bar', 'karte'])],
        ], ['zahlungsart.required' => 'Bitte „Bar“ oder „Karte“ wählen.']);

        try {
            $ergebnis = $this->kasse->spontanverkauf($vorstellung, $daten['mengen'] ?? [], $daten['zahlungsart'], $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['kasse' => $e->getMessage()]);
        }

        return $this->menue($vorstellung)->with('hinweis', $ergebnis['anzahl'].' Karten verkauft · '.Geld::anzeigen($ergebnis['betrag_cent']).' '.$this->zahlText($daten['zahlungsart']).' · Gäste gelten als eingelassen.');
    }

    public function reservierung(Vorstellung $vorstellung, Reservierung $reservierung): View
    {
        abort_unless($reservierung->vorstellung_id === $vorstellung->id, 404);
        $reservierung->load(['positionen.kategorie', 'tickets']);

        return view('ticket::abendkasse.ausgabe', ['v' => $vorstellung->load('veranstaltung'), 'r' => $reservierung]);
    }

    public function ausgeben(Request $request, Vorstellung $vorstellung, Reservierung $reservierung, Reservierungsverwaltung $verwaltung): RedirectResponse
    {
        abort_unless($reservierung->vorstellung_id === $vorstellung->id, 404);
        $daten = $request->validate([
            'mengen' => ['array'], 'mengen.*' => ['integer', 'min:0', 'max:500'],
            'zahlungsart' => ['required', Rule::in(['bar', 'karte'])],
        ], ['zahlungsart.required' => 'Bitte „Bar“ oder „Karte“ wählen.']);

        try {
            $ergebnis = $verwaltung->ausgeben($reservierung, array_map('intval', $daten['mengen'] ?? []), $daten['zahlungsart'], $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['kasse' => $e->getMessage()]);
        }

        return $this->menue($vorstellung)->with('hinweis',
            $reservierung->name.': '.$ergebnis['anzahl'].' Karten ausgegeben · '.Geld::anzeigen($ergebnis['betrag_cent']).' '.$this->zahlText($daten['zahlungsart']).'.');
    }

    /** Karte aus dem gedruckten Bestand der Abendkasse scannen (E46a). */
    public function bestandScan(Request $request, Vorstellung $vorstellung): RedirectResponse
    {
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:200']])['code'];
        $ticket = TicketCode::finde($code, true);
        if (! $ticket) {
            return back()->withErrors(['kasse' => 'Karte nicht gefunden.']);
        }
        if ($fehler = $this->kasse->bestandPruefen($vorstellung, $ticket)) {
            return back()->withErrors(['kasse' => $ticket->nummer.': '.$fehler]);
        }

        return redirect()->route('tickets.abendkasse.bestand', [$vorstellung, $ticket]);
    }

    public function bestand(Vorstellung $vorstellung, Ticket $ticket): View|RedirectResponse
    {
        if ($fehler = $this->kasse->bestandPruefen($vorstellung, $ticket)) {
            return redirect()->route('tickets.abendkasse.bestand.scan', $vorstellung)->withErrors(['kasse' => $ticket->nummer.': '.$fehler]);
        }

        return view('ticket::abendkasse.bestand', ['v' => $vorstellung->load('veranstaltung'), 't' => $ticket->load('kategorie')]);
    }

    public function bestandVerkaufen(Request $request, Vorstellung $vorstellung, Ticket $ticket): RedirectResponse
    {
        $daten = $request->validate(['zahlungsart' => ['required', Rule::in(['bar', 'karte'])]]);
        try {
            $this->kasse->bestandVerkaufen($vorstellung, $ticket, $daten['zahlungsart'], $request->user());
        } catch (InvalidArgumentException $e) {
            return redirect()->route('tickets.abendkasse.bestand.scan', $vorstellung)->withErrors(['kasse' => $e->getMessage()]);
        }

        return $this->menue($vorstellung)
            ->with('hinweis', 'Karte '.$ticket->nummer.' verkauft · '.Geld::anzeigen($ticket->preis_cent).' '.$this->zahlText($daten['zahlungsart']).'.');
    }

    public function freikarten(Request $request, Vorstellung $vorstellung, Kartenauftrag $auftrag): RedirectResponse
    {
        abort_unless($auftrag->vorstellung_id === $vorstellung->id, 404);
        $anzahl = (int) $request->validate(['anzahl' => ['required', 'integer', 'min:1', 'max:2000']])['anzahl'];
        try {
            $this->kasse->freikartenAusgeben($auftrag, $anzahl, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['kasse' => $e->getMessage()]);
        }

        return $this->menue($vorstellung)->with('hinweis', 'Freikarten „'.$auftrag->name.'“: '.$anzahl.' ausgegeben.');
    }

    /** Nicht abgeholte Reservierungen halbautomatisch stornieren (E41, E121). */
    public function nichtAbgeholt(Request $request, Vorstellung $vorstellung, Reservierungsverwaltung $verwaltung): RedirectResponse
    {
        $daten = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']], ['ids.required' => 'Bitte mindestens eine Reservierung auswählen.']);
        if (now()->lt($ab = $this->kasse->nichtAbgeholtAb($vorstellung))) {
            return back()->withErrors(['kasse' => 'Nicht abgeholte Reservierungen können erst ab '.$ab->format('H:i').' Uhr storniert werden.']);
        }

        $anzahl = 0;
        foreach (Reservierung::where('vorstellung_id', $vorstellung->id)->where('status', 'offen')->whereIn('id', $daten['ids'])->get() as $r) {
            $verwaltung->stornieren($r, $request->user(), 'nicht abgeholt');
            $anzahl++;
        }

        return $this->menue($vorstellung)->with('hinweis', $anzahl.' nicht abgeholte Reservierungen storniert – die Plätze sind wieder frei.');
    }

    private function menue(Vorstellung $vorstellung): RedirectResponse
    {
        return redirect()->route('tickets.abendkasse.show', $vorstellung);
    }

    private function zahlText(string $art): string
    {
        return $art === 'bar' ? 'bar' : 'mit Karte';
    }
}
