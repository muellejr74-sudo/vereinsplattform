@extends('layouts.basis')

@section('titel', 'Tisch zuordnen')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>Tisch zuordnen</h1>
        @include('kern::teile.fehler')

        @if ($code)
            <p><strong>{{ $code->bezeichnung }}</strong> <span class="hinweis">{{ $code->istBetreiberTisch() ? '(Tisch des Plattformbetreibers)' : '(eigener Code)' }}</span></p>
            @if ($bisher)
                <div class="meldung meldung--hinweis">Dieser Code ist zurzeit einer Veranstaltung zugeordnet. Beim Speichern wird er dort gelöst und hier übernommen.</div>
            @endif
        @endif

        <form method="post" action="{{ route('verein.tische.zuordnen') }}" class="formular">
            @csrf
            @if ($code)
                <input type="hidden" name="code" value="{{ $code->code }}">
            @else
                <label for="code">QR-Code</label>
                <select id="code" name="code" required>
                    @forelse ($freieCodes as $frei)
                        <option value="{{ $frei->code }}" @selected(old('code') === $frei->code)>{{ $frei->bezeichnung }}{{ $frei->einheitscode ? ' (Einheitscode)' : '' }}</option>
                    @empty
                        <option value="">– keine freien eigenen Codes –</option>
                    @endforelse
                </select>
            @endif

            <label for="veranstaltung_id">Veranstaltung</label>
            <select id="veranstaltung_id" name="veranstaltung_id" required
                    onchange="var v = JSON.parse(this.dataset.vorschlaege); document.getElementById('gueltig_bis').value = v[this.value] || ''"
                    data-vorschlaege="{{ json_encode($vorschlaege) }}">
                @foreach ($veranstaltungen as $v)
                    <option value="{{ $v->id }}" @selected((int) old('veranstaltung_id', $vorauswahl->id) === $v->id)>{{ $v->name }}</option>
                @endforeach
            </select>

            @if (! $code || ! $code->einheitscode)
                <label for="tischnummer">Tischnummer</label>
                <input id="tischnummer" name="tischnummer" type="text" inputmode="numeric" maxlength="10" value="{{ old('tischnummer', $bisherigeNummer) }}" class="eingabe--code" @if ($code) autofocus required @endif>
            @endif

            <label for="gueltig_bis">Gültig bis einschließlich</label>
            <input id="gueltig_bis" name="gueltig_bis" type="date" min="{{ today()->toDateString() }}" value="{{ old('gueltig_bis', $vorschlaege[$vorauswahl->id] ?? '') }}" required>
            <small class="hinweis">Vorschlag: Tag nach der letzten Vorstellung. Danach wird der Tisch automatisch frei.</small>

            <button type="submit" class="knopf">Speichern</button>
        </form>
        @if ($code)
            <p class="hinweis"><a href="{{ route('scan', ['code' => $code->code, 'gast' => 1]) }}">Gästeansicht dieses Codes anzeigen</a></p>
        @endif
    </section>
@endsection
