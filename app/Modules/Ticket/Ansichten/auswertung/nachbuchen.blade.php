@extends('layouts.basis')

@section('titel', 'Nachbuchen')

@section('inhalt')
    @include('ticket::teile.mengen')
    @include('ticket::auswertung.kopf', ['titel' => 'Nachbuchen'])

    @if ($abgeschlossen)
        <div class="meldung meldung--fehler">{{ \App\Modules\Ticket\Dienste\Abschlusssperre::TEXT }}</div>
    @elseif ($v->beginn_um->isFuture())
        <div class="meldung meldung--hinweis">Nachbuchen ist ab Beginn der Vorstellung möglich ({{ $v->beginn_um->format('d.m.Y H:i') }} Uhr). Vorher bitte Abendkasse oder Reservierung nutzen.</div>
    @else
        <section class="karte karte--schmal">
            <h2>Verkäufe nachtragen</h2>
            <p class="hinweis">z. B. Karten, die bei Internetausfall nach der Notfallliste verkauft wurden. Ohne Etikett, gelten als eingelassen.</p>
            <form method="post" action="{{ route('tickets.nachbuchen.verkauf', $v) }}" class="formular" @if ($frei !== null) data-frei="{{ $frei }}" @endif>
                @csrf
                @foreach ($kategorien as $k)
                    <div class="mengen-zeile">
                        <label for="n{{ $k->id }}">{{ $k->name }} <span class="hinweis">{{ \App\Modules\Ticket\Dienste\Geld::anzeigen($k->preisFuer($v->id)) }}</span></label>
                        <input data-menge id="n{{ $k->id }}" name="mengen[{{ $k->id }}]" type="number" min="0" max="500" value="{{ old('mengen.'.$k->id, 0) }}">
                    </div>
                @endforeach
                <div class="knopfzeile knopfzeile--voll">
                    <button type="submit" name="zahlungsart" value="bar" class="knopf">bar</button>
                    <button type="submit" name="zahlungsart" value="karte" class="knopf knopf--zweit">Karte</button>
                    <button type="submit" name="zahlungsart" value="ueberweisung" class="knopf knopf--zweit">Überweisung</button>
                </div>
            </form>
        </section>

        <section class="karte karte--schmal">
            <h2>Freikarten nachtragen</h2>
            <form method="post" action="{{ route('tickets.nachbuchen.freikarten', $v) }}" class="formular" @if ($frei !== null) data-frei="{{ $frei }}" @endif>
                @csrf
                <label for="fname">Name</label>
                <input id="fname" name="name" maxlength="80" required value="{{ old('name') }}" placeholder="z. B. Sponsor">
                <div class="mengen-zeile">
                    <label for="fanzahl">Anzahl</label>
                    <input data-menge id="fanzahl" name="anzahl" type="number" min="1" max="500" value="{{ old('anzahl', 1) }}">
                </div>
                <button type="submit" class="knopf">Freikarten nachbuchen</button>
            </form>
        </section>
        <p class="hinweis">Frei: {{ $frei ?? '–' }} · jeder Nachtrag wird protokolliert.</p>
    @endif
@endsection
