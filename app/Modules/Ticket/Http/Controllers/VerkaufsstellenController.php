<?php

namespace App\Modules\Ticket\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Ticket\Models\Verkaufsstelle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Vorverkaufsstellen und Abendkasse mit Benutzerzuordnung (E24, E46, E115) – Recht „Vorverkaufsstellen verwalten“. */
class VerkaufsstellenController extends Controller
{
    public function index(Mandantenkontext $kontext): View
    {
        return view('ticket::verkaufsstellen', [
            'stellen' => Verkaufsstelle::with('benutzer')->orderBy('art')->orderBy('name')->get(),
            'mitglieder' => $kontext->mandant()->benutzer()->where('aktiv', true)->orderBy('name')->get(),
        ]);
    }

    public function speichern(Request $request, Mandantenkontext $kontext, ?Verkaufsstelle $stelle = null): RedirectResponse
    {
        $mitglieder = $kontext->mandant()->benutzer()->pluck('users.id')->all();
        $daten = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'art' => ['required', Rule::in(array_keys(Verkaufsstelle::ARTEN))],
            'aktiv' => ['nullable', 'boolean'],
            'benutzer' => ['array'],
            'benutzer.*' => ['integer', Rule::in($mitglieder)], // nur Personen dieses Vereins
        ], [], ['name' => 'Name', 'art' => 'Art']);

        $neu = ! $stelle?->exists;
        $stelle ??= new Verkaufsstelle;
        $stelle->fill(['name' => trim($daten['name']), 'art' => $daten['art'], 'aktiv' => $neu ? true : $request->boolean('aktiv')])->save();
        $ergebnis = $stelle->benutzer()->sync($daten['benutzer'] ?? []);

        Protokoll::schreibe($neu ? 'ticket.verkaufsstelle_angelegt' : 'ticket.verkaufsstelle_geaendert', $stelle, [
            'name' => $stelle->name, 'hinzu' => $ergebnis['attached'], 'entfernt' => $ergebnis['detached'],
        ]);

        return back()->with('hinweis', Verkaufsstelle::ARTEN[$stelle->art].' „'.$stelle->name.'“ gespeichert.');
    }
}
