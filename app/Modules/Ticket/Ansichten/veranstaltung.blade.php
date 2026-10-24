@extends('layouts.basis')

@section('titel', 'Tickets – '.$veranstaltung->name)

@section('inhalt')
    <h1>{{ $veranstaltung->name }}</h1>
    <p class="hinweis"><a href="{{ route('tickets.uebersicht') }}">‹ Ticketverwaltung</a></p>
    @include('kern::teile.fehler')

    <section class="karte">
        <h2>Kategorien und Preise</h2>
        <p class="hinweis">Gelten für alle Vorstellungen. Abweichende Preise für einzelne Vorstellungen weiter unten.</p>
        <div class="tabelle-rahmen">
            <table class="tabelle">
                <thead><tr><th>Kategorie</th><th>Preis</th><th></th></tr></thead>
                <tbody>
                @foreach ($kategorien as $k)
                    <tr>
                        @if ($k->ist_freikarte)
                            <td data-titel="Kategorie">{{ $k->name }} <span class="hinweis">(fest)</span></td>
                            <td data-titel="Preis">0,00 €</td><td></td>
                        @else
                            <td colspan="2" style="padding:0">
                                @can('ticket.kategorien')
                                    <form method="post" action="{{ route('tickets.kategorien.speichern', [$veranstaltung, $k]) }}" class="formular formular--zeile" style="margin:0;padding:0.5rem 0.75rem">
                                        @csrf @method('put')
                                        <div class="feld"><label class="sr" for="kn{{ $k->id }}">Name</label><input id="kn{{ $k->id }}" name="name" value="{{ $k->name }}" maxlength="40" required></div>
                                        <div class="feld" style="flex:0 0 7rem"><label class="sr" for="kp{{ $k->id }}">Preis</label><input id="kp{{ $k->id }}" name="preis" value="{{ number_format($k->preis_cent / 100, 2, ',', '') }}" inputmode="decimal" required></div>
                                        <input type="hidden" name="reihenfolge" value="{{ $k->reihenfolge }}">
                                        <button type="submit" class="knopf knopf--zweit">Speichern</button>
                                    </form>
                                @else
                                    <div style="padding:0.5rem 0.75rem">{{ $k->name }} · {{ \App\Modules\Ticket\Dienste\Geld::anzeigen($k->preis_cent) }}</div>
                                @endcan
                            </td>
                            <td class="aktionen">
                                @can('ticket.kategorien')
                                    <form method="post" action="{{ route('tickets.kategorien.loeschen', [$veranstaltung, $k]) }}" onsubmit="return confirm('Kategorie löschen?')">
                                        @csrf @method('delete') <button type="submit" class="link-knopf">Löschen</button>
                                    </form>
                                @endcan
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
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
                   Angefordert, noch nicht bestätigt: <strong>{{ $z['angefordert'] }}</strong></p>
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
