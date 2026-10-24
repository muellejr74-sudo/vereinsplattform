@extends('layouts.basis')

@section('titel', 'Start')

@section('inhalt')
    <section class="karte">
        <h1>Hallo {{ $benutzer->name }}</h1>

        @if ($vereine->isEmpty())
            @if ($benutzer->istSuperAdmin())
                <p>Es ist noch kein Verein angelegt. <a href="{{ route('verwaltung.vereine.create') }}">Jetzt einen Verein anlegen</a>.</p>
            @else
                <p>Ihrem Konto ist noch kein Verein zugeordnet. Bitte wenden Sie sich an den Plattformbetreiber.</p>
            @endif
        @else
            <form method="post" action="{{ route('verein.wechseln') }}" class="formular formular--zeile">
                @csrf
                <label for="mandant_id">Ich arbeite gerade im Verein</label>
                <select id="mandant_id" name="mandant_id">
                    @if ($benutzer->istSuperAdmin() || ! $aktiverVerein)
                        <option value="">{{ $benutzer->istSuperAdmin() ? '– kein Verein (Plattformverwaltung) –' : '– bitte wählen –' }}</option>
                    @endif
                    @foreach ($vereine as $verein)
                        <option value="{{ $verein->id }}" @selected($aktiverVerein?->id === $verein->id)>{{ $verein->name }} ({{ $verein->kuerzel }})</option>
                    @endforeach
                </select>
                <button type="submit" class="knopf">Wechseln</button>
            </form>
        @endif
    </section>

    @if ($aktiverVerein)
        <section class="karte">
            <h2>{{ $aktiverVerein->name }}</h2>
            @can('verein.verwalten')
                <p><a class="knopf" href="{{ route('verein.uebersicht') }}">Verein verwalten</a></p>
            @endcan
            <p class="hinweis">Die Funktionen für Veranstaltungen, Tickets und Saal folgen in den nächsten Ausbaustufen.</p>
        </section>
    @endif

    @if ($benutzer->istSuperAdmin())
        <section class="karte">
            <h2>Plattformverwaltung</h2>
            <p><a class="knopf" href="{{ route('verwaltung.uebersicht') }}">Zur Verwaltung</a></p>
        </section>
    @endif
@endsection
