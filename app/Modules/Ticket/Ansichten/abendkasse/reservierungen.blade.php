@extends('layouts.basis')

@section('titel', 'Reservierung abholen')

@section('inhalt')
    <section class="karte karte--schmal">
        @include('ticket::abendkasse.kopf', ['titel' => 'Reservierung abholen'])
        <form method="get" class="formular formular--zeile">
            <input name="suche" value="{{ $suche }}" placeholder="Name oder Nummer" aria-label="Reservierung suchen" style="flex:1">
            <button type="submit" class="knopf knopf--zweit">Suchen</button>
        </form>
        <div class="menue-knoepfe" style="margin-top:0.75rem">
            @forelse ($reservierungen as $r)
                <a class="menue-knopf" href="{{ route('tickets.abendkasse.reservierung', [$v, $r]) }}">
                    <strong>{{ $r->name }}</strong>
                    <span>{{ $r->anzahl() }} Karten · {{ \App\Modules\Ticket\Dienste\Geld::anzeigen($r->betragCent()) }} · {{ $r->kennung() }}</span>
                    <span class="hinweis">{{ $r->statusText() }}</span>
                </a>
            @empty
                <p class="hinweis">{{ $suche ? 'Keine passende offene Reservierung.' : 'Keine offenen Reservierungen.' }}</p>
            @endforelse
        </div>
    </section>
@endsection
