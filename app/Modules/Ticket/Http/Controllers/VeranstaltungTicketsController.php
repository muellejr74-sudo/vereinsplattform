<?php

namespace App\Modules\Ticket\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Models\Veranstaltung;
use App\Modules\Kern\Models\Vorstellung;
use App\Modules\Ticket\Dienste\Abschlusssperre;
use App\Modules\Ticket\Dienste\Geld;
use App\Modules\Ticket\Dienste\Kontingent;
use App\Modules\Ticket\Models\Auftragsposition;
use App\Modules\Ticket\Models\Preisabweichung;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\Ticket\Models\Ticketkategorie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Kategorien, Preise (E38) und Kontingente (E39) einer Veranstaltung. */
class VeranstaltungTicketsController extends Controller
{
    public function show(Request $request, Veranstaltung $veranstaltung, Kontingent $kontingent): View
    {
        abort_unless($request->user()->can('ticket.kategorien') || $request->user()->can('ticket.kontingente') || $request->user()->can('ticket.drucken'), 403);
        Ticketkategorie::freikarte($veranstaltung);

        $vorstellungen = $veranstaltung->vorstellungen()->get();

        return view('ticket::veranstaltung', [
            'veranstaltung' => $veranstaltung,
            'kategorien' => Ticketkategorie::where('veranstaltung_id', $veranstaltung->id)->orderBy('reihenfolge')->orderBy('id')->get(),
            'vorstellungen' => $vorstellungen,
            'zahlen' => $vorstellungen->mapWithKeys(fn ($v) => [$v->id => $kontingent->uebersicht($v)]),
            'abweichungen' => Preisabweichung::whereIn('vorstellung_id', $vorstellungen->pluck('id'))->get()
                ->groupBy('vorstellung_id')->map(fn ($g) => $g->pluck('preis_cent', 'kategorie_id')),
        ]);
    }

    /** Reihenfolge per Ziehen oder Pfeilen (Wunsch des Betreibers): Liste der Kategorie-Kennungen in neuer Folge. */
    public function kategorienReihenfolge(Request $request, Veranstaltung $veranstaltung): JsonResponse
    {
        $ids = array_map('intval', (array) $request->validate(['ids' => ['required', 'array', 'max:200'], 'ids.*' => ['integer']])['ids']);
        $kategorien = Ticketkategorie::where('veranstaltung_id', $veranstaltung->id)->where('ist_freikarte', false)->whereIn('id', $ids)->get()->keyBy('id');
        abort_if($kategorien->count() !== count(array_unique($ids)), 422);
        foreach (array_values(array_unique($ids)) as $i => $id) {
            $kategorien[$id]->update(['reihenfolge' => ($i + 1) * 10]);
        }

        return response()->json(['ok' => true]);
    }

    public function kategorieSpeichern(Request $request, Veranstaltung $veranstaltung, ?Ticketkategorie $kategorie = null): RedirectResponse
    {
        abort_if($kategorie && ($kategorie->veranstaltung_id !== $veranstaltung->id || $kategorie->ist_freikarte), 404);
        $daten = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'preis' => ['required', 'string', 'max:10'],
            'reihenfolge' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [], ['name' => 'Name', 'preis' => 'Preis']);

        $preis = Geld::ausEingabe($daten['preis']);
        if ($preis === null) {
            return back()->withInput()->withErrors(['preis' => 'Bitte einen Preis wie 12,00 eingeben.']);
        }
        if (mb_strtolower(trim($daten['name'])) === mb_strtolower(Ticketkategorie::FREIKARTE)) {
            return back()->withInput()->withErrors(['name' => 'Die Kategorie „Freikarte“ gibt es bereits automatisch.']);
        }

        $werte = ['veranstaltung_id' => $veranstaltung->id, 'name' => trim($daten['name']), 'preis_cent' => $preis];
        if (isset($daten['reihenfolge'])) {
            $werte['reihenfolge'] = $daten['reihenfolge'];
        } elseif (! $kategorie) {
            // neue Kategorie ans Ende; beim Bearbeiten bleibt die Reihenfolge (Verschieben per Ziehen/Pfeilen)
            $werte['reihenfolge'] = (int) Ticketkategorie::where('veranstaltung_id', $veranstaltung->id)->where('ist_freikarte', false)->max('reihenfolge') + 10;
        }

