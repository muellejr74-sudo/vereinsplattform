@extends('layouts.basis')

@section('titel', $art === 'freikarten' ? 'Freikarten anfordern' : 'Karten bestellen')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>{{ $art === 'freikarten' ? 'Freikarten anfordern' : 'Karten bestellen' }}</h1>
        @include('kern::teile.fehler')
        @if ($vorstellungen->isEmpty())
            <p>Es gibt keine kommenden Vorstellungen.</p>
        @else
            <form method="post" action="{{ route('tickets.auftraege.store') }}" class="formular">
                @csrf
                <input type="hidden" name="art" value="{{ $art }}">

                <label for="verkaufsstelle_id">Für</label>
                <select id="verkaufsstelle_id" name="verkaufsstelle_id">
                    @foreach ($stellen as $s)<option value="{{ $s->id }}" @selected((int) old('verkaufsstelle_id') === $s->id)>{{ $s->name }}</option>@endforeach
                    @if ($intern)<option value="" @selected(old('verkaufsstelle_id') === '')>Verein (intern, z. B. Sponsoren)</option>@endif
                </select>

                <label for="vorstellung_id">Vorstellung</label>
                <select id="vorstellung_id" name="vorstellung_id" required onchange="document.querySelectorAll('[data-veranstaltung]').forEach(function (e) { e.hidden = e.dataset.veranstaltung !== this.selectedOptions[0].dataset.veranstaltung; }, this)">
                    @foreach ($vorstellungen as $v)
                        <option value="{{ $v->id }}" data-veranstaltung="{{ $v->veranstaltung_id }}" @selected((int) old('vorstellung_id', $vorauswahl) === $v->id)>{{ $v->veranstaltung->name }} – {{ $v->anzeige() }}</option>
                    @endforeach
                </select>

                @if ($art === 'freikarten')
                    <label for="name">Name auf den Karten</label>
                    <input id="name" name="name" maxlength="80" required value="{{ old('name') }}" placeholder="z. B. Sparkasse oder Familie Meier">
                    <label for="anzahl">Anzahl Freikarten</label>
                    <input id="anzahl" name="anzahl" type="number" min="1" max="2000" required value="{{ old('anzahl', 1) }}" class="eingabe--kurz">
                    <small class="hinweis">Alle Karten bekommen diesen Namen und werden als Stapel nummeriert (z. B. „3/10“). Freikarten zählen zum Kontingent.</small>
                @else
                    @php $erste = $vorstellungen->firstWhere('id', (int) old('vorstellung_id', $vorauswahl)) ?? $vorstellungen->first(); @endphp
                    @foreach ($kategorien as $veranstaltungId => $liste)
                        <fieldset class="rechte-gruppe" data-veranstaltung="{{ $veranstaltungId }}" @if ($veranstaltungId !== $erste->veranstaltung_id) hidden @endif>
                            <legend>Anzahl je Kategorie</legend>
                            @foreach ($liste as $k)
                                <div class="formular--zeile" style="margin:0.25rem 0">
                                    <label for="m{{ $k->id }}" style="flex:1">{{ $k->name }} <span class="hinweis">{{ \App\Modules\Ticket\Dienste\Geld::anzeigen($k->preis_cent) }}</span></label>
                                    <input id="m{{ $k->id }}" name="mengen[{{ $k->id }}]" type="number" min="0" max="2000" value="{{ old('mengen.'.$k->id, 0) }}" style="flex:0 0 6rem">
                                </div>
                            @endforeach
                        </fieldset>
                    @endforeach
                    @if ($kategorien->isEmpty())
                        <p class="hinweis">Für diese Veranstaltungen sind noch keine Kategorien angelegt.</p>
                    @endif
                @endif

                <label for="bemerkung">Bemerkung (optional)</label>
                <input id="bemerkung" name="bemerkung" maxlength="255" value="{{ old('bemerkung') }}">

                <button type="submit" class="knopf">Anfordern</button>
            </form>
            <p class="hinweis">Die Druckstelle bestätigt den Auftrag. Erst dann werden die Plätze belegt und die Karten gedruckt.</p>
        @endif
    </section>
@endsection
