@extends('layouts.basis')

@section('titel', 'Abendkasse')

@section('inhalt')
    <div class="titelzeile">
        <h1>Abendkasse</h1>
        <a class="knopf knopf--zweit" href="{{ route('tickets.abendkasse.index') }}">Vorstellung wechseln</a>
    </div>
    <p><strong>{{ $v->veranstaltung->name }}</strong> · {{ $v->anzeige() }}</p>
    <p class="zaehler">Frei: <strong>{{ $zahlen['frei'] ?? '–' }}</strong> · belegt {{ $zahlen['belegt'] }} von {{ $zahlen['kontingent'] ?? '–' }} · eingelassen {{ $zahlen['eingelassen'] }}</p>
    @include('kern::teile.fehler')

    <nav class="menue-knoepfe" aria-label="Abendkasse">
        <a class="menue-knopf menue-knopf--haupt" href="{{ route('tickets.abendkasse.verkauf', $v) }}">
            <strong>Karten direkt verkaufen</strong><span>Spontanverkauf, bar oder mit Karte</span>
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
@endsection
