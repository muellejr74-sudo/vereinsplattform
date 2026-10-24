@extends('layouts.basis')

@section('titel', 'Stornieren')

@section('inhalt')
    <div class="titelzeile">
        <h1>Karten stornieren</h1>
        <a class="knopf knopf--zweit" href="{{ route('tickets.uebersicht') }}">Übersicht</a>
    </div>
    @include('kern::teile.fehler')

    <section class="karte">
        <p>Karten nacheinander scannen (z. B. Rückgaben einer Vorverkaufsstelle) oder die Ticketnummer eintippen.
            Sie werden zuerst gesammelt und erst mit „Karten stornieren“ ungültig.</p>
        @include('ticket::teile.scanner', ['aktion' => route('tickets.storno.erfassen'), 'json' => true, 'liste' => '#storno-liste', 'knopf' => 'Hinzufügen'])
    </section>

    <section class="karte">
        <h2>Zum Stornieren gesammelt</h2>
        <div id="storno-liste">@include('ticket::storno.liste')</div>
    </section>
    <p class="hinweis">Eingelassene Karten können nicht storniert werden. Eine stornierte Nummer bleibt dauerhaft ungültig, der Platz wird wieder frei.</p>
@endsection
