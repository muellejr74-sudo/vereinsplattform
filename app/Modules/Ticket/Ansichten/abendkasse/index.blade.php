@extends('layouts.basis')

@section('titel', 'Abendkasse')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>Abendkasse</h1>
        @if ($vorstellungen->isEmpty())
            <p>Keine kommenden Vorstellungen.</p>
        @else
            <p>Für welche Vorstellung?</p>
            <div class="stationswahl">
                @foreach ($vorstellungen as $v)
                    <a class="station-knopf" href="{{ route('tickets.abendkasse.show', $v) }}">
                        <strong>{{ $v->veranstaltung->name }}</strong><span>{{ $v->anzeige() }}</span>
                        @if ($v->istImZeitfenster())<span class="status status--fertig">läuft</span>@endif
                    </a>
                @endforeach
            </div>
        @endif
    </section>
@endsection
