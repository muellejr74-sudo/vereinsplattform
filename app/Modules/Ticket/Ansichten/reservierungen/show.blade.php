@extends('layouts.basis')

@section('titel', 'Reservierung '.$r->kennung())

@php $geld = fn ($c) => \App\Modules\Ticket\Dienste\Geld::anzeigen($c); @endphp

@section('inhalt')
    <div class="titelzeile">
        <h1>Reservierung {{ $r->kennung() }}</h1>
        <a class="knopf knopf--zweit" href="{{ route('tickets.reservierungen.index') }}">Alle Reservierungen</a>
    </div>
    @include('kern::teile.fehler')

    <section class="karte">
        <dl class="angaben">
            <dt>Status</dt><dd><strong>{{ $r->statusText() }}</strong></dd>
            <dt>Name</dt><dd>{{ $r->name }}</dd>
            @if ($r->telefon)<dt>Telefon</dt><dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $r->telefon) }}">{{ $r->telefon }}</a></dd>@endif
            <dt>Vorstellung</dt><dd>{{ $r->vorstellung->veranstaltung->name }}, {{ $r->vorstellung->anzeige() }}</dd>
            <dt>Art</dt><dd>{{ ucfirst(\App\Modules\Ticket\Models\Reservierung::ARTEN[$r->art]) }}</dd>
            @if ($r->art === 'vorkasse' || $r->strasse)<dt>Anschrift</dt><dd>{!! collect($r->anschrift())->map(fn ($z) => e($z))->join('<br>') !!}</dd>@endif
            <dt>Angenommen</dt><dd>{{ $r->created_at->format('d.m.Y H:i') }} · {{ $r->stelleText() }} · {{ $r->angelegtVon?->name ?? '–' }}</dd>
            @if ($r->bemerkung)<dt>Bemerkung</dt><dd>{{ $r->bemerkung }}</dd>@endif
            @if ($r->zahlungsart)<dt>Bezahlt</dt><dd>{{ \App\Modules\Ticket\Models\Reservierung::ZAHLUNGSARTEN[$r->zahlungsart] }}@if ($r->bezahlt_am) am {{ $r->bezahlt_am->format('d.m.Y') }}@endif</dd>@endif
        </dl>
        <table class="tabelle tabelle--klein" style="margin-top:0.75rem">
            <thead><tr><th>Kategorie</th><th>Anzahl</th><th>Preis</th><th>Summe</th></tr></thead>
            <tbody>
            @foreach ($r->positionen as $p)
                <tr><td data-titel="Kategorie">{{ $p->kategorie->name }}</td><td data-titel="Anzahl">{{ $p->anzahl }}@if ($p->anzahl_abgeholt !== null && $p->anzahl_abgeholt !== $p->anzahl) <span class="hinweis">(abgeholt: {{ $p->anzahl_abgeholt }})</span>@endif</td><td data-titel="Preis">{{ $geld($p->preis_cent) }}</td><td data-titel="Summe">{{ $geld($p->anzahl * $p->preis_cent) }}</td></tr>
            @endforeach
            <tr><td colspan="3"><strong>Gesamt</strong></td><td><strong>{{ $geld($r->betragCent()) }}</strong></td></tr>
            </tbody>
        </table>
    </section>

    @if ($r->art === 'vorkasse' && $r->status === 'offen')
        <section class="karte karte--wichtig">
            <h2>Zum Durchsagen</h2>
            <dl class="angaben">
                <dt>Betrag</dt><dd class="summe">{{ $geld($r->betragCent()) }}</dd>
                <dt>Verwendungszweck</dt><dd><strong>{{ $r->verwendungszweck() }}</strong></dd>
                @if ($einstellungen->iban)
                    <dt>Empfänger</dt><dd>{{ $einstellungen->kontoinhaber ?: '–' }}</dd>
                    <dt>IBAN</dt><dd><code>{{ $einstellungen->ibanAnzeige() }}</code></dd>
                @else
                    <dt>IBAN</dt><dd class="hinweis">Noch keine Bankverbindung hinterlegt (Tickets → Einstellungen).</dd>
                @endif
                <dt>Zahlung bis</dt><dd>{{ $r->zahlungsfrist?->format('d.m.Y') }}@if ($r->istUeberfaellig()) <span class="status status--storniert">überfällig</span>@endif</dd>
            </dl>
            @if ($postKnapp)
                <div class="meldung meldung--hinweis">Die Vorstellung ist schon bald – für den Postversand wird es knapp. Empfehlung: auf <strong>Abholung an der Abendkasse</strong> umstellen.</div>
            @endif
            <div class="knopfzeile" style="margin-top:0.75rem">
                @if ($kasse)
                    <form method="post" action="{{ route('tickets.reservierungen.bezahlt', $r) }}" onsubmit="return confirm('Ist {{ $geld($r->betragCent()) }} auf dem Konto eingegangen? Die Karten werden dann gedruckt und verschickt.')">
                        @csrf <button type="submit" class="knopf">Bezahlt, zum Versand freigeben</button>
                    </form>
                @endif
                @if ($darfBearbeiten)
                    <form method="post" action="{{ route('tickets.reservierungen.abholung', $r) }}">@csrf <button type="submit" class="knopf knopf--zweit">Auf Abholung an der Abendkasse umstellen</button></form>
                @endif
            </div>
            @unless ($kasse)<p class="hinweis">Den Geldeingang vermerkt, wer das Recht „Zahlungseingang vermerken“ hat.</p>@endunless
        </section>
    @endif

    @if ($r->kartenauftrag && in_array($r->status, ['offen', 'bezahlt'], true))
        <section class="karte">
            <p>Druckauftrag: <a href="{{ route('tickets.auftraege.show', $r->kartenauftrag) }}">Auftrag #{{ $r->kartenauftrag->id }}</a> – {{ $r->kartenauftrag->statusText() }}</p>
        </section>
    @endif

    @if ($r->tickets->isNotEmpty())
        <section class="karte">
            <h2>Karten ({{ $r->tickets->count() }})</h2>
            <table class="tabelle tabelle--klein">
                <thead><tr><th>Nummer</th><th>Kategorie</th><th>Status</th></tr></thead>
                <tbody>
                @foreach ($r->tickets as $t)
                    <tr><td data-titel="Nummer"><code>{{ $t->nummer }}</code> <span class="hinweis">{{ $t->stapelText() }}</span></td><td data-titel="Kategorie">{{ $t->kategorie->name }}</td><td data-titel="Status">{{ $t->statusText() }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif

    @if ($r->status === 'offen' && $darfBearbeiten)
        <form method="post" action="{{ route('tickets.reservierungen.stornieren', $r) }}" onsubmit="return confirm('Reservierung {{ $r->kennung() }} stornieren? Die Plätze werden wieder frei.')" style="margin-top:1rem">
            @csrf <button type="submit" class="link-knopf">Reservierung stornieren</button>
        </form>
    @endif
@endsection
