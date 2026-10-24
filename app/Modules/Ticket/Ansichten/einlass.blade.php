@extends('layouts.basis')

@section('titel', 'Einlass')

@section('inhalt')
    <section class="karte karte--schmal">
        <div class="kasse-kopf">
            <h1>Einlass</h1>
            @unless ($station)
                <a class="knopf knopf--zweit" href="{{ route('tickets.abendkasse.show', $v) }}">← Menü</a>
            @endunless
        </div>
        <p class="kasse-termin" title="{{ $v->veranstaltung->name }}">{{ $v->anzeige() }}@if ($unterzeile) · {{ $unterzeile }}@endif</p>
        <p class="kasse-zahlen kasse-zahlen--einfach"><span class="kasse-frei">Eingelassen <strong data-zaehler="eingelassen">{{ $zaehler['eingelassen'] }}</strong></span><span>von <span data-zaehler="belegt">{{ $zaehler['belegt'] }}</span></span></p>

        @include('ticket::teile.scanner', ['aktion' => $aktion, 'json' => true, 'pause' => true, 'knopf' => 'Prüfen'])

        <p class="hinweis">Grün = einlassen · Gelb = schon eingelassen (Wiedereinlass selbst entscheiden) · Rot = nicht einlassen.</p>
        @if ($station)
            <form method="post" action="{{ route('station.abmelden') }}" onsubmit="return confirm('Von der Station abmelden?')" style="margin-top:1.5rem">
                @csrf <button type="submit" class="link-knopf">Von der Station abmelden</button>
            </form>
        @endif
    </section>
@endsection
