@extends('layouts.basis')

@section('titel', 'Auswertung')

@php $g = fn ($c) => \App\Modules\Ticket\Dienste\Geld::anzeigen($c); $k = $z['kontingent']; @endphp

@section('inhalt')
    @include('ticket::auswertung.kopf', ['titel' => 'Auswertung'])

    <section class="karte">
        @if ($z['abschluss'])
            <div class="meldung meldung--erfolg">Abgeschlossen am {{ $z['abschluss']->abgeschlossen_am->format('d.m.Y H:i') }} von {{ $z['abschluss']->durch?->name ?? '–' }}</div>
        @else
            <div class="meldung meldung--hinweis">Noch nicht abgeschlossen – vorläufige Zahlen.</div>
        @endif
        <div class="kacheln">
            <div class="kachel"><strong>{{ $k['belegt'] }} / {{ $k['kontingent'] ?? '–' }}</strong><span>belegt / Kontingent</span></div>
            <div class="kachel"><strong>{{ $z['eingelassen'] }}</strong><span>eingelassen</span></div>
            <div class="kachel"><strong>{{ $g($z['einnahmen']) }}</strong><span>Einnahmen gesamt</span></div>
            <div class="kachel"><strong>{{ $z['freikarten'] }}</strong><span>Freikarten</span></div>
        </div>
        <div class="knopfzeile" style="margin-top:0.75rem">
            <a class="knopf" href="{{ route('tickets.auswertung.bericht', $v) }}" target="_blank">Abschlussbericht (PDF)</a>
            <a class="knopf knopf--zweit" href="{{ route('tickets.auswertung.csv', [$v, 'karten']) }}">Alle Karten (CSV)</a>
            <a class="knopf knopf--zweit" href="{{ route('tickets.auswertung.csv', [$v, 'abrechnung']) }}">Abrechnung (CSV)</a>
            <a class="knopf knopf--zweit" href="{{ route('tickets.auswertung.csv', [$v, 'freikarten']) }}">Freikarten (CSV)</a>
        </div>
    </section>

    <section class="karte">
        <h2>Einnahmen nach Zahlungsart</h2>
        <table class="tabelle tabelle--klein">
            <thead><tr><th>Zahlungsart</th><th>Karten</th><th>Betrag</th></tr></thead>
            <tbody>
            @foreach (\App\Modules\Ticket\Dienste\Auswertung::ZAHLUNGSARTEN as $art => $text)
                <tr><td data-titel="Zahlungsart">{{ $text }}</td><td data-titel="Karten">{{ $z['zahlungsarten'][$art]['anzahl'] }}</td><td data-titel="Betrag"><strong>{{ $g($z['zahlungsarten'][$art]['betrag']) }}</strong></td></tr>
            @endforeach
            <tr><td><strong>Gesamt</strong></td><td>{{ collect($z['zahlungsarten'])->sum('anzahl') }}</td><td><strong>{{ $g($z['einnahmen']) }}</strong></td></tr>
            </tbody>
        </table>
        <p class="hinweis">„bar“ muss in der Kasse sein. „Abrechnung Vorverkaufsstellen“ zahlen die Stellen an den Verein.</p>
    </section>

    <section class="karte">
        <h2>Einnahmen nach Kategorie</h2>
        <table class="tabelle tabelle--klein">
            <thead><tr><th>Kategorie</th><th>Preis</th><th>Karten</th><th>Betrag</th></tr></thead>
            <tbody>
            @forelse ($z['kategorien'] as $r)
                <tr><td data-titel="Kategorie">{{ $r['name'] }}</td><td data-titel="Preis">{{ $g($r['preis']) }}</td><td data-titel="Karten">{{ $r['anzahl'] }}</td><td data-titel="Betrag">{{ $g($r['betrag']) }}</td></tr>
            @empty
                <tr><td colspan="4">Noch keine verkauften Karten.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="karte">
        <h2>Verkauf nach Weg</h2>
        <table class="tabelle tabelle--klein">
            <tbody>
            @foreach (\App\Modules\Ticket\Dienste\Auswertung::WEGE as $weg => $text)
                <tr><td data-titel="Weg">{{ $text }}</td><td data-titel="Karten">{{ $z['wege'][$weg] }}</td></tr>
            @endforeach
            <tr><td>Storniert</td><td>{{ $z['storniert'] }}</td></tr>
            <tr><td>Reservierungen offen / abgeholt / storniert</td><td>{{ $z['reservierungen']['offen'] }} / {{ $z['reservierungen']['abgeholt'] }} / {{ $z['reservierungen']['storniert'] }}</td></tr>
            </tbody>
        </table>
    </section>

    <section class="karte">
        <h2>Abrechnung je Vorverkaufsstelle</h2>
        <table class="tabelle tabelle--klein">
            <thead><tr><th>Stelle</th><th>Ausgegeben</th><th>Zurück</th><th>Verkauft</th><th>Freikarten</th><th>Betrag</th><th></th></tr></thead>
            <tbody>
            @forelse ($abrechnung as $a)
                <tr>
                    <td data-titel="Stelle">{{ $a['stelle']->name }}@if ($a['stelle']->druckt_selbst) <span class="marke">druckt selbst</span>@endif</td>
                    <td data-titel="Ausgegeben">{{ $a['ausgegeben'] }}</td><td data-titel="Zurück">{{ $a['zurueck'] }}</td>
                    <td data-titel="Verkauft">{{ $a['verkauft'] }}</td><td data-titel="Freikarten">{{ $a['freikarten'] }}</td>
                    <td data-titel="Betrag"><strong>{{ $g($a['betrag']) }}</strong></td>
                    <td class="aktionen"><a href="{{ route('tickets.auswertung.abrechnung', [$v, $a['stelle']]) }}" target="_blank">PDF</a></td>
                </tr>
            @empty
                <tr><td colspan="7">Keine Karten an Vorverkaufsstellen ausgegeben.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="karte">
        <h2>Freikarten</h2>
        <table class="tabelle tabelle--klein">
            <thead><tr><th>Name</th><th>Über</th><th>Anzahl</th><th>Eingelassen</th></tr></thead>
            <tbody>
            @forelse ($freikarten as $f)
                <tr><td data-titel="Name">{{ $f['name'] }}</td><td data-titel="Über">{{ $f['stelle'] }}</td><td data-titel="Anzahl">{{ $f['anzahl'] }}</td><td data-titel="Eingelassen">{{ $f['eingelassen'] }}</td></tr>
            @empty
                <tr><td colspan="4">Keine Freikarten.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
