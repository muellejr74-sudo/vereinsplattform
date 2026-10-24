@extends('layouts.basis')

@section('titel', $druckstelle ? 'Druckstelle' : 'Meine Aufträge')

@section('inhalt')
    <div class="titelzeile">
        <h1>{{ $druckstelle ? 'Druckstelle – Kartenaufträge' : 'Meine Aufträge' }}</h1>
        <a class="knopf knopf--zweit" href="{{ route('tickets.uebersicht') }}">Übersicht</a>
    </div>
    @include('kern::teile.fehler')

    <form method="get" class="formular formular--zeile">
        <div class="feld" style="flex:0 0 16rem"><label for="status">Anzeigen</label>
            <select id="status" name="status" onchange="this.form.submit()">
                <option value="offen" @selected($status === 'offen')>offene Aufträge</option>
                <option value="alle" @selected($status === 'alle')>alle</option>
                @foreach (\App\Modules\Ticket\Models\Kartenauftrag::STATUS as $k => $b)
                    <option value="{{ $k }}" @selected($status === $k)>{{ $b }}</option>
                @endforeach
            </select></div>
        <noscript><button type="submit" class="knopf knopf--zweit">Anzeigen</button></noscript>
    </form>

    <div class="tabelle-rahmen">
        <table class="tabelle">
            <thead><tr><th>Nr.</th><th>Für</th><th>Vorstellung</th><th>Karten</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($auftraege as $a)
                <tr>
                    <td data-titel="Nr."><a href="{{ route('tickets.auftraege.show', $a) }}">#{{ $a->id }}</a></td>
                    <td data-titel="Für">{{ $a->istReservierung() ? $a->artText() : $a->stelleText() }}@if ($a->istFreikarten() || $a->istReservierung())<br><span class="marke">{{ $a->istFreikarten() ? 'Freikarten' : 'Name' }}: {{ $a->name }}</span>@endif</td>
                    <td data-titel="Vorstellung">{{ $a->vorstellung->veranstaltung->name }}<br><span class="hinweis">{{ $a->vorstellung->anzeige() }}</span></td>
                    <td data-titel="Karten">
                        @foreach ($a->positionen as $p){{ $p->anzahl_bestaetigt ?? $p->anzahl }} × {{ $p->kategorie->name }}@if (! $loop->last), @endif @endforeach
                    </td>
                    <td data-titel="Status"><span class="status {{ in_array($a->status, ['angefordert', 'bestaetigt', 'gedruckt']) ? 'status--offen' : (in_array($a->status, ['ausgegeben']) ? 'status--fertig' : 'status--storniert') }}">{{ $a->statusText() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5">Keine Aufträge.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $auftraege->links() }}
@endsection
