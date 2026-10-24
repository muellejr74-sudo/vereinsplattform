@extends('layouts.basis')

@section('titel', 'Kassieren')

@section('inhalt')
    <section class="karte karte--schmal">
        @if (! $auftrag)
            @if ($versuch < 8)
                <h1>Einen Moment …</h1>
                <p>Die Karten werden erzeugt.</p>
                <script>setTimeout(function () { location.href = '{{ route('tickets.selbstdruck.kassieren', ['nach' => $nach, 'vorstellung' => $vorstellung, 'versuch' => $versuch + 1]) }}'; }, 1000);</script>
            @else
                <h1>Kein neuer Verkauf</h1>
                <p>Es wurden keine Karten erzeugt – die Meldung steht im anderen Tab (z. B. „nicht genug Plätze frei“).</p>
                <a class="knopf" href="{{ route('tickets.selbstdruck.create', ['vorstellung' => $vorstellung]) }}">Zurück zum Kartenverkauf</a>
            @endif
        @else
            @php $betrag = (int) $auftrag->tickets->sum('preis_cent'); @endphp
            <h1>Kassieren</h1>
            <p class="hinweis">{{ $auftrag->tickets->count() }} {{ $auftrag->tickets->count() === 1 ? 'Karte' : 'Karten' }} · {{ $auftrag->vorstellung->anzeige() }} · die Etiketten sind im anderen Tab ·
                <a href="{{ route('tickets.selbstdruck.show', $auftrag) }}" target="_blank">Karten anzeigen / Nachdruck</a></p>
            <p class="kasse-betrag">Zu zahlen <strong>{{ \App\Modules\Ticket\Dienste\Geld::anzeigen($betrag) }}</strong></p>

            @include('ticket::teile.rueckgeld', ['betrag' => $betrag])
            <a class="knopf knopf--gross" href="{{ route('tickets.selbstdruck.create', ['vorstellung' => $auftrag->vorstellung_id]) }}" style="display:block;text-align:center;margin-top:1rem">Abschließen – nächster Verkauf</a>
            <p class="hinweis">Das Rückgeld wird nur angezeigt, nicht gespeichert.</p>

        @endif
    </section>
@endsection
