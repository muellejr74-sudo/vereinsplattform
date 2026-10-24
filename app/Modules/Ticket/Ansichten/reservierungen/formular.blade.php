@extends('layouts.basis')

@section('titel', 'Reservierung anlegen')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>Reservierung anlegen</h1>
        @include('kern::teile.fehler')
        @if ($vorstellungen->isEmpty())
            <p>Es gibt keine kommenden Vorstellungen.</p>
        @else
            @php
                $erste = $vorstellungen->firstWhere('id', (int) old('vorstellung_id', $vorauswahl)) ?? $vorstellungen->first();
                $art = old('art', $einstellungen->reservierung_standard);
            @endphp
            <form method="post" action="{{ route('tickets.reservierungen.store') }}" class="formular" id="reservierung">
                @csrf
                <label for="verkaufsstelle_id">Angenommen von</label>
                <select id="verkaufsstelle_id" name="verkaufsstelle_id">
                    @if ($verein)<option value="">Verein</option>@endif
                    @foreach ($stellen as $s)<option value="{{ $s->id }}" @selected((int) old('verkaufsstelle_id') === $s->id)>{{ $s->name }}</option>@endforeach
                </select>

                <label for="vorstellung_id">Vorstellung</label>
                <select id="vorstellung_id" name="vorstellung_id" required onchange="var v = this.selectedOptions[0].dataset.veranstaltung; this.form.querySelectorAll('[data-veranstaltung]').forEach(function (e) { e.hidden = e.dataset.veranstaltung !== v; e.querySelectorAll('input').forEach(function (i) { i.disabled = e.hidden; }); });">
                    @foreach ($vorstellungen as $v)
                        <option value="{{ $v->id }}" data-veranstaltung="{{ $v->veranstaltung_id }}" @selected($erste->id === $v->id)>{{ $v->veranstaltung->name }} – {{ $v->anzeige() }}</option>
                    @endforeach
                </select>

                @foreach ($vorstellungen->pluck('veranstaltung')->unique('id') as $veranstaltung)
                    @php $liste = $kategorien->get($veranstaltung->id, collect()); $sichtbar = $veranstaltung->id === $erste->veranstaltung_id; @endphp
                    @if ($liste->isEmpty())
                        <div class="meldung meldung--fehler" data-veranstaltung="{{ $veranstaltung->id }}" @unless ($sichtbar) hidden @endunless>Für „{{ $veranstaltung->name }}“ sind noch keine Kartenkategorien angelegt.</div>
                    @else
                        <fieldset class="rechte-gruppe" data-veranstaltung="{{ $veranstaltung->id }}" @unless ($sichtbar) hidden @endunless>
                            <legend>Anzahl Karten je Kategorie</legend>
                            @foreach ($liste as $k)
                                <div class="formular--zeile" style="margin:0.25rem 0">
                                    <label for="m{{ $k->id }}" style="flex:1">{{ $k->name }} <span class="hinweis">{{ \App\Modules\Ticket\Dienste\Geld::anzeigen($k->preis_cent) }}</span></label>
                                    <input id="m{{ $k->id }}" name="mengen[{{ $k->id }}]" type="number" min="0" max="500" value="{{ old('mengen.'.$k->id, 0) }}" style="flex:0 0 6rem" @unless ($sichtbar) disabled @endunless>
                                </div>
                            @endforeach
                        </fieldset>
                    @endif
                @endforeach

                <label for="name">Name (steht auf den Karten)</label>
                <input id="name" name="name" maxlength="80" required value="{{ old('name') }}" placeholder="z. B. Familie Meier oder Liederkranz">
                <label for="telefon">Telefon (freiwillig)</label>
                <input id="telefon" name="telefon" type="tel" maxlength="40" value="{{ old('telefon') }}">

                <fieldset>
                    <legend>Wie bekommt der Gast die Karten?</legend>
                    @foreach (\App\Modules\Ticket\Models\Reservierung::ARTEN as $schluessel => $text)
                        <label class="zusatz"><input type="radio" name="art" value="{{ $schluessel }}" @checked($art === $schluessel) onchange="document.getElementById('vorkasse').hidden = this.value !== 'vorkasse'"> {{ ucfirst($text) }}</label>
                    @endforeach
                </fieldset>

                <div id="vorkasse" @if ($art !== 'vorkasse') hidden @endif>
                    <label for="strasse">Straße und Hausnummer</label>
                    <input id="strasse" name="strasse" maxlength="120" value="{{ old('strasse') }}" autocomplete="street-address">
                    <div class="formular--zeile">
                        <div style="flex:0 0 7rem"><label for="plz">PLZ</label><input id="plz" name="plz" maxlength="10" value="{{ old('plz') }}" inputmode="numeric"></div>
                        <div style="flex:1"><label for="ort">Ort</label><input id="ort" name="ort" maxlength="80" value="{{ old('ort') }}"></div>
                    </div>
                    <label for="zahlungsfrist">Zahlung bis</label>
                    <input id="zahlungsfrist" name="zahlungsfrist" type="date" value="{{ old('zahlungsfrist', $frist) }}" class="eingabe--kurz">
                    <small class="hinweis">Die Karten werden erst nach Geldeingang gedruckt und verschickt. Betrag und Bankverbindung stehen nach dem Speichern zum Durchsagen bereit.</small>
                </div>

                <label for="bemerkung">Bemerkung (optional)</label>
                <input id="bemerkung" name="bemerkung" maxlength="255" value="{{ old('bemerkung') }}">

                <button type="submit" class="knopf">Reservieren</button>
            </form>
            <p class="hinweis">Die Plätze sind sofort fest reserviert und zählen zum Kontingent.</p>
        @endif
    </section>
@endsection
