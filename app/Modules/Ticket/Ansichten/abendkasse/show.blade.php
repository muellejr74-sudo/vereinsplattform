@extends('layouts.basis')

@section('titel', 'Abendkasse')

@php $geld = fn ($c) => \App\Modules\Ticket\Dienste\Geld::anzeigen($c); @endphp

@section('inhalt')
    <div class="titelzeile">
        <h1>Abendkasse</h1>
        <a class="knopf knopf--zweit" href="{{ route('tickets.abendkasse.index') }}">Vorstellung wechseln</a>
    </div>
    <p><strong>{{ $v->veranstaltung->name }}</strong> · {{ $v->anzeige() }}</p>
    <p class="zaehler">Frei: <strong>{{ $zahlen['frei'] ?? '–' }}</strong> · belegt {{ $zahlen['belegt'] }} von {{ $zahlen['kontingent'] ?? '–' }} · eingelassen {{ $zahlen['eingelassen'] }}</p>
    @include('kern::teile.fehler')

    <section class="karte karte--wichtig">
        <h2>Karten verkaufen</h2>
        @if ($kategorien->isEmpty())
            <p>Für diese Veranstaltung sind keine Kategorien angelegt.</p>
        @else
            <form method="post" action="{{ route('tickets.abendkasse.verkauf', $v) }}" class="formular" id="verkauf"
                  oninput="var s = 0; this.querySelectorAll('[data-preis]').forEach(function (e) { s += (parseInt(e.value, 10) || 0) * parseInt(e.dataset.preis, 10); }); document.getElementById('verkauf-summe').textContent = (s / 100).toFixed(2).replace('.', ',') + ' €';">
                @csrf
                @foreach ($kategorien as $k)
                    <div class="formular--zeile" style="margin:0.25rem 0">
                        <label for="vk{{ $k->id }}" style="flex:1">{{ $k->name }} <span class="hinweis">{{ $geld($k->preisFuer($v->id)) }}</span></label>
                        <input id="vk{{ $k->id }}" name="mengen[{{ $k->id }}]" type="number" min="0" max="200" value="0" data-preis="{{ $k->preisFuer($v->id) }}" style="flex:0 0 6rem" inputmode="numeric">
                    </div>
                @endforeach
                <p>Zu zahlen: <span class="summe" id="verkauf-summe">0,00 €</span></p>
                <div class="knopfzeile knopfzeile--voll">
                    <button type="submit" name="zahlungsart" value="bar" class="knopf knopf--gross">Bar bezahlt</button>
                    <button type="submit" name="zahlungsart" value="karte" class="knopf knopf--gross knopf--zweit">Mit Karte bezahlt</button>
                </div>
                <small class="hinweis">Ohne Etikett – die Gäste gelten sofort als eingelassen.</small>
            </form>
        @endif
    </section>

    <section class="karte">
        <h2>Reservierungen abholen ({{ $reservierungen->count() }})</h2>
        <form method="get" class="formular formular--zeile">
            <input name="suche" value="{{ $suche }}" placeholder="Name oder Nummer" aria-label="Reservierung suchen" style="flex:1">
            <button type="submit" class="knopf knopf--zweit">Suchen</button>
        </form>
        @forelse ($reservierungen as $r)
            <a class="station-knopf" href="{{ route('tickets.abendkasse.reservierung', [$v, $r]) }}" style="margin-top:0.5rem">
                <strong>{{ $r->name }}</strong>
                <span>{{ $r->anzahl() }} Karten · {{ $geld($r->betragCent()) }} · {{ $r->kennung() }}</span>
                <span class="hinweis">{{ $r->statusText() }}</span>
            </a>
        @empty
            <p class="hinweis">{{ $suche ? 'Keine passende offene Reservierung.' : 'Keine offenen Reservierungen.' }}</p>
        @endforelse
    </section>

    @if ($freikarten->isNotEmpty())
        <section class="karte">
            <h2>Hinterlegte Freikarten</h2>
            @foreach ($freikarten as $a)
                @php $offen = $a->tickets->whereIn('status', ['erzeugt', 'gedruckt'])->count(); @endphp
                <form method="post" action="{{ route('tickets.abendkasse.freikarten', [$v, $a]) }}" class="formular formular--zeile" style="margin:0.5rem 0" onsubmit="return confirm('Freikarten ausgeben? Nicht ausgegebene werden storniert.')">
                    @csrf
                    <span style="flex:1"><strong>{{ $a->name }}</strong> – {{ $offen }} Freikarten</span>
                    <input name="anzahl" type="number" min="1" max="{{ $offen }}" value="{{ $offen }}" style="flex:0 0 5rem" aria-label="Anzahl">
                    <button type="submit" class="knopf knopf--zweit">Ausgeben</button>
                </form>
            @endforeach
        </section>
    @endif

    <section class="karte">
        <h2>Gedruckte Karte aus dem Abendkassen-Bestand verkaufen</h2>
        @include('ticket::teile.scanner', ['aktion' => route('tickets.abendkasse.bestand.scan', $v), 'json' => false, 'knopf' => 'Weiter'])
    </section>

    <section class="karte">
        <h2>Nicht abgeholte Reservierungen</h2>
        @if (now()->lt($nichtAbgeholtAb))
            <p class="hinweis">Erscheinen ab {{ $nichtAbgeholtAb->format('H:i') }} Uhr.</p>
        @elseif ($nichtAbgeholt->isEmpty())
            <p class="hinweis">Alle Reservierungen sind abgeholt.</p>
        @else
            <form method="post" action="{{ route('tickets.abendkasse.nicht_abgeholt', $v) }}" onsubmit="return confirm('Ausgewählte Reservierungen stornieren? Die Plätze werden frei.')">
                @csrf
                @foreach ($nichtAbgeholt as $r)
                    <label class="zusatz" style="display:block;margin:0.35rem 0"><input type="checkbox" name="ids[]" value="{{ $r->id }}" checked> {{ $r->name }} – {{ $r->anzahl() }} Karten ({{ $r->kennung() }}{{ $r->art === 'vorkasse' ? ', Vorkasse unbezahlt' : '' }})</label>
                @endforeach
                <p class="hinweis">Wer noch kommen soll: Haken entfernen.</p>
                <button type="submit" class="knopf">Ausgewählte stornieren</button>
            </form>
        @endif
    </section>
@endsection
