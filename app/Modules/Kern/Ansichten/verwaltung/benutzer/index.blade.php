@extends('layouts.basis')

@section('titel', 'Benutzer')

@section('inhalt')
    <div class="titelzeile">
        <h1>Benutzer</h1>
        <a class="knopf" href="{{ route('verwaltung.benutzer.create') }}">Benutzer anlegen</a>
    </div>
    @include('kern::teile.fehler')

    <form method="get" class="formular formular--zeile">
        <label for="suche">Suche</label>
        <input id="suche" name="suche" type="search" value="{{ $suche }}" placeholder="Name oder E-Mail">
        <button type="submit" class="knopf knopf--zweit">Suchen</button>
    </form>

    <div class="tabelle-rahmen">
        <table class="tabelle">
            <thead><tr><th>Name</th><th>E-Mail</th><th>Vereine</th><th>Letzte Anmeldung</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($benutzer as $person)
                <tr>
                    <td data-titel="Name">{{ $person->name }} @if ($person->istSuperAdmin()) <span class="marke">Super-Admin</span> @endif</td>
                    <td data-titel="E-Mail">{{ $person->email }}</td>
                    <td data-titel="Vereine">{{ $person->istSuperAdmin() ? 'alle' : ($person->vereine->pluck('kuerzel')->join(', ') ?: '–') }}</td>
                    <td data-titel="Letzte Anmeldung">{{ $person->letzte_anmeldung_am?->format('d.m.Y H:i') ?? 'noch nie' }}</td>
                    <td data-titel="Status"><span class="status {{ $person->aktiv ? 'status--fertig' : 'status--storniert' }}">{{ $person->aktiv ? 'aktiv' : 'deaktiviert' }}</span></td>
                    <td class="aktionen">
                        <a href="{{ route('verwaltung.benutzer.edit', $person) }}">Bearbeiten</a>
                        @if ($person->aktiv && ! $person->letzte_anmeldung_am)
                            <form method="post" action="{{ route('verwaltung.benutzer.einladung', $person) }}" class="inline">
                                @csrf <button type="submit" class="link-knopf">Einladung erneut senden</button>
                            </form>
                        @endif
                        @unless ($person->is(auth()->user()))
                            <form method="post" action="{{ route('verwaltung.benutzer.aktiv', $person) }}" class="inline"
                                  onsubmit="return confirm('{{ $person->aktiv ? 'Benutzer wirklich deaktivieren? Er wird sofort abgemeldet.' : 'Benutzer wieder aktivieren?' }}')">
                                @csrf <button type="submit" class="link-knopf">{{ $person->aktiv ? 'Deaktivieren' : 'Aktivieren' }}</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Keine Benutzer gefunden.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $benutzer->links() }}
@endsection
