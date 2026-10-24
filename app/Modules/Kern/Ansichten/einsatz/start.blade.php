@extends('layouts.basis')

@section('titel', $akteur)

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>{{ $akteur }}</h1>
        <p class="hinweis">{{ $geraet->vorstellung->veranstaltung->name }} · {{ $geraet->vorstellung->anzeige() }}</p>
        <div class="meldung meldung--erfolg">Dieses Handy ist für heute als <strong>{{ $geraet->station->name }}</strong> angemeldet.</div>
        <p>Die Funktionen für Bedienung, Ausschank und Küche folgen mit den Saalbestellungen (Stufe 6).</p>
        <form method="post" action="{{ route('station.abmelden') }}" onsubmit="return confirm('Von der Station abmelden?')">
            @csrf <button type="submit" class="knopf knopf--zweit">Abmelden</button>
        </form>
    </section>
@endsection
