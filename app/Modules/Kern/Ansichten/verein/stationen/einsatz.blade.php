@extends('layouts.basis')

@section('titel', 'Personal & Einsatz-Code')

@section('inhalt')
    <h1>Personal &amp; Einsatz-Code</h1>
    <p class="hinweis">{{ $vorstellung->veranstaltung->name }} · {{ $vorstellung->anzeige() }}<br>
        Anmeldung möglich von {{ $vorstellung->fensterBeginn()->format('d.m. H:i') }} bis {{ $vorstellung->fensterEnde()->format('d.m. H:i') }} Uhr</p>
    @include('kern::teile.fehler')

    <section class="karte">
        <h2>Einsatz-Code</h2>
        @if ($vorstellung->einsatz_code)
            <p class="einsatzcode">{{ substr($vorstellung->einsatz_code, 0, 3) }} {{ substr($vorstellung->einsatz_code, 3) }}</p>
            <p>Das Personal öffnet <strong>{{ route('einsatz') }}</strong> auf dem Handy, gibt diesen Code ein und wählt seine Station.</p>
            <div class="qr qr--klein">{!! \App\Modules\Kern\Dienste\QrCode::svg(route('einsatz'), 160) !!}</div>
        @else
            <p>Noch kein Code erzeugt.</p>
        @endif
        @unless ($vorstellung->istVorbei())
            <form method="post" action="{{ route('verein.einsatz.code', $vorstellung) }}"
                  @if ($vorstellung->einsatz_code) onsubmit="return confirm('Neuen Code erzeugen? Der alte Code funktioniert dann nicht mehr für neue Anmeldungen.')" @endif>
                @csrf <button type="submit" class="knopf">{{ $vorstellung->einsatz_code ? 'Neuen Code erzeugen' : 'Code erzeugen' }}</button>
            </form>
        @endunless
    </section>

    <section class="karte">
        <h2>Wer ist heute an welcher Station?</h2>
        <form method="post" action="{{ route('verein.einsatz.namen', $vorstellung) }}" class="formular">
            @csrf @method('put')
            @foreach ($stationen as $eintrag)
                <label for="name_{{ $eintrag['station']->id }}">{{ $eintrag['station']->name }}
                    @if ($eintrag['belegt'])<span class="status status--fertig">angemeldet</span>@endif</label>
                <input id="name_{{ $eintrag['station']->id }}" name="namen[{{ $eintrag['station']->id }}]" value="{{ $eintrag['name'] }}" maxlength="60" placeholder="Name (optional)">
            @endforeach
            <button type="submit" class="knopf">Namen speichern</button>
        </form>
    </section>

    <section class="karte">
        <h2>Angemeldete Geräte ({{ $geraete->count() }})</h2>
        @if ($geraete->isEmpty())
            <p class="hinweis">Noch keine Geräte angemeldet.</p>
        @else
            <div class="tabelle-rahmen">
                <table class="tabelle tabelle--klein">
                    <thead><tr><th>Station</th><th>Angemeldet</th><th>Zuletzt aktiv</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($geraete as $g)
                        <tr>
                            <td data-titel="Station">{{ $g->station?->name }}</td>
                            <td data-titel="Angemeldet">{{ $g->angemeldet_am->format('H:i') }} Uhr</td>
                            <td data-titel="Zuletzt aktiv">{{ $g->zuletzt_aktiv_am?->format('H:i') }} Uhr</td>
                            <td class="aktionen">
                                <form method="post" action="{{ route('verein.einsatz.geraet', [$vorstellung, $g]) }}" onsubmit="return confirm('Gerät abmelden?')">
                                    @csrf @method('delete') <button type="submit" class="link-knopf">Abmelden</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
    <p><a href="{{ route('verein.veranstaltungen.show', $vorstellung->veranstaltung_id) }}">‹ Zurück zur Veranstaltung</a></p>
@endsection
