@extends('layouts.basis')

@section('titel', config('app.name'))

@section('inhalt')
    <section class="karte karte--schmal">
        @if ($ungueltig)
            <div class="meldung meldung--hinweis">Dieser Code ist nicht mehr gültig.</div>
        @endif
        <h1>{{ config('app.name') }}</h1>
        <p>Digitale Speisekarte, Bestellungen am Tisch und Eintrittskarten für Vereine und Feste.</p>
        <p class="hinweis">Platzhalter: Hier erscheint ab Stufe 8 die Werbeseite des Plattformbetreibers.</p>
    </section>
@endsection