        if ($kategorie) {
            $vorher = $kategorie->preis_cent;
            $kategorie->update($werte);
            Protokoll::schreibe('ticket.preis_geaendert', $kategorie, ['name' => $kategorie->name, 'vorher' => Geld::anzeigen($vorher), 'nachher' => Geld::anzeigen($preis)]);
        } else {
            $kategorie = Ticketkategorie::create($werte);
            Protokoll::schreibe('ticket.preis_geaendert', $kategorie, ['name' => $kategorie->name, 'neu' => Geld::anzeigen($preis)]);
        }

        return back()->with('hinweis', 'Kategorie „'.$kategorie->name.'“ gespeichert. Bereits erzeugte Karten behalten ihren Preis.');
    }

    public function kategorieLoeschen(Veranstaltung $veranstaltung, Ticketkategorie $kategorie): RedirectResponse
    {
        abort_if($kategorie->veranstaltung_id !== $veranstaltung->id || $kategorie->ist_freikarte, 404);
        if (Ticket::where('kategorie_id', $kategorie->id)->exists() || Auftragsposition::where('kategorie_id', $kategorie->id)->exists()) {
            return back()->withErrors(['kategorie' => 'Die Kategorie wird bereits verwendet und kann nicht gelöscht werden.']);
        }
        Protokoll::schreibe('ticket.preis_geaendert', $kategorie, ['name' => $kategorie->name, 'geloescht' => true]);
        $kategorie->delete();

        return back()->with('hinweis', 'Kategorie gelöscht.');
    }

    /** Preisabweichungen einer Vorstellung (leer = Normalpreis). */
    public function abweichungenSpeichern(Request $request, Veranstaltung $veranstaltung, Vorstellung $vorstellung): RedirectResponse
    {
        abort_if($vorstellung->veranstaltung_id !== $veranstaltung->id, 404);
        $daten = $request->validate(['preise' => ['array'], 'preise.*' => ['nullable', 'string', 'max:10']]);
        $kategorien = Ticketkategorie::where('veranstaltung_id', $veranstaltung->id)->where('ist_freikarte', false)->get()->keyBy('id');

        foreach ($daten['preise'] ?? [] as $kategorieId => $eingabe) {
            if (! $kategorien->has((int) $kategorieId)) {
                continue;
            }
            if (blank($eingabe)) {
                Preisabweichung::where('vorstellung_id', $vorstellung->id)->where('kategorie_id', $kategorieId)->delete();

                continue;
            }
            $preis = Geld::ausEingabe($eingabe);
            if ($preis === null) {
                return back()->withErrors(['preise' => 'Bitte Preise wie 12,00 eingeben.']);
            }
            Preisabweichung::updateOrCreate(['vorstellung_id' => $vorstellung->id, 'kategorie_id' => (int) $kategorieId], ['preis_cent' => $preis]);
        }
        Protokoll::schreibe('ticket.preis_geaendert', $vorstellung, ['abweichungen' => array_filter($daten['preise'] ?? [])]);

        return back()->with('hinweis', 'Preise für die Vorstellung am '.$vorstellung->anzeige().' gespeichert.');
    }

    public function kontingentSpeichern(Request $request, Veranstaltung $veranstaltung, Vorstellung $vorstellung, Kontingent $kontingent): RedirectResponse
    {
        abort_if($vorstellung->veranstaltung_id !== $veranstaltung->id, 404);
        if (Abschlusssperre::istAbgeschlossen($vorstellung->id)) {
            return back()->withErrors(['kontingent' => Abschlusssperre::TEXT]);
        }
        $daten = $request->validate(['kontingent' => ['required', 'integer', 'min:0', 'max:100000']], [], ['kontingent' => 'Kontingent']);
        $belegt = $kontingent->belegt($vorstellung);

        if ($daten['kontingent'] < $belegt) {
            return back()->withErrors(['kontingent' => 'Es sind bereits '.$belegt.' Plätze belegt – das Kontingent kann nicht kleiner sein.']);
        }

        $vorher = $vorstellung->kontingent;
        $vorstellung->update(['kontingent' => $daten['kontingent']]);
        Protokoll::schreibe('ticket.kontingent_geaendert', $vorstellung, ['vorher' => $vorher, 'nachher' => (int) $daten['kontingent']]);

        return back()->with('hinweis', 'Kontingent gespeichert.');
    }
}
