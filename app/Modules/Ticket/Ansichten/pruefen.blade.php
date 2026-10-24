@extends('layouts.basis')

@section('titel', 'Eintrittskarte')

@section('inhalt')
    <section class="karte karte--schmal">
        @if ($ticket && $ticket->status !== 'storniert')
            <div class="meldung meldung--erfolg">✔ Gültige Eintrittskarte</div>
            <h1>{{ $ticket->vorstellung->veranstaltung->name }}</h1>
            <p>{{ $ticket->vorstellung->anzeige() }}</p>
            <p class="hinweis">Karte {{ $ticket->nummer }}. Bitte beim Einlass vorzeigen.</p>
        @elseif ($ticket)
            <div class="meldung meldung--fehler">Diese Eintrittskarte wurde storniert und ist ungültig.</div>
        @else
            <div class="meldung meldung--fehler">Diese Eintrittskarte ist nicht gültig.</div>
        @endif
    </section>
@endsection
