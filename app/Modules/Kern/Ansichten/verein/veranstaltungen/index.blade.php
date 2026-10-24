@extends('layouts.basis')

@section('titel', 'Veranstaltungen')

@section('inhalt')
    <div class="titelzeile">
        <h1>Veranstaltungen</h1>
        <a class="knopf" href="{{ route('verein.veranstaltungen.create') }}">Veranstaltung anlegen</a>
    </div>

    <div class="tabelle-rahmen">
        <table class="tabelle">
            <thead><tr><th>Name</th><th>Vorstellungen</th><th>Zeitraum</th><th></th></tr></thead>
            <tbody>
            @forelse ($veranstaltungen as $v)
                <tr>
                    <td data-titel="Name"><a href="{{ route('verein.veranstaltungen.show', $v) }}">{{ $v->name }}</a></td>
                    <td data-titel="Vorstellungen">{{ $v->vorstellungen_count }}</td>
                    <td data-titel="Zeitraum">
                        @if ($v->vorstellungen_min_beginn_um)
                            {{ \Illuminate\Support\Carbon::parse($v->vorstellungen_min_beginn_um)->format('d.m.Y') }}
                            @if ($v->vorstellungen_count > 1) – {{ \Illuminate\Support\Carbon::parse($v->vorstellungen_max_beginn_um)->format('d.m.Y') }} @endif
                        @else – @endif
                    </td>
                    <td class="aktionen"><a href="{{ route('verein.veranstaltungen.show', $v) }}">Öffnen</a></td>
                </tr>
            @empty
                <tr><td colspan="4">Noch keine Veranstaltungen angelegt.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
