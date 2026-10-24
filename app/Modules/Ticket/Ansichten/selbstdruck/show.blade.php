@extends('layouts.basis')

@section('titel', 'Karten drucken')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>{{ $auftrag->tickets->count() }} {{ $auftrag->tickets->count() === 1 ? 'Karte' : 'Karten' }} erzeugt</h1>
        <p class="hinweis">{{ $auftrag->vorstellung->veranstaltung->name }}, {{ $auftrag->vorstellung->anzeige() }} · {{ $auftrag->verkaufsstelle->name }}</p>
        @include('kern::teile.fehler')
        <a class="knopf knopf--gross" href="{{ route('tickets.selbstdruck.etiketten', $auftrag) }}" target="_blank" style="display:block;text-align:center">Etiketten jetzt drucken</a>
        <p class="hinweis">Etikett 101,6 × 38,1 mm, Skalierung „Tatsächliche Größe“ / 100 %. Ist ein Etikett misslungen: unten „Nachdruck“ – gleiche Nummer.</p>
        <p class="summe">Zu kassieren: {{ \App\Modules\Ticket\Dienste\Geld::anzeigen((int) $auftrag->tickets->sum('preis_cent')) }}</p>

        <table class="tabelle tabelle--klein">
            <thead><tr><th>Nummer</th><th>Kategorie</th><th></th></tr></thead>
            <tbody>
            @foreach ($auftrag->tickets as $t)
                <tr>
                    <td data-titel="Nummer"><code>{{ $t->nummer }}</code></td>
                    <td data-titel="Kategorie">{{ $t->kategorie->name }} · {{ \App\Modules\Ticket\Dienste\Geld::anzeigen($t->preis_cent) }}</td>
                    <td class="aktionen">
                        @if (in_array($t->status, ['ausgegeben', 'verkauft'], true))
                            <form method="post" action="{{ route('tickets.selbstdruck.nachdruck', $t) }}" target="_blank" class="inline" onsubmit="return confirm('Karte {{ $t->nummer }} mit derselben Nummer nachdrucken?')">
                                @csrf <button type="submit" class="link-knopf">Nachdruck</button>
                            </form>
                        @else
                            {{ $t->statusText() }}
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="knopfzeile" style="margin-top:1rem">
            <button type="button" class="knopf" onclick="window.close(); setTimeout(function () { location.href = '{{ route('tickets.selbstdruck.create', ['vorstellung' => $auftrag->vorstellung_id]) }}'; }, 300);">Fertig – Tab schließen</button>
        </div>
    </section>
@endsection
