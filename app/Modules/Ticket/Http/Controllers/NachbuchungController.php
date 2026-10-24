<?php

namespace App\Modules\Ticket\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Dienste\Abschlusssperre;
use App\Modules\Ticket\Dienste\Geld;
use App\Modules\Ticket\Dienste\Kontingent;
use App\Modules\Ticket\Dienste\Nachbuchung;
use App\Modules\Ticket\Models\Ticketkategorie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/** Nachbuchen bis zum Abschluss (E129) – Recht „Abschluss durchführen“ oder „Abendkasse“. */
class NachbuchungController extends Controller
{
    public function __construct(private Nachbuchung $nachbuchung) {}

    public function show(Vorstellung $vorstellung, Kontingent $kontingent): View
    {
        return view('ticket::auswertung.nachbuchen', [
            'v' => $vorstellung->load('veranstaltung'),
            'kategorien' => Ticketkategorie::where('veranstaltung_id', $vorstellung->veranstaltung_id)->where('ist_freikarte', false)->orderBy('reihenfolge')->get(),
            'frei' => $kontingent->frei($vorstellung),
            'abgeschlossen' => Abschlusssperre::istAbgeschlossen($vorstellung->id),
        ]);
    }

    public function verkauf(Request $request, Vorstellung $vorstellung): RedirectResponse
    {
        $daten = $request->validate([
            'mengen' => ['array'], 'mengen.*' => ['nullable', 'integer', 'min:0', 'max:500'],
            'zahlungsart' => ['required', Rule::in(['bar', 'karte', 'ueberweisung'])],
        ], ['zahlungsart.required' => 'Bitte die Zahlungsart wählen.']);

        try {
            $e = $this->nachbuchung->verkauf($vorstellung, $daten['mengen'] ?? [], $daten['zahlungsart'], $request->user());
        } catch (InvalidArgumentException $x) {
            return back()->withInput()->withErrors(['nachbuchung' => $x->getMessage()]);
        }

        return back()->with('hinweis', $e['anzahl'].' '.($e['anzahl'] === 1 ? 'Karte' : 'Karten').' nachgebucht · '.Geld::anzeigen($e['betrag_cent']).'.');
    }

    public function freikarten(Request $request, Vorstellung $vorstellung): RedirectResponse
    {
        $daten = $request->validate(['anzahl' => ['required', 'integer', 'min:1', 'max:500'], 'name' => ['required', 'string', 'max:80']], [], ['name' => 'Name', 'anzahl' => 'Anzahl']);

        try {
            $e = $this->nachbuchung->freikarten($vorstellung, (int) $daten['anzahl'], $daten['name'], $request->user());
        } catch (InvalidArgumentException $x) {
            return back()->withInput()->withErrors(['nachbuchung' => $x->getMessage()]);
        }

        return back()->with('hinweis', $e['anzahl'].' Freikarten „'.trim($daten['name']).'“ nachgebucht.');
    }
}
