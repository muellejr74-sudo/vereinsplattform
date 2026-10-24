@extends('layouts.basis')

@section('titel', 'Karte verkaufen')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>Karte {{ $t->nummer }}</h1>
        <p>{{ $v->veranstaltung->name }}, {{ $v->anzeige() }}</p>
        <p>{{ $t->kategorie->name }} · <span class="summe">{{ \App\Modules\Ticket\Dienste\Geld::anzeigen($t->preis_cent) }}</span></p>
        <form method="post" action="{{ route('tickets.abendkasse.bestand', [$v, $t]) }}" class="knopfzeile knopfzeile--voll">
            @csrf
            <button type="submit" name="zahlungsart" value="bar" class="knopf knopf--gross">Bar bezahlt</button>
            <button type="submit" name="zahlungsart" value="karte" class="knopf knopf--gross knopf--zweit">Mit Karte bezahlt</button>
        </form>
        <p style="margin-top:1rem"><a href="{{ route('tickets.abendkasse.show', $v) }}">Abbrechen</a></p>
    </section>
@endsection
