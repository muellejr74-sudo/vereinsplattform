@extends('layouts.basis')

@section('titel', 'Nicht abgeholte Reservierungen')

@section('inhalt')
    <section class="karte karte--schmal">
        @include('ticket::abendkasse.kopf', ['titel' => 'Nicht abgeholte Reservierungen'])
        @if (now()->lt($nichtAbgeholtAb))
            <p>Die Liste erscheint ab <strong>{{ $nichtAbgeholtAb->format('H:i') }} Uhr</strong>.</p>
        @elseif ($nichtAbgeholt->isEmpty())
            <p>Alle Reservierungen sind abgeholt.</p>
        @else
            <form method="post" action="{{ route('tickets.abendkasse.nicht_abgeholt', $v) }}" onsubmit="return confirm('Ausgewählte Reservierungen stornieren? Die Plätze werden frei.')">
                @csrf
                @foreach ($nichtAbgeholt as $r)
                    <label class="zusatz" style="display:block;margin:0.5rem 0"><input type="checkbox" name="ids[]" value="{{ $r->id }}" checked> {{ $r->name }} – {{ $r->anzahl() }} Karten ({{ $r->kennung() }}{{ $r->art === 'vorkasse' ? ', Vorkasse unbezahlt' : '' }})</label>
                @endforeach
                <p class="hinweis">Wer noch kommen soll: Haken entfernen.</p>
                <button type="submit" class="knopf knopf--gross">Ausgewählte stornieren</button>
            </form>
        @endif
    </section>
@endsection
