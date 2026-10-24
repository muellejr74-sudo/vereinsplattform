@extends('layouts.basis')

@section('titel', $akteur)

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>{{ $akteur }}</h1>
        <p class="hinweis">{{ $geraet->vorstellung->veranstaltung->name }} · {{ $geraet->vorstellung->anzeige() }}</p>
        <div class="meldung meldung--erfolg">Dieses Handy ist für heute als <strong>{{ $geraet->station->name }}</strong> angemeldet.</div>
        <p>Die Funktionen der Station folgen in den nächsten Ausbaustufen:
            Einlass (Tickets scannen) in Stufe 4, Bedienung, Ausschank und Küche in Stufe 6.</p>
        <form method="post" action="{{ route('station.abmelden') }}" onsubmit="return confirm('Von der Station abmelden?')">
            @csrf <button type="submit" class="knopf knopf--zweit">Abmelden</button>
        </form>
    </section>
@endsection
