@extends('layouts.basis')

@section('titel', 'Einlass')

@section('inhalt')
    <section class="karte karte--schmal">
        <div class="titelzeile">
            <h1>Einlass</h1>
            <span class="hinweis">{{ $akteur }}</span>
        </div>
        <p class="hinweis">{{ $geraet->vorstellung->veranstaltung->name }} · {{ $geraet->vorstellung->anzeige() }}</p>
        <p class="zaehler">Eingelassen: <strong data-zaehler="eingelassen">{{ $zaehler['eingelassen'] }}</strong> von <span data-zaehler="belegt">{{ $zaehler['belegt'] }}</span></p>

        @include('ticket::teile.scanner', ['aktion' => route('station.einlass'), 'json' => true, 'knopf' => 'Einlassen'])

        <p class="hinweis">Grün = einlassen · Gelb = schon eingelassen (Wiedereinlass selbst entscheiden) · Rot = nicht einlassen.</p>
        <form method="post" action="{{ route('station.abmelden') }}" onsubmit="return confirm('Von der Station abmelden?')" style="margin-top:1.5rem">
            @csrf <button type="submit" class="link-knopf">Von der Station abmelden</button>
        </form>
    </section>
@endsection
