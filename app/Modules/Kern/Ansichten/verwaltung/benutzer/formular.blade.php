@extends('layouts.basis')

@section('titel', $person->exists ? 'Benutzer bearbeiten' : 'Benutzer anlegen')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>{{ $person->exists ? 'Benutzer bearbeiten' : 'Benutzer anlegen' }}</h1>
        @include('kern::teile.fehler')

        <form method="post" action="{{ $person->exists ? route('verwaltung.benutzer.update', $person) : route('verwaltung.benutzer.store') }}" class="formular">
            @csrf
            @if ($person->exists) @method('put') @endif

            <label for="name">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $person->name) }}" maxlength="120" required autofocus>

            <label for="email">E-Mail-Adresse (zugleich Anmeldename)</label>
            <input id="email" name="email" type="email" value="{{ old('email', $person->email) }}" maxlength="255" required>

            @if ($person->istSuperAdmin())
                <p class="hinweis">Super-Administrator – arbeitet in allen Vereinen.</p>
            @else
                <fieldset>
                    <legend>Vereine, in denen die Person arbeitet</legend>
                    @forelse ($vereine as $verein)
                        <label class="auswahl">
                            <input type="checkbox" name="vereine[]" value="{{ $verein->id }}" @checked(in_array($verein->id, old('vereine', $zugeordnet)))>
                            {{ $verein->name }} ({{ $verein->kuerzel }}) @unless ($verein->aktiv) – deaktiviert @endunless
                        </label>
                    @empty
                        <p class="hinweis">Noch keine Vereine angelegt.</p>
                    @endforelse
                </fieldset>
            @endif

            @unless ($person->exists)
                <p class="hinweis">Nach dem Speichern erhält die Person eine E-Mail mit einem Link (3 Tage gültig), über den sie ihr Passwort selbst festlegt.</p>
            @endunless

            <div class="knopfzeile">
                <button type="submit" class="knopf">Speichern</button>
                <a href="{{ route('verwaltung.benutzer.index') }}">Abbrechen</a>
            </div>
        </form>
        <p class="hinweis">Rechte und Rollen (z. B. Vereinsadmin) folgen in Stufe 1b.</p>
    </section>
@endsection
