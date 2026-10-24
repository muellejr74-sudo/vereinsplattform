@extends('layouts.basis')

@section('titel', $verein->exists ? 'Verein bearbeiten' : 'Verein anlegen')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>{{ $verein->exists ? 'Verein bearbeiten' : 'Verein anlegen' }}</h1>
        @include('kern::teile.fehler')

        <form method="post" action="{{ $verein->exists ? route('verwaltung.vereine.update', $verein) : route('verwaltung.vereine.store') }}" class="formular">
            @csrf
            @if ($verein->exists) @method('put') @endif

            <label for="name">Name des Vereins</label>
            <input id="name" name="name" type="text" value="{{ old('name', $verein->name) }}" maxlength="120" required autofocus>

            <label for="kuerzel">Kürzel</label>
            <input id="kuerzel" name="kuerzel" type="text" value="{{ old('kuerzel', $verein->kuerzel) }}" maxlength="6" required class="eingabe--kurz" style="text-transform: uppercase">
            <small class="hinweis">2 bis 6 Großbuchstaben oder Ziffern, z. B. <code>TVM</code>. Das Kürzel steht später vorne in jeder Ticketnummer (z. B. TVM-26-00412)
                und sollte deshalb nach dem ersten Ticketverkauf nicht mehr geändert werden.</small>

            <div class="knopfzeile">
                <button type="submit" class="knopf">Speichern</button>
                <a href="{{ route('verwaltung.vereine.index') }}">Abbrechen</a>
            </div>
        </form>
        @if ($verein->exists)
            <p class="hinweis"><a href="{{ route('verwaltung.vereine.design', $verein) }}">Logo, Vereinsfarben und Module bearbeiten</a></p>
        @endif
    </section>
@endsection
