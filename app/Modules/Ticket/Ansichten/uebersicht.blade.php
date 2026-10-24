@extends('layouts.basis')

@section('titel', 'Tickets')

@section('inhalt')
    <h1>Ticketverwaltung</h1>
    @include('kern::teile.fehler')

    <div class="kacheln">
        @if ($offen !== null)
            <a class="kachel" href="{{ route('tickets.auftraege.index') }}"><strong>Druckstelle ({{ $offen }} offen)</strong><span>Aufträge bestätigen, Etiketten drucken, übergeben</span></a>
        @else
            <a class="kachel" href="{{ route('tickets.auftraege.index') }}"><strong>Meine Aufträge</strong><span>Kartenbestellungen und Freikarten</span></a>
        @endif
        @if ($stellen->where('druckt_selbst', true)->isNotEmpty())
            <a class="kachel" href="{{ route('tickets.selbstdruck.create') }}"><strong>Karten verkaufen und drucken</strong><span>direkt an der Verkaufsstelle, ohne Druckstelle</span></a>
        @endif
        @if ($stellen->isNotEmpty())
            <a class="kachel" href="{{ route('tickets.auftraege.create') }}"><strong>Karten bestellen</strong><span>für {{ $stellen->pluck('name')->join(', ') }}</span></a>
        @endif
        @if ($stellen->isNotEmpty() || $intern)
            <a class="kachel" href="{{ route('tickets.auftraege.create', ['art' => 'freikarten']) }}"><strong>Freikarten anfordern</strong><span>Stapel mit einem Namen, z. B. „Sparkasse – 10 Karten“</span></a>
        @endif
        @if ($reservieren)
            <a class="kachel" href="{{ route('tickets.reservierungen.index') }}"><strong>Reservierungen</strong><span>telefonisch, gedruckt, digital oder mit Vorkasse{{ $ueberfaellig ? ' · '.$ueberfaellig.' Zahlung überfällig' : '' }}</span></a>
        @endif
        @can('ticket.abendkasse')
            <a class="kachel" href="{{ route('tickets.abendkasse.index') }}"><strong>Abendkasse</strong><span>Verkauf, Reservierungen ausgeben, nicht abgeholte</span></a>
        @endcan
        @can('ticket.stornieren')
            <a class="kachel" href="{{ route('tickets.storno.index') }}"><strong>Stornieren</strong><span>einzeln oder als Stapel per Scan</span></a>
        @endcan
        @if (auth()->user()->can('ticket.reservierungen') || auth()->user()->can('ticket.zahlungseingang'))
            <a class="kachel" href="{{ route('tickets.einstellungen') }}"><strong>Einstellungen</strong><span>Voreinstellungen, Zahlungsfrist, Bankverbindung</span></a>
        @endif
        @can('ticket.verkaufsstellen')
            <a class="kachel" href="{{ route('tickets.verkaufsstellen') }}"><strong>Verkaufsstellen</strong><span>Vorverkaufsstellen und Abendkasse, Zugänge zuordnen</span></a>
        @endcan
    </div>

    @if ($veranstaltungen->isNotEmpty())
        <section class="karte" style="margin-top:1rem">
            <h2>Veranstaltungen</h2>
            <div class="tabelle-rahmen">
                <table class="tabelle">
                    <thead><tr><th>Vorstellung</th><th>Kontingent</th><th>Belegt</th><th>Frei</th><th>Angefordert</th></tr></thead>
                    <tbody>
                    @foreach ($veranstaltungen as $veranstaltung)
                        <tr><td colspan="5"><strong><a href="{{ route('tickets.veranstaltung', $veranstaltung) }}">{{ $veranstaltung->name }}</a></strong> <span class="hinweis">– Kategorien, Preise, Kontingente</span></td></tr>
                        @foreach ($veranstaltung->vorstellungen as $v)
                            @php $z = $kontingent->uebersicht($v); @endphp
                            <tr>
                                <td data-titel="Vorstellung">{{ $v->anzeige() }}</td>
                                <td data-titel="Kontingent">{{ $z['kontingent'] ?? 'nicht festgelegt' }}</td>
                                <td data-titel="Belegt">{{ $z['belegt'] }}</td>
                                <td data-titel="Frei">{{ $z['frei'] ?? '–' }}</td>
                                <td data-titel="Angefordert">{{ $z['angefordert'] ?: '–' }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
