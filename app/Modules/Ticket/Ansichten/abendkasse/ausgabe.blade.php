@extends('layouts.basis')

@section('titel', 'Reservierung ausgeben')

@php $geld = fn ($c) => \App\Modules\Ticket\Dienste\Geld::anzeigen($c); @endphp

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>{{ $r->name }}</h1>
        <p class="hinweis">{{ $r->kennung() }} · {{ $v->veranstaltung->name }}, {{ $v->anzeige() }} · {{ $r->statusText() }}</p>
        @if ($r->bemerkung)<div class="meldung meldung--hinweis">{{ $r->bemerkung }}</div>@endif
        @include('kern::teile.fehler')
        @if ($r->art === 'vorkasse')
            <div class="meldung meldung--hinweis">Vorkasse – laut Plattform ist <strong>noch keine Überweisung</strong> eingegangen. Bitte jetzt kassieren.</div>
        @endif

        <form method="post" action="{{ route('tickets.abendkasse.reservierung', [$v, $r]) }}" class="formular"
              oninput="var s = 0; this.querySelectorAll('[data-preis]').forEach(function (e) { s += (parseInt(e.value, 10) || 0) * parseInt(e.dataset.preis, 10); }); document.getElementById('summe').textContent = (s / 100).toFixed(2).replace('.', ',') + ' €';">
            @csrf
            @foreach ($r->positionen as $p)
                <div class="formular--zeile" style="margin:0.25rem 0">
                    <label for="p{{ $p->id }}" style="flex:1">{{ $p->kategorie->name }} <span class="hinweis">{{ $geld($p->preis_cent) }} · reserviert: {{ $p->anzahl }}</span></label>
                    <input id="p{{ $p->id }}" name="mengen[{{ $p->id }}]" type="number" min="0" max="{{ $p->anzahl }}" value="{{ $p->anzahl }}" data-preis="{{ $p->preis_cent }}" style="flex:0 0 6rem" inputmode="numeric">
                </div>
            @endforeach
            <p>Zu zahlen: <span class="summe" id="summe">{{ $geld($r->betragCent()) }}</span></p>
            <small class="hinweis">Kommen weniger Personen, Anzahl verringern – die übrigen Plätze werden storniert und frei.
                @if ($r->art === 'gedruckt') Die hinterlegten Karten mitgeben. @else Ohne Etikett – die Gäste gelten sofort als eingelassen. @endif</small>
            <div class="knopfzeile knopfzeile--voll" style="margin-top:0.75rem">
                <button type="submit" name="zahlungsart" value="bar" class="knopf knopf--gross">Bar bezahlt</button>
                <button type="submit" name="zahlungsart" value="karte" class="knopf knopf--gross knopf--zweit">Mit Karte bezahlt</button>
            </div>
        </form>
        <p style="margin-top:1rem"><a href="{{ route('tickets.abendkasse.reservierungen', $v) }}">Andere Reservierung</a> · <a href="{{ route('tickets.abendkasse.show', $v) }}">Menü</a></p>
    </section>
@endsection
