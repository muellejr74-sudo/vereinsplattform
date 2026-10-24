@extends('layouts.basis')

@section('titel', 'Abendkasse')

@section('inhalt')
    <div class="kasse-kopf">
        <h1>Abendkasse</h1>
        <a class="symbol-knopf" href="{{ route('tickets.abendkasse.index') }}" title="Vorstellung wechseln" aria-label="Vorstellung wechseln">
            <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h14l-4-4M20 16H6l4 4"/></svg>
        </a>
    </div>
    <p class="kasse-termin" title="{{ $v->veranstaltung->name }}">{{ $v->anzeige() }}</p>
    <p class="kasse-zahlen"><span class="kasse-frei">Frei <strong>{{ $zahlen['frei'] ?? '–' }}</strong></span><span>belegt {{ $zahlen['belegt'] }}/{{ $zahlen['kontingent'] ?? '–' }}</span><span>eingelassen {{ $zahlen['eingelassen'] }}</span></p>
    @include('kern::teile.fehler')

    <nav class="menue-knoepfe" aria-label="Abendkasse">
        <a class="menue-knopf menue-knopf--haupt" href="{{ route('tickets.abendkasse.verkauf', $v) }}">
            <strong>Karten direkt verkaufen</strong><span>Spontanverkauf, bar oder mit Karte</span>
        </a>
        <a class="menue-knopf" href="{{ route('tickets.abendkasse.einlass', $v) }}">
            <strong>Einlass scannen</strong><span>Karten am Eingang prüfen</span>
        </a>
        <a class="menue-knopf" href="{{ route('tickets.abendkasse.reservierungen', $v) }}">
            <strong>Reservierung abholen</strong><span>{{ $offen }} offen</span>
        </a>
        <a class="menue-knopf" href="{{ route('tickets.abendkasse.bestand.scan', $v) }}">
            <strong>Gedruckte Karte verkaufen</strong><span>aus dem Abendkassen-Bestand scannen</span>
        </a>
        @if ($freikarten > 0)
            <a class="menue-knopf" href="{{ route('tickets.abendkasse.freikarten.liste', $v) }}">
                <strong>Freikarten ausgeben</strong><span>{{ $freikarten }} Stapel hinterlegt</span>
            </a>
        @endif
        <a class="menue-knopf" href="{{ route('tickets.abendkasse.nicht_abgeholt', $v) }}">
            <strong>Nicht abgeholte Reservierungen</strong>
            <span>{{ now()->lt($nichtAbgeholtAb) ? 'ab '.$nichtAbgeholtAb->format('H:i').' Uhr' : $offen.' noch offen' }}</span>
        </a>
    </nav>
    <p class="hinweis" style="margin-top:1rem"><a href="{{ route('tickets.notfallliste', $v) }}" target="_blank">Notfallliste (PDF)</a> – für den Fall eines Internetausfalls vorab ausdrucken.@can('tickets.nachbuchen') · <a href="{{ route('tickets.nachbuchen', $v) }}">Nachbuchen</a>@endcan</p>
@endsection
