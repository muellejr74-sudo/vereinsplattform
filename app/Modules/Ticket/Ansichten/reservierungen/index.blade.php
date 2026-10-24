@extends('layouts.basis')

@section('titel', 'Reservierungen')

@section('inhalt')
    <div class="titelzeile">
        <h1>Reservierungen</h1>
        @if ($darfAnlegen)<a class="knopf" href="{{ route('tickets.reservierungen.create') }}">Neue Reservierung</a>@endif
    </div>
    @include('kern::teile.fehler')

    <form method="get" class="formular formular--zeile">
        <select name="status" onchange="this.form.submit()" aria-label="Anzeigen">
            @foreach (['offen' => 'offen', 'zahlung' => 'Vorkasse: Zahlung offen', 'ueberfaellig' => 'Zahlung überfällig ('.$ueberfaellig.')', 'bezahlt' => 'bezahlt, Versand ausstehend', 'erledigt' => 'abgeholt/versendet', 'storniert' => 'storniert', 'alle' => 'alle'] as $wert => $text)
                <option value="{{ $wert }}" @selected($status === $wert)>{{ $text }}</option>
            @endforeach
        </select>
        <input name="suche" value="{{ $suche }}" placeholder="Name suchen" aria-label="Name suchen">
        <button type="submit" class="knopf knopf--zweit">Suchen</button>
    </form>

    @if ($ueberfaellig > 0 && $status !== 'ueberfaellig')
        <div class="meldung meldung--hinweis"><a href="{{ route('tickets.reservierungen.index', ['status' => 'ueberfaellig']) }}">{{ $ueberfaellig }} Vorkasse-Reservierungen mit abgelaufener Zahlungsfrist</a></div>
    @endif

    @if ($status === 'ueberfaellig' && $reservierungen->isNotEmpty())<form method="post" action="{{ route('tickets.reservierungen.ueberfaellig') }}" onsubmit="return confirm('Ausgewählte Reservierungen stornieren? Die Plätze werden frei.')">@csrf @endif
    <div class="tabelle-rahmen" style="margin-top:0.75rem">
        <table class="tabelle">
            <thead><tr>@if ($status === 'ueberfaellig')<th></th>@endif<th>Nr.</th><th>Name</th><th>Vorstellung</th><th>Karten</th><th>Art</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($reservierungen as $r)
                <tr>
                    @if ($status === 'ueberfaellig')<td><input type="checkbox" name="ids[]" value="{{ $r->id }}" checked aria-label="auswählen"></td>@endif
                    <td data-titel="Nr."><a href="{{ route('tickets.reservierungen.show', $r) }}">{{ $r->kennung() }}</a></td>
                    <td data-titel="Name">{{ $r->name }}@if ($r->verkaufsstelle)<br><span class="hinweis">{{ $r->verkaufsstelle->name }}</span>@endif</td>
                    <td data-titel="Vorstellung">{{ $r->vorstellung->veranstaltung->kurzname ?: $r->vorstellung->veranstaltung->name }}, {{ $r->vorstellung->anzeige() }}</td>
                    <td data-titel="Karten">{{ $r->anzahl() }} · {{ \App\Modules\Ticket\Dienste\Geld::anzeigen($r->betragCent()) }}</td>
                    <td data-titel="Art">{{ ['gedruckt' => 'gedruckt', 'digital' => 'digital', 'vorkasse' => 'Vorkasse'][$r->art] }}</td>
                    <td data-titel="Status">{{ $r->statusText() }}@if ($r->art === 'vorkasse' && $r->status === 'offen' && $r->zahlungsfrist)<br><span class="hinweis">bis {{ $r->zahlungsfrist->format('d.m.Y') }}</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7">Keine Reservierungen.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($status === 'ueberfaellig' && $reservierungen->isNotEmpty())
        <button type="submit" class="knopf" style="margin-top:0.75rem">Ausgewählte stornieren</button>
    </form>@endif
    {{ $reservierungen->links() }}
@endsection
