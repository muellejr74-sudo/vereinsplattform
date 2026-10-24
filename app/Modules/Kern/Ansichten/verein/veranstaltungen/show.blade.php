@extends('layouts.basis')

@section('titel', $veranstaltung->name)

@section('inhalt')
    <div class="titelzeile">
        <h1>{{ $veranstaltung->name }}</h1>
        <div class="knopfzeile" style="margin:0">
            @can('tickets.nutzen')<a class="knopf knopf--zweit" href="{{ route('tickets.veranstaltung', $veranstaltung) }}">Tickets &amp; Preise</a>@endcan
            <a class="knopf knopf--zweit" href="{{ route('verein.veranstaltungen.edit', $veranstaltung) }}">Bearbeiten</a>
        </div>
    </div>
    @include('kern::teile.fehler')
    @if ($laufend)
        <div class="meldung meldung--hinweis">Gerade läuft die Vorstellung am {{ $laufend->anzeige() }}.</div>
    @endif

    <section class="karte">
        <div class="titelzeile">
            <h2>Vorstellungen</h2>
            <a class="knopf" href="{{ route('verein.vorstellungen.create', $veranstaltung) }}">Vorstellung anlegen</a>
        </div>
        <div class="tabelle-rahmen">
            <table class="tabelle">
                <thead><tr><th>Termin</th><th>Einlass</th><th>Ende</th>@if ($mehrereSaele)<th>Saal</th>@endif<th>Kontingent</th><th></th></tr></thead>
                <tbody>
                @forelse ($vorstellungen as $v)
                    <tr>
                        <td data-titel="Termin">{{ $v->anzeige() }} @if ($v->istImZeitfenster())<span class="status status--offen">läuft</span>@elseif ($v->istVorbei())<span class="marke">vorbei</span>@endif</td>
                        <td data-titel="Einlass">{{ $v->einlass_um?->format('H:i') ?? '–' }}</td>
                        <td data-titel="Ende">{{ $v->ende_um?->format('H:i') ?? '–' }}</td>
                        @if ($mehrereSaele)<td data-titel="Saal">{{ $v->saal?->anzeigename() }}</td>@endif
                        <td data-titel="Kontingent">{{ $v->kontingent ?? '–' }}</td>
                        <td class="aktionen">
                            @can('einsatz.verwalten')<a href="{{ route('verein.einsatz', $v) }}">Personal &amp; Einsatz-Code</a>@endcan
                            <a href="{{ route('verein.vorstellungen.edit', [$veranstaltung, $v]) }}">Bearbeiten</a>
                            <form method="post" action="{{ route('verein.vorstellungen.destroy', [$veranstaltung, $v]) }}" class="inline" onsubmit="return confirm('Vorstellung wirklich löschen?')">
                                @csrf @method('delete') <button type="submit" class="link-knopf">Löschen</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">Noch keine Vorstellungen.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="karte">
        <div class="titelzeile">
            <h2>Tische ({{ $zuordnungen->whereNotNull('tischnummer')->count() }})</h2>
            @can('qrcodes.verwalten')
                <a class="knopf knopf--zweit" href="{{ route('verein.tische.zuordnen', ['veranstaltung' => $veranstaltung->id]) }}">Eigenen Code zuordnen</a>
            @endcan
        </div>
        <p class="hinweis">Tische werden zugeordnet, indem Sie den QR-Code am Tisch mit der Handy-Kamera scannen (angemeldet, im richtigen Verein).</p>
        @if ($zuordnungen->isNotEmpty())
            <div class="tabelle-rahmen">
                <table class="tabelle tabelle--klein">
                    <thead><tr><th>Tisch</th><th>Code</th><th>Gültig bis</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($zuordnungen as $z)
                        <tr>
                            <td data-titel="Tisch">{{ $z->tischnummer ?? 'Einheitscode' }}</td>
                            <td data-titel="Code">{{ $z->tischcode?->bezeichnung }} <span class="hinweis">{{ $z->tischcode?->istBetreiberTisch() ? '(geliehen)' : '' }}</span></td>
                            <td data-titel="Gültig bis">{{ $z->gueltig_bis->format('d.m.Y') }}</td>
                            <td class="aktionen">
                                @can('qrcodes.verwalten')
                                    <form method="post" action="{{ route('verein.tische.loesen', $z) }}" class="inline" onsubmit="return confirm('Zuordnung lösen?')">
                                        @csrf @method('delete') <button type="submit" class="link-knopf">Lösen</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @can('qrcodes.verwalten')
                <form method="post" action="{{ route('verein.tische.alle', $veranstaltung) }}" class="formular formular--zeile" style="margin-top:1rem">
                    @csrf @method('put')
                    <div class="feld"><label for="alle_bis">Alle Tische gültig bis</label>
                        <input id="alle_bis" name="gueltig_bis" type="date" value="{{ $veranstaltung->vorschlagGueltigBis()->toDateString() }}" required></div>
                    <button type="submit" class="knopf knopf--zweit">Für alle übernehmen</button>
                </form>
            @endcan
        @endif
    </section>

    <form method="post" action="{{ route('verein.veranstaltungen.destroy', $veranstaltung) }}" onsubmit="return confirm('Veranstaltung mit allen Vorstellungen löschen? Die Tische werden frei.')">
        @csrf @method('delete') <button type="submit" class="link-knopf">Veranstaltung löschen</button>
    </form>
@endsection
