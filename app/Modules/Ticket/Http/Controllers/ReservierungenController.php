<?php

namespace App\Modules\Ticket\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Dienste\Kontingent;
use App\Modules\Ticket\Dienste\Reservierungsverwaltung;
use App\Modules\Ticket\Dienste\TicketZugriff;
use App\Modules\Ticket\Models\Reservierung;
use App\Modules\Ticket\Models\Ticketeinstellung;
use App\Modules\Ticket\Models\Ticketkategorie;
use App\Modules\Ticket\Models\Verkaufsstelle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Telefonische Reservierungen und Vorkasse (E40, E40a, E120, E124–E127).
 * Annehmen: Recht „Reservierungen“ (für den Verein) oder Zuordnung zu einer Vorverkaufsstelle.
 * Zahlungseingang: nur mit dem sensiblen Recht „Zahlungseingang vermerken“ (E125).
 */
class ReservierungenController extends Controller
{
    public function __construct(private TicketZugriff $zugriff, private Reservierungsverwaltung $verwaltung) {}

    public function index(Request $request): View
    {
        $benutzer = $request->user();
        abort_unless($this->zugriff->darfReservieren($benutzer) || $this->zugriff->siehtAlleReservierungen($benutzer), 403);
        $filter = $request->validate([
            'status' => ['nullable', Rule::in(['offen', 'zahlung', 'ueberfaellig', 'bezahlt', 'erledigt', 'storniert', 'alle'])],
            'vorstellung' => ['nullable', 'integer'],
            'suche' => ['nullable', 'string', 'max:80'],
        ]);
        $status = $filter['status'] ?? 'offen';

        $abfrage = Reservierung::with(['vorstellung.veranstaltung', 'positionen', 'tickets', 'verkaufsstelle'])
            ->when($status === 'offen', fn ($q) => $q->where('status', 'offen'))
            ->when($status === 'zahlung', fn ($q) => $q->where('status', 'offen')->where('art', 'vorkasse'))
            ->when($status === 'ueberfaellig', fn ($q) => $q->ueberfaellig())
            ->when($status === 'bezahlt', fn ($q) => $q->where('status', 'bezahlt'))
            ->when($status === 'erledigt', fn ($q) => $q->whereIn('status', ['abgeholt', 'versendet']))
            ->when($status === 'storniert', fn ($q) => $q->where('status', 'storniert'))
            ->when(filled($filter['vorstellung'] ?? null), fn ($q) => $q->where('vorstellung_id', $filter['vorstellung']))
            ->when(filled($filter['suche'] ?? null), fn ($q) => $q->where('name', 'like', '%'.$filter['suche'].'%'))
            ->orderByDesc('id');

        $ueberfaellig = Reservierung::ueberfaellig();
        if (! $this->zugriff->siehtAlleReservierungen($benutzer)) {
            $eigene = $this->zugriff->reservierungsStellen($benutzer)->pluck('id');
            $abfrage->whereIn('verkaufsstelle_id', $eigene);
            $ueberfaellig->whereIn('verkaufsstelle_id', $eigene);
        }

        return view('ticket::reservierungen.index', [
            'reservierungen' => $abfrage->paginate(30)->withQueryString(),
            'status' => $status,
            'suche' => $filter['suche'] ?? '',
            'ueberfaellig' => $ueberfaellig->count(),
            'darfAnlegen' => $this->zugriff->darfReservieren($benutzer),
            'kasse' => $benutzer->can('ticket.zahlungseingang'),
        ]);
    }

