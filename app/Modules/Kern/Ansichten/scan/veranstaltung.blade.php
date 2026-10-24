@extends('layouts.basis')

@section('titel', $veranstaltung?->name ?? $verein->name)

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>{{ $veranstaltung?->name }}</h1>
        @if ($tischnummer)<p class="tischnummer">Tisch {{ $tischnummer }}</p>@endif
        @if ($laufend)
            <p>Heute: {{ $laufend->anzeige() }}</p>
        @endif
        <p class="hinweis">Platzhalter: Hier erscheint ab Stufe 7 das Gästeportal mit Speise- und Getränkekarte.</p>
        <p class="hinweis">
            <a href="{{ route('verein.impressum', $verein->kuerzel) }}">Impressum des Vereins</a> ·
            <a href="{{ route('verein.datenschutz', $verein->kuerzel) }}">Datenschutz</a>
        </p>
    </section>
@endsection
