@extends('layouts.basis')

@section('titel', 'Karten verkaufen')

@php $geld = fn ($c) => \App\Modules\Ticket\Dienste\Geld::anzeigen($c); @endphp

@section('inhalt')
    @include('ticket::teile.mengen')
    <section class="karte karte--schmal">
        @include('ticket::abendkasse.kopf', ['titel' => 'Karten verkaufen'])
        <p class="zaehler">Frei: <strong>{{ $zahlen['frei'] ?? '–' }}</strong></p>
        @if ($kategorien->isEmpty())
            <p>Für diese Veranstaltung sind keine Kategorien angelegt.</p>
        @else
            <form method="post" action="{{ route('tickets.abendkasse.verkauf', $v) }}" class="formular" id="verkauf"
                  oninput="var s = 0; this.querySelectorAll('[data-preis]').forEach(function (e) { s += (parseInt(e.value, 10) || 0) * parseInt(e.dataset.preis, 10); }); document.getElementById('verkauf-summe').textContent = (s / 100).toFixed(2).replace('.', ',') + ' €';">
                @csrf
                @foreach ($kategorien as $k)
                    <div class="mengen-zeile">
                        <label for="vk{{ $k->id }}" >{{ $k->name }} <span class="hinweis">{{ $geld($k->preisFuer($v->id)) }}</span></label>
                        <input data-menge id="vk{{ $k->id }}" name="mengen[{{ $k->id }}]" type="number" min="0" max="200" value="{{ old('mengen.'.$k->id, 0) }}" data-preis="{{ $k->preisFuer($v->id) }}" inputmode="numeric">
                    </div>
                @endforeach
                <p>Zu zahlen: <span class="summe" id="verkauf-summe">0,00 €</span></p>
                <div class="knopfzeile knopfzeile--voll">
                    <button type="submit" name="zahlungsart" value="bar" class="knopf knopf--gross">Bar bezahlt</button>
                    <button type="submit" name="zahlungsart" value="karte" class="knopf knopf--gross knopf--zweit">Mit Karte bezahlt</button>
                </div>
                <small class="hinweis">Ohne Etikett – die Gäste gelten sofort als eingelassen. Danach geht es zurück ins Menü.</small>
            </form>
        @endif
    </section>
@endsection
