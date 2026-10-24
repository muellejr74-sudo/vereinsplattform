@extends('layouts.basis')

@section('titel', 'Personen & Rechte')

@section('inhalt')
    <div class="titelzeile">
        <h1>Personen &amp; Rechte</h1>
        @can('benutzer.verwalten')
            <a class="knopf" href="{{ route('verein.personen.hinzufuegen') }}">Person hinzufügen</a>
        @endcan
    </div>
    @include('kern::teile.fehler')

    <div class="tabelle-rahmen">
        <table class="tabelle">
            <thead><tr><th>Name</th><th>E-Mail</th><th>Rechte</th><th>Letzte Anmeldung</th><th></th></tr></thead>
            <tbody>
            @forelse ($personen as $person)
                @php $seine = $rechte->get($person->id, collect())->pluck('recht'); @endphp
                <tr>
                    <td data-titel="Name">
                        {{ $person->name }}
                        @if ($seine->contains('rechte.vergeben')) <span class="marke">Vereinsadmin</span> @endif
                        @if (! $person->aktiv) <span class="status status--storniert">deaktiviert</span> @endif
                    </td>
                    <td data-titel="E-Mail">{{ $person->email }}</td>
                    <td data-titel="Rechte">{{ $seine->count() }}</td>
                    <td data-titel="Letzte Anmeldung">{{ $person->letzte_anmeldung_am?->format('d.m.Y H:i') ?? 'noch nie' }}</td>
                    <td class="aktionen">
                        <a href="{{ route('verein.personen.rechte', $person) }}">{{ $person->is(auth()->user()) ? 'Meine Rechte' : 'Rechte' }}</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Diesem Verein sind noch keine Personen zugeordnet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