    public function create(Request $request): View
    {
        $benutzer = $request->user();
        abort_unless($this->zugriff->darfReservieren($benutzer), 403);

        $vorstellungen = Vorstellung::with('veranstaltung')->where('beginn_um', '>=', now()->startOfDay())->orderBy('beginn_um')->get();
        $kategorien = Ticketkategorie::whereIn('veranstaltung_id', $vorstellungen->pluck('veranstaltung_id')->unique())
            ->where('ist_freikarte', false)->orderBy('reihenfolge')->get()->groupBy('veranstaltung_id');
        $einstellungen = Ticketeinstellung::aktuell();

        return view('ticket::reservierungen.formular', [
            'vorstellungen' => $vorstellungen,
            'kategorien' => $kategorien,
            'stellen' => $this->zugriff->reservierungsStellen($benutzer),
            'verein' => $this->zugriff->darfIntern($benutzer),
            'einstellungen' => $einstellungen,
            'vorauswahl' => (int) $request->query('vorstellung'),
            'frist' => today()->addDays($einstellungen->zahlungsfrist_tage)->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $benutzer = $request->user();
        abort_unless($this->zugriff->darfReservieren($benutzer), 403);
        $daten = $request->validate([
            'verkaufsstelle_id' => ['nullable', 'integer'],
            'vorstellung_id' => ['required', 'integer'],
            'art' => ['required', Rule::in(array_keys(Reservierung::ARTEN))],
            'mengen' => ['array'],
            'mengen.*' => ['nullable', 'integer', 'min:0', 'max:500'],
            'name' => ['required', 'string', 'max:80'],
            'telefon' => ['nullable', 'string', 'max:40', 'regex:/^[0-9 +\/()\-]*$/'],
            'strasse' => ['nullable', 'string', 'max:120'],
            'plz' => ['nullable', 'string', 'max:10'],
            'ort' => ['nullable', 'string', 'max:80'],
            'bemerkung' => ['nullable', 'string', 'max:255'],
            'zahlungsfrist' => ['nullable', 'date'],
        ], ['telefon.regex' => 'Bitte nur Ziffern, Leerzeichen und + / ( ) - verwenden.'], [
            'name' => 'Name', 'telefon' => 'Telefon', 'vorstellung_id' => 'Vorstellung', 'zahlungsfrist' => 'Zahlungsfrist',
        ]);

        $stelle = null;
        if (filled($daten['verkaufsstelle_id'] ?? null)) {
            $stelle = Verkaufsstelle::findOrFail($daten['verkaufsstelle_id']);
            abort_unless($this->zugriff->reservierungsStellen($benutzer)->contains('id', $stelle->id), 403);
        } else {
            abort_unless($this->zugriff->darfIntern($benutzer), 403);
        }

        try {
            $reservierung = $this->verwaltung->anlegen(Vorstellung::findOrFail($daten['vorstellung_id']), $stelle, $daten['art'], $daten['mengen'] ?? [], $daten, $benutzer);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['reservierung' => $e->getMessage()]);
        }

        return redirect()->route('tickets.reservierungen.show', $reservierung)->with('hinweis', 'Reservierung '.$reservierung->kennung().' angelegt.');
    }

    public function show(Request $request, Reservierung $reservierung, Kontingent $kontingent): View
    {
        abort_unless($this->zugriff->darfReservierungSehen($request->user(), $reservierung), 404);
        $reservierung->load(['vorstellung.veranstaltung', 'positionen.kategorie', 'tickets.kategorie', 'verkaufsstelle', 'kartenauftrag', 'angelegtVon']);
        $einstellungen = Ticketeinstellung::aktuell();

        return view('ticket::reservierungen.show', [
            'r' => $reservierung,
            'einstellungen' => $einstellungen,
            'darfBearbeiten' => $this->zugriff->darfReservierungBearbeiten($request->user(), $reservierung),
            'kasse' => $request->user()->can('ticket.zahlungseingang'),
            'postKnapp' => $reservierung->art === 'vorkasse' && $reservierung->status === 'offen'
                && $reservierung->vorstellung->beginn_um->lt(now()->addDays($einstellungen->zahlungsfrist_tage)),
        ]);
    }

    public function bezahlt(Request $request, Reservierung $reservierung): RedirectResponse
    {
        abort_unless($this->zugriff->darfReservierungSehen($request->user(), $reservierung), 404);

        return $this->ausfuehren(fn () => $this->verwaltung->bezahlt($reservierung, $request->user()),
            'Zahlung vermerkt. Die Karten sind erzeugt und als Druckauftrag (mit Adressaufkleber) bei der Druckstelle.');
    }

    public function abholung(Request $request, Reservierung $reservierung): RedirectResponse
    {
        abort_unless($this->zugriff->darfReservierungBearbeiten($request->user(), $reservierung), 404);

        return $this->ausfuehren(fn () => $this->verwaltung->aufAbholungUmstellen($reservierung, $request->user()),
            'Umgestellt: Die Karten werden an der Abendkasse abgeholt und dort bezahlt.');
    }

    public function stornieren(Request $request, Reservierung $reservierung): RedirectResponse
    {
        abort_unless($this->zugriff->darfReservierungBearbeiten($request->user(), $reservierung), 404);

        return $this->ausfuehren(fn () => $this->verwaltung->stornieren($reservierung, $request->user()),
            'Reservierung storniert – die Plätze sind wieder frei.');
    }

    /** Überfällige Vorkasse-Reservierungen gesammelt stornieren (E126). */
    public function ueberfaelligeStornieren(Request $request): RedirectResponse
    {
        $daten = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']], ['ids.required' => 'Bitte mindestens eine Reservierung auswählen.']);
        $anzahl = 0;
        foreach (Reservierung::ueberfaellig()->whereIn('id', $daten['ids'])->get() as $r) {
            if ($this->zugriff->darfReservierungBearbeiten($request->user(), $r)) {
                $this->verwaltung->stornieren($r, $request->user(), 'Zahlungsfrist abgelaufen');
                $anzahl++;
            }
        }

        return back()->with('hinweis', $anzahl.' überfällige Reservierungen storniert.');
    }

    private function ausfuehren(callable $schritt, string $erfolg): RedirectResponse
    {
        try {
            $schritt();
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['reservierung' => $e->getMessage()]);
        }

        return back()->with('hinweis', $erfolg);
    }
}
