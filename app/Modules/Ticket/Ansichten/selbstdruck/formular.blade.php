@extends('layouts.basis')

@section('titel', 'Karten verkaufen und drucken')

@section('inhalt')
    @include('ticket::teile.mengen')
    <section class="karte karte--schmal">
        <h1>Karten verkaufen</h1>
        @include('kern::teile.fehler')
        @if ($vorstellungen->isEmpty())
            <p>Es gibt keine kommenden Vorstellungen.</p>
        @else
            @php $erste = $vorstellungen->firstWhere('id', (int) old('vorstellung_id', $vorauswahl)) ?? $vorstellungen->first(); @endphp
            <form method="post" action="{{ route('tickets.selbstdruck.store') }}" class="formular" data-frei-aus="#vorstellung_id" target="_blank"
                  data-letzte="{{ $letzte }}"
                  oninput="var o = this.vorstellung_id.selectedOptions[0], p = JSON.parse(o.dataset.preise || '{}'), s = 0; this.querySelectorAll('input[data-menge]').forEach(function (e) { if (!e.disabled) { s += (parseInt(e.value, 10) || 0) * (p[e.dataset.kategorie] || 0); } }); document.getElementById('summe').textContent = (s / 100).toFixed(2).replace('.', ',') + ' €';"
                  onsubmit="var v = this.vorstellung_id.value, n = this.dataset.letzte; setTimeout(function () { location.href = '{{ route('tickets.selbstdruck.kassieren') }}?nach=' + n + '&vorstellung=' + v; }, 900);">
                @csrf
                @if ($stellen->count() > 1)
                    <label for="verkaufsstelle_id">Verkaufsstelle</label>
                    <select id="verkaufsstelle_id" name="verkaufsstelle_id">
                        @foreach ($stellen as $s)<option value="{{ $s->id }}" @selected((int) old('verkaufsstelle_id') === $s->id)>{{ $s->name }}</option>@endforeach
                    </select>
                @else
                    <input type="hidden" name="verkaufsstelle_id" value="{{ $stellen->first()->id }}">
                    <p class="hinweis">{{ $stellen->first()->name }}</p>
                @endif

                <label for="vorstellung_id">Vorstellung</label>
                <select id="vorstellung_id" name="vorstellung_id" required onchange="var v = this.selectedOptions[0].dataset.veranstaltung; this.form.querySelectorAll('[data-veranstaltung]').forEach(function (e) { e.hidden = e.dataset.veranstaltung !== v; e.querySelectorAll('input').forEach(function (i) { i.disabled = e.hidden; }); }); this.form.dispatchEvent(new Event('input'));">
                    @foreach ($vorstellungen as $v)
                        <option value="{{ $v->id }}" data-veranstaltung="{{ $v->veranstaltung_id }}" data-preise='@json((object) ($preise[$v->id] ?? []))' @if (($frei[$v->id] ?? null) !== null) data-frei="{{ $frei[$v->id] }}" @endif @selected($erste->id === $v->id)>{{ $v->veranstaltung->name }} – {{ $v->anzeige() }} (frei: {{ $frei[$v->id] ?? '–' }})</option>
                    @endforeach
                </select>

                @foreach ($vorstellungen->pluck('veranstaltung')->unique('id') as $veranstaltung)
                    @php $liste = $kategorien->get($veranstaltung->id, collect()); $sichtbar = $veranstaltung->id === $erste->veranstaltung_id; @endphp
                    @if ($liste->isEmpty())
                        <div class="meldung meldung--fehler" data-veranstaltung="{{ $veranstaltung->id }}" @unless ($sichtbar) hidden @endunless>Für „{{ $veranstaltung->name }}“ sind noch keine Kartenkategorien angelegt.</div>
                    @else
                        <fieldset class="rechte-gruppe" data-veranstaltung="{{ $veranstaltung->id }}" @unless ($sichtbar) hidden @endunless>
                            <legend>Anzahl Karten</legend>
                            @foreach ($liste as $k)
                                <div class="mengen-zeile">
                                    <label for="m{{ $k->id }}">{{ $k->name }} <span class="hinweis">{{ \App\Modules\Ticket\Dienste\Geld::anzeigen($k->preis_cent) }}</span></label>
                                    <input data-menge data-kategorie="{{ $k->id }}" id="m{{ $k->id }}" name="mengen[{{ $k->id }}]" type="number" min="0" max="500" value="{{ old('mengen.'.$k->id, 0) }}" @unless ($sichtbar) disabled @endunless>
                                </div>
                            @endforeach
                        </fieldset>
                    @endif
                @endforeach

                <p>Zu zahlen: <span class="summe" id="summe">0,00 €</span></p>

                <label for="name">Name auf den Karten (optional)</label>
                <input id="name" name="name" maxlength="80" value="{{ old('name') }}" placeholder="z. B. Familie Meier">

                <button type="submit" class="knopf knopf--gross">Karten erzeugen und drucken</button>
            </form>
            <p class="hinweis">Die Karten entstehen sofort mit Nummer und belegen das Kontingent. Sie öffnen sich in einem neuen Tab zum Drucken; hier geht es weiter mit dem Kassieren (Rückgeld).</p>
        @endif
    </section>
@endsection
