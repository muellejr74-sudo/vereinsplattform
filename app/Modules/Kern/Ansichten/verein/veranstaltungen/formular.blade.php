@extends('layouts.basis')

@section('titel', $veranstaltung->exists ? 'Veranstaltung bearbeiten' : 'Veranstaltung anlegen')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>{{ $veranstaltung->exists ? 'Veranstaltung bearbeiten' : 'Veranstaltung anlegen' }}</h1>
        @include('kern::teile.fehler')
        <form method="post" action="{{ $veranstaltung->exists ? route('verein.veranstaltungen.update', $veranstaltung) : route('verein.veranstaltungen.store') }}" class="formular">
            @csrf @if ($veranstaltung->exists) @method('put') @endif
            <label for="name">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $veranstaltung->name) }}" maxlength="160" required autofocus placeholder="z. B. Theater 2026 – Der Hauptmann von Köpenick">

            <label for="kurzname">Kurzname (für Eintrittskarten, optional)</label>
            <input id="kurzname" name="kurzname" type="text" value="{{ old('kurzname', $veranstaltung->kurzname) }}" maxlength="40" placeholder="z. B. Hauptmann v. Köpenick">

            <label for="beschreibung">Beschreibung (optional)</label>
            <textarea id="beschreibung" name="beschreibung" rows="5">{{ old('beschreibung', $veranstaltung->beschreibung) }}</textarea>
            <small class="hinweis">Infos und Bilder für das Gästeportal folgen mit Stufe 7.</small>

            <div class="knopfzeile">
                <button type="submit" class="knopf">Speichern</button>
                <a href="{{ $veranstaltung->exists ? route('verein.veranstaltungen.show', $veranstaltung) : route('verein.veranstaltungen.index') }}">Abbrechen</a>
            </div>
        </form>
    </section>
@endsection
