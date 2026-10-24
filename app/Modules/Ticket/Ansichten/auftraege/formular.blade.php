@extends('layouts.basis')

@section('titel', $art === 'freikarten' ? 'Freikarten anfordern' : 'Karten bestellen')

@section('inhalt')
    @include('ticket::teile.mengen')
    <section class="karte karte--schmal">
        <h1>{{ $art === 'freikarten' ? 'Freikarten anfordern' : 'Karten bestellen' }}</h1>
        @include('kern::teile.fehler')
        @if ($vorstellungen->isEmpty())
            <p>Es gibt keine kommenden Vorstellungen.</p>
        @else
            <form method="post" action="{{ route('tickets.auftraege.store') }}" class="formular" data-frei-aus="#vorstellung_id">
                @csrf
                <input type="hidden" name="art" value="{{ $art }}">

                <label for="verkaufsstelle_id">Für</label>
                <select id="verkaufsstelle_id" name="verkaufsstelle_id">
                    @foreach ($stellen as $s)<option value="{{ $s->id }}" @selected((int) old('verkaufsstelle_id') === $s->id)>{{ $s->name }}</option>@endforeach
                    @if ($intern)<option value="" @selected(old('verkaufsstelle_id') === '')>Verein (intern, z. B. Sponsoren)</option>@endif
                </select>

                <label for="vorstellung_id">Vorstellung</label>
                <select id="vorstellung_id" name="vorstellung_id" required onchange="var v = this.selectedOptions[0].dataset.veranstaltung, f = this.form; f.querySelectorAll('[data-veranstaltung]').forEach(function (e) { e.hidden = e.dataset.veranstaltung !== v; }); var k = f.querySelector('button[type=submit]'); if (k && f.art.value === 'karten') k.disabled = !!f.querySelector('[data-ohne-kategorien]:not([hidden])'); this.form.dispatchEvent(new Event('input'));">
                    @foreach ($vorstellungen as $v)
                        <option value="{{ $v->id }}" data-veranstaltung="{{ $v->veranstaltung_id }}" @if (($frei[$v->id] ?? null) !== null) data-frei="{{ $frei[$v->id] }}" @endif @selected((int) old('vorstellung_id', $vorauswahl) === $v->id)>{{ $v->veranstaltung->name }} – {{ $v->anzeige() }}</option>
                    @endforeach
                </select>

                @if ($art === 'freikarten')
                    <label for="name">Name auf den Karten</label>
                    <input id="name" name="name" maxlength="80" required value="{{ old('name') }}" placeholder="z. B. Sparkasse oder Familie Meier">
                    <label for="anzahl">Anzahl Freikarten</label>
                    <input data-menge id="anzahl" name="anzahl" type="number" min="1" max="2000" required value="{{ old('anzahl', 1) }}">
                    <small class="hinweis">Alle Karten bekommen diesen Namen und werden als Stapel nummeriert (z. B. „3/10“). Freikarten zählen zum Kontingent.</small>
                @else
                    @php
                        $erste = $vorstellungen->firstWhere('id', (int) old('vorstellung_id', $vorauswahl)) ?? $vorstellungen->first();
                        $veranstaltungen = $vorstellungen->pluck('veranstaltung')->unique('id');
                    @endphp
                    @foreach ($veranstaltungen as $veranstaltung)
                        @php $liste = $kategorien->get($veranstaltung->id, collect()); @endphp
                        @if ($liste->isEmpty())
                            <div class="meldung meldung--fehler" data-veranstaltung="{{ $veranstaltung->id }}" data-ohne-kategorien @if ($veranstaltung->id !== $erste->veranstaltung_id) hidden @endif>
                                Für „{{ $veranstaltung->name }}“ sind noch keine Kartenkategorien mit Preisen angelegt – deshalb kann hier noch keine Anzahl eingegeben werden.
                                @can('ticket.kategorien')
                                    <br><a href="{{ route('tickets.veranstaltung', $veranstaltung) }}">Jetzt Kategorien und Preise anlegen</a>
                                @else
                                    Bitte beim Verein melden.
                                @endcan
                            </div>
                        @else
                            <fieldset class="rechte-gruppe" data-veranstaltung="{{ $veranstaltung->id }}" @if ($veranstaltung->id !== $erste->veranstaltung_id) hidden @endif>
                                <legend>Anzahl Karten je Kategorie</legend>
                                @foreach ($liste as $k)
                                    <div class="mengen-zeile">
                                        <label for="m{{ $k->id }}" >{{ $k->name }} <span class="hinweis">{{ \App\Modules\Ticket\Dienste\Geld::anzeigen($k->preis_cent) }}</span></label>
                                        <input data-menge id="m{{ $k->id }}" name="mengen[{{ $k->id }}]" type="number" min="0" max="2000" value="{{ old('mengen.'.$k->id, 0) }}">
                                    </div>
                                @endforeach
                            </fieldset>
                        @endif
                    @endforeach
                @endif

                <label for="bemerkung">Bemerkung (optional)</label>
                <input id="bemerkung" name="bemerkung" maxlength="255" value="{{ old('bemerkung') }}">

                <button type="submit" class="knopf" @if ($art !== 'freikarten' && ! $kategorien->has($erste->veranstaltung_id ?? 0)) disabled @endif>Anfordern</button>
            </form>
            <p class="hinweis">Die Druckstelle bestätigt den Auftrag. Erst dann werden die Plätze belegt und die Karten gedruckt.</p>
        @endif
    </section>
@endsection
