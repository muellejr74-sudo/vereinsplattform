@extends('layouts.basis')

@section('titel', 'Tickets – '.$veranstaltung->name)

@section('inhalt')
    <h1>{{ $veranstaltung->name }}</h1>
    <p class="hinweis"><a href="{{ route('tickets.uebersicht') }}">‹ Ticketverwaltung</a></p>
    @include('kern::teile.fehler')

    <section class="karte">
        <h2>Kategorien und Preise</h2>
        <p class="hinweis">Gelten für alle Vorstellungen. Abweichende Preise für einzelne Vorstellungen weiter unten.</p>
        @can('ticket.kategorien')
            @include('kern::teile.sortierbar')
            <p class="hinweis">Reihenfolge (so erscheinen die Kategorien beim Verkauf): am Griff ☰ ziehen oder mit ▲ ▼. Zum Bearbeiten antippen.</p>
            <ul class="sortier-liste" data-sortierbar data-url="{{ route('tickets.kategorien.reihenfolge', $veranstaltung) }}" data-token="{{ csrf_token() }}">
                @foreach ($kategorien->where('ist_freikarte', false) as $k)
                    <li data-id="{{ $k->id }}">
                        <details>
                            <summary>
                                <span class="griff" title="Zum Verschieben ziehen" aria-hidden="true">☰</span>
                                <span class="sortier-name">{{ $k->name }}<span class="marke">{{ \App\Modules\Ticket\Dienste\Geld::anzeigen($k->preis_cent) }}</span></span>
                                <span class="sortier-knoepfe">
                                    <button type="button" data-schieben="hoch" aria-label="{{ $k->name }} nach oben">▲</button>
                                    <button type="button" data-schieben="runter" aria-label="{{ $k->name }} nach unten">▼</button>
                                </span>
                                <span class="sortier-bearbeiten-knopf">✎</span>
                            </summary>
                            <form method="post" action="{{ route('tickets.kategorien.speichern', [$veranstaltung, $k]) }}" class="formular">
                                @csrf @method('put')
                                <label for="kn{{ $k->id }}">Name</label><input id="kn{{ $k->id }}" name="name" value="{{ $k->name }}" maxlength="40" required>
                                <label for="kp{{ $k->id }}">Preis (€)</label><input id="kp{{ $k->id }}" name="preis" value="{{ number_format($k->preis_cent / 100, 2, ',', '') }}" inputmode="decimal" required>
                                <div class="knopfzeile">
                                    <button type="submit" class="knopf">Speichern</button>
                                    <button type="submit" form="kl{{ $k->id }}" class="link-knopf">Löschen</button>
                                </div>
                            </form>
                            <form id="kl{{ $k->id }}" method="post" action="{{ route('tickets.kategorien.loeschen', [$veranstaltung, $k]) }}" onsubmit="return confirm('Kategorie „{{ $k->name }}“ löschen?')">@csrf @method('delete')</form>
                        </details>
                    </li>
                @endforeach
            </ul>
            <p class="hinweis">Freikarte – 0,00 € (feste Kategorie, immer vorhanden)</p>
        @else
            <ul>
                @foreach ($kategorien as $k)<li>{{ $k->name }} · {{ \App\Modules\Ticket\Dienste\Geld::anzeigen($k->preis_cent) }}</li>@endforeach
            </ul>
        @endcan
        @can('ticket.kategorien')
            <form method="post" action="{{ route('tickets.kategorien.neu', $veranstaltung) }}" class="formular formular--zeile" style="margin-top:1rem">
                @csrf
                <div class="feld"><label for="neu_name">Neue Kategorie</label><input id="neu_name" name="name" maxlength="40" required placeholder="z. B. Erwachsene" value="{{ old('name') }}"></div>
                <div class="feld" style="flex:0 0 7rem"><label for="neu_preis">Preis (€)</label><input id="neu_preis" name="preis" inputmode="decimal" required placeholder="12,00" value="{{ old('preis') }}"></div>
                <button type="submit" class="knopf">Hinzufügen</button>
            </form>
        @endcan
    </section>

    <section class="karte">
        <h2>Vorstellungen: Kontingent und Preise</h2>
        @foreach ($vorstellungen as $v)
            @php $z = $zahlen[$v->id]; @endphp
            <div class="vorstellungsblock">
                <h3>{{ $v->anzeige() }}</h3>
                <p>Belegt: <strong>{{ $z['belegt'] }}</strong>@if ($z['freikarten']) (davon {{ $z['freikarten'] }} Freikarten)@endif ·
                   Frei: <strong>{{ $z['frei'] ?? '–' }}</strong> ·
                   Angefordert, noch nicht bestätigt: <strong>{{ $z['angefordert'] }}</strong>
                   @if (\App\Modules\Ticket\Dienste\Abschlusssperre::istAbgeschlossen($v->id)) · <span class="marke">abgeschlossen</span>@endif</p>
                <p class="knopfzeile">
                    @can('tickets.auswerten')<a class="knopf knopf--zweit" href="{{ route('tickets.auswertung', $v) }}">Auswertung</a>@endcan
                    @can('ticket.abschluss')<a class="knopf knopf--zweit" href="{{ route('tickets.abschluss', $v) }}">Abschluss</a>@endcan
                    @can('tickets.notfallliste')<a class="knopf knopf--zweit" href="{{ route('tickets.notfallliste', $v) }}" target="_blank">Notfallliste</a>@endcan
                </p>
                @can('ticket.kontingente')
                    <form method="post" action="{{ route('tickets.kontingent', [$veranstaltung, $v]) }}" class="formular formular--zeile">
                        @csrf @method('put')
                        <div class="feld" style="flex:0 0 10rem"><label for="ko{{ $v->id }}">Kontingent (Plätze)</label><input id="ko{{ $v->id }}" name="kontingent" type="number" min="0" value="{{ $v->kontingent }}" required></div>
                        <button type="submit" class="knopf knopf--zweit">Speichern</button>
                    </form>
                @else
                    <p>Kontingent: {{ $v->kontingent ?? 'nicht festgelegt' }}</p>
                @endcan
                @can('ticket.kategorien')
                    @if ($kategorien->where('ist_freikarte', false)->isNotEmpty())
                        <details>
                            <summary>Abweichende Preise für diese Vorstellung</summary>
                            <form method="post" action="{{ route('tickets.preise', [$veranstaltung, $v]) }}" class="formular formular--zeile">
                                @csrf @method('put')
                                @foreach ($kategorien->where('ist_freikarte', false) as $k)
                                    <div class="feld" style="flex:0 0 9rem"><label for="pa{{ $v->id }}_{{ $k->id }}">{{ $k->name }}</label>
                                        <input id="pa{{ $v->id }}_{{ $k->id }}" name="preise[{{ $k->id }}]" inputmode="decimal" placeholder="{{ number_format($k->preis_cent / 100, 2, ',', '') }}"
                                               value="{{ isset($abweichungen[$v->id][$k->id]) ? number_format($abweichungen[$v->id][$k->id] / 100, 2, ',', '') : '' }}"></div>
                                @endforeach
                                <button type="submit" class="knopf knopf--zweit">Speichern</button>
                            </form>
                            <p class="hinweis">Leer = Normalpreis.</p>
                        </details>
                    @endif
                @endcan
            </div>
        @endforeach
    </section>
@endsection
