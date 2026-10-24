@extends('layouts.basis')

@section('titel', 'Freikarten ausgeben')

@section('inhalt')
    @include('ticket::teile.mengen')
    <section class="karte karte--schmal">
        @include('ticket::abendkasse.kopf', ['titel' => 'Freikarten ausgeben'])
        @forelse ($freikarten as $a)
            @php $offen = $a->tickets->whereIn('status', ['erzeugt', 'gedruckt'])->count(); @endphp
            <form method="post" action="{{ route('tickets.abendkasse.freikarten', [$v, $a]) }}" class="formular formular--zeile" style="margin:0.75rem 0" onsubmit="return confirm('Freikarten ausgeben? Nicht ausgegebene werden storniert.')">
                @csrf
                <span style="flex:1"><strong>{{ $a->name }}</strong><br>{{ $offen }} Freikarten</span>
                <input data-menge name="anzahl" type="number" min="1" max="{{ $offen }}" value="{{ $offen }}" aria-label="Anzahl">
                <button type="submit" class="knopf">Ausgeben</button>
            </form>
        @empty
            <p class="hinweis">Keine hinterlegten Freikarten.</p>
        @endforelse
    </section>
@endsection
