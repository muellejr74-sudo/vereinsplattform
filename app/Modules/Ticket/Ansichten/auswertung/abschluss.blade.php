@extends('layouts.basis')

@section('titel', 'Abschluss')

@php $g = fn ($c) => \App\Modules\Ticket\Dienste\Geld::anzeigen($c); $offen = collect($reste)->sum(fn ($l) => $l->count()); @endphp

@section('inhalt')
    @include('ticket::auswertung.kopf', ['titel' => 'Abschluss'])

    @if ($abschluss)
        <section class="karte">
            <div class="meldung meldung--erfolg">Abgeschlossen am {{ $abschluss->abgeschlossen_am->format('d.m.Y H:i') }} von {{ $abschluss->durch?->name ?? '–' }}. Die Vorstellung ist gesperrt.</div>
            <a class="knopf" href="{{ route('tickets.auswertung.bericht', $v) }}" target="_blank">Abschlussbericht (PDF)</a>
        </section>
        @if (auth()->user()->istSuperAdmin())
            <section class="karte">
                <h2>Abschluss rückgängig machen</h2>
                <p class="hinweis">Nur für den Super-Admin. Der Grund wird im Protokoll gespeichert. Karten, die durch den Abschluss als verkauft galten, sind danach wieder „ausgegeben“.</p>
                <form method="post" action="{{ route('tickets.abschluss.rueckgaengig', $v) }}" class="formular" onsubmit="return confirm('Abschluss wirklich rückgängig machen?')">
                    @csrf
                    <label for="grund">Grund (Pflicht)</label>
                    <input id="grund" name="grund" required minlength="5" maxlength="255" value="{{ old('grund') }}">
                    <button type="submit" class="knopf knopf--zweit">Rückgängig machen</button>
                </form>
            </section>
        @endif
    @else
        <section class="karte">
            <h2>1. Offene Reste prüfen</h2>
            @if ($offen === 0)
                <div class="meldung meldung--erfolg">Keine offenen Reste.</div>
            @endif

            @if ($reste['reservierungen']->isNotEmpty())
                <h3>Nicht abgeholte / unbezahlte Reservierungen ({{ $reste['reservierungen']->count() }})</h3>
                <ul>@foreach ($reste['reservierungen'] as $r)<li>{{ $r->name }} – {{ $r->anzahl() }} Karten ({{ $r->kennung() }}, {{ $r->statusText() }})</li>@endforeach</ul>
                <form method="post" action="{{ route('tickets.abschluss.bereinigen', $v) }}" onsubmit="return confirm('Alle diese Reservierungen stornieren?')">@csrf<input type="hidden" name="art" value="reservierungen"><button type="submit" class="knopf knopf--zweit">Alle stornieren</button></form>
            @endif

            @if ($reste['auftraege']->isNotEmpty())
                <h3>Nicht bestätigte Kartenaufträge ({{ $reste['auftraege']->count() }})</h3>
                <ul>@foreach ($reste['auftraege'] as $a)<li>#{{ $a->id }} {{ $a->stelleText() }} – {{ $a->anzahlAngefordert() }} Karten</li>@endforeach</ul>
                <form method="post" action="{{ route('tickets.abschluss.bereinigen', $v) }}" onsubmit="return confirm('Alle diese Aufträge ablehnen?')">@csrf<input type="hidden" name="art" value="auftraege"><button type="submit" class="knopf knopf--zweit">Alle ablehnen</button></form>
            @endif

            @if ($reste['karten']->isNotEmpty())
                <h3>Nicht ausgegebene Karten ({{ $reste['karten']->count() }})</h3>
                <p class="hinweis">Gedruckt oder erzeugt, aber nie an Gäste oder Verkaufsstellen gegangen – auch nicht verkaufter Bestand der Abendkasse.</p>
                <p>{{ $reste['karten']->take(30)->pluck('nummer')->join(', ') }}{{ $reste['karten']->count() > 30 ? ' …' : '' }}</p>
                <form method="post" action="{{ route('tickets.abschluss.bereinigen', $v) }}" onsubmit="return confirm('Alle diese Karten stornieren?')">@csrf<input type="hidden" name="art" value="karten"><button type="submit" class="knopf knopf--zweit">Alle stornieren</button></form>
            @endif

            @if ($reste['versand']->isNotEmpty())
                <h3>Bezahlte Vorkasse, noch nicht versendet ({{ $reste['versand']->count() }})</h3>
                <p class="hinweis">Diese Karten sind bezahlt – bitte bei der Druckstelle drucken und „Versendet“ wählen.</p>
                <ul>@foreach ($reste['versand'] as $r)<li><a href="{{ route('tickets.reservierungen.show', $r) }}">{{ $r->name }} ({{ $r->kennung() }})</a></li>@endforeach</ul>
            @endif
        </section>

        <section class="karte karte--wichtig">
            <h2>2. Abschluss durchführen</h2>
            <p>Einnahmen: <strong>{{ $g($z['einnahmen']) }}</strong> · davon bar {{ $g($z['zahlungsarten']['bar']['betrag']) }} · Karte {{ $g($z['zahlungsarten']['karte']['betrag']) }} · Überweisung {{ $g($z['zahlungsarten']['ueberweisung']['betrag']) }} · Vorverkaufsstellen {{ $g($z['zahlungsarten']['stellen']['betrag']) }}</p>
            <p class="hinweis">Danach gelten ausgegebene Vorverkaufskarten als verkauft. Stornieren, Nachbuchen und Verkauf sind gesperrt. Rückgängig nur durch den Super-Admin.</p>
            <form method="post" action="{{ route('tickets.abschluss', $v) }}" onsubmit="return confirm('Abschluss jetzt durchführen? Danach ist die Vorstellung gesperrt.')">
                @csrf <button type="submit" class="knopf knopf--gross" @disabled($offen > 0)>Abschluss durchführen</button>
            </form>
            @if ($offen > 0)<p class="hinweis">Erst möglich, wenn keine offenen Reste mehr da sind.</p>@endif
        </section>
    @endif
@endsection
