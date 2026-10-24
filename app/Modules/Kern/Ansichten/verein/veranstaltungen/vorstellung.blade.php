@extends('layouts.basis')

@section('titel', $vorstellung->exists ? 'Vorstellung bearbeiten' : 'Vorstellung anlegen')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>{{ $vorstellung->exists ? 'Vorstellung bearbeiten' : 'Vorstellung anlegen' }}</h1>
        <p class="hinweis">{{ $veranstaltung->name }}</p>
        @include('kern::teile.fehler')
        <form method="post" action="{{ $vorstellung->exists ? route('verein.vorstellungen.update', [$veranstaltung, $vorstellung]) : route('verein.vorstellungen.store', $veranstaltung) }}" class="formular">
            @csrf @if ($vorstellung->exists) @method('put') @endif
            <label for="datum">Datum</label>
            <input id="datum" name="datum" type="date" value="{{ old('datum', $vorstellung->beginn_um?->format('Y-m-d')) }}" required>

            <div class="formular--zeile" style="margin:0">
                <div class="feld"><label for="einlass">Einlass</label>
                    <input id="einlass" name="einlass" type="time" value="{{ old('einlass', $vorstellung->einlass_um?->format('H:i')) }}"></div>
                <div class="feld"><label for="beginn">Beginn</label>
                    <input id="beginn" name="beginn" type="time" value="{{ old('beginn', $vorstellung->beginn_um?->format('H:i')) }}" required></div>
                <div class="feld"><label for="ende">Ende (optional)</label>
                    <input id="ende" name="ende" type="time" value="{{ old('ende', $vorstellung->ende_um?->format('H:i')) }}"></div>
            </div>
            <small class="hinweis">Personal kann sich ab 3 Stunden vor Einlass anmelden – bis 3 Stunden nach Ende, ohne Ende bis 4:00 Uhr am Folgetag.</small>

            @if ($mehrereSaele)
                <label for="saal_id">Saal</label>
                <select id="saal_id" name="saal_id" required>
                    @foreach ($saele as $saal)
                        <option value="{{ $saal->id }}" @selected((int) old('saal_id', $vorstellung->saal_id) === $saal->id)>{{ $saal->anzeigename() }}</option>
                    @endforeach
                </select>
            @endif

            <label for="kontingent">Kontingent (Plätze, optional)</label>
            <input id="kontingent" name="kontingent" type="number" min="0" max="100000" value="{{ old('kontingent', $vorstellung->kontingent) }}" class="eingabe--kurz">
            <small class="hinweis">Wird ab der Ticketverwaltung (Stufe 3) genutzt.</small>

            <div class="knopfzeile">
                <button type="submit" class="knopf">Speichern</button>
                <a href="{{ route('verein.veranstaltungen.show', $veranstaltung) }}">Abbrechen</a>
            </div>
        </form>
    </section>
@endsection
