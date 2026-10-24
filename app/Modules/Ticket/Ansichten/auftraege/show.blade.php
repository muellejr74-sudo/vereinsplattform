@extends('layouts.basis')

@section('titel', 'Auftrag #'.$auftrag->id)

@section('inhalt')
    @include('ticket::teile.mengen')
    <div class="titelzeile">
        <h1>Auftrag #{{ $auftrag->id }}</h1>
        <a class="knopf knopf--zweit" href="{{ route('tickets.auftraege.index') }}">Alle Aufträge</a>
    </div>
    @include('kern::teile.fehler')

    <section class="karte">
        <dl class="angaben">
            <dt>Status</dt><dd><strong>{{ $auftrag->statusText() }}</strong></dd>
            <dt>Für</dt><dd>{{ $auftrag->stelleText() }}</dd>
            <dt>Art</dt><dd>{{ $auftrag->artText() }}@if ($auftrag->name) – Name: <strong>{{ $auftrag->name }}</strong>@endif</dd>
            @if ($auftrag->istReservierung() && $auftrag->reservierung)
                <dt>Reservierung</dt><dd><a href="{{ route('tickets.reservierungen.show', $auftrag->reservierung) }}">{{ $auftrag->reservierung->kennung() }}</a></dd>
                @if ($auftrag->art === 'versand')<dt>Versand an</dt><dd>{!! collect($auftrag->reservierung->anschrift())->map(fn ($z) => e($z))->join('<br>') !!}</dd>@endif
            @endif
            <dt>Vorstellung</dt><dd>{{ $auftrag->vorstellung->veranstaltung->name }}, {{ $auftrag->vorstellung->anzeige() }}</dd>
            <dt>Angefordert</dt><dd>{{ $auftrag->created_at->format('d.m.Y H:i') }} von {{ $auftrag->anfordernder?->name ?? '–' }}</dd>
            @if ($auftrag->bemerkung)<dt>Bemerkung</dt><dd>{{ $auftrag->bemerkung }}</dd>@endif
        </dl>
    </section>

    @if ($druckstelle && $auftrag->status === 'angefordert')
        <section class="karte">
            <h2>Bestätigen</h2>
            <p>Kontingent: {{ $zahlen['kontingent'] ?? 'nicht festgelegt' }} · belegt: {{ $zahlen['belegt'] }} · <strong>frei: {{ $zahlen['frei'] ?? '–' }}</strong></p>
            <form method="post" action="{{ route('tickets.auftraege.bestaetigen', $auftrag) }}" class="formular">
                @csrf
                @foreach ($auftrag->positionen as $p)
                    <div class="mengen-zeile">
                        <label for="b{{ $p->id }}" >{{ $p->kategorie->name }} <span class="hinweis">(angefordert: {{ $p->anzahl }})</span></label>
                        <input data-menge id="b{{ $p->id }}" name="mengen[{{ $p->id }}]" type="number" min="0" max="2000" value="{{ $p->anzahl }}">
                    </div>
                @endforeach
                <small class="hinweis">Menge bei Bedarf anpassen. Mit der Bestätigung werden die Ticketnummern vergeben und die Plätze belegt.</small>
                <button type="submit" class="knopf">Bestätigen und Karten erzeugen</button>
            </form>
            <form method="post" action="{{ route('tickets.auftraege.ablehnen', $auftrag) }}" onsubmit="return confirm('Auftrag ablehnen?')" style="margin-top:0.75rem">
                @csrf <button type="submit" class="link-knopf">Auftrag ablehnen</button>
            </form>
        </section>
    @elseif ($auftrag->status === 'angefordert')
        <section class="karte">
            <p>Der Auftrag wartet auf die Bestätigung durch die Druckstelle.</p>
            @foreach ($auftrag->positionen as $p)<p>{{ $p->anzahl }} × {{ $p->kategorie->name }}</p>@endforeach
            <form method="post" action="{{ route('tickets.auftraege.zurueckziehen', $auftrag) }}" onsubmit="return confirm('Auftrag zurückziehen?')">
                @csrf <button type="submit" class="knopf knopf--zweit">Zurückziehen</button>
            </form>
        </section>
    @endif

    @if ($druckstelle && $auftrag->status === 'bestaetigt')
        <section class="karte karte--wichtig">
            <h2>Etiketten drucken</h2>
            <ol class="schritte">
                <li><a class="knopf" href="{{ route('tickets.auftraege.etiketten', $auftrag) }}" target="_blank">Etiketten-PDF öffnen ({{ $auftrag->tickets->count() }} Karten{{ $auftrag->art === 'versand' ? ' + Adressaufkleber' : '' }})</a></li>
                <li>Auf dem Labeldrucker drucken: Etikett 101,6 × 38,1 mm, Skalierung „Tatsächliche Größe“ / 100 %.</li>
                <li>Wenn alle Etiketten sauber gedruckt sind:
                    <form method="post" action="{{ route('tickets.auftraege.gedruckt', $auftrag) }}" class="inline">@csrf <button type="submit" class="knopf knopf--zweit">Druck war erfolgreich</button></form></li>
            </ol>
        </section>
    @endif

    @if ($druckstelle && $auftrag->status === 'gedruckt' && $auftrag->art === 'reservierung')
        <section class="karte">
            <p>Die Karten bitte an der <strong>Abendkasse hinterlegen</strong>. Dort werden sie bei Abholung ausgegeben und bezahlt.</p>
        </section>
    @elseif ($druckstelle && $auftrag->status === 'gedruckt' && $auftrag->art === 'versand')
        <section class="karte karte--wichtig">
            <h2>Versand</h2>
            <p>Adressaufkleber und Karten in einen Umschlag geben und zur Post bringen. Danach:</p>
            <form method="post" action="{{ route('tickets.auftraege.uebergeben', $auftrag) }}" onsubmit="return confirm('Als versendet vermerken?')">
                @csrf <button type="submit" class="knopf">Versendet</button>
            </form>
        </section>
    @elseif ($druckstelle && $auftrag->status === 'gedruckt')
        <section class="karte karte--wichtig">
            <h2>Übergabe</h2>
            <p>Die Karten liegen zur Abholung bereit. Bei Übergabe an {{ $auftrag->stelleText() }}:</p>
            <form method="post" action="{{ route('tickets.auftraege.uebergeben', $auftrag) }}" onsubmit="return confirm('Karten jetzt als übergeben vermerken?')">
                @csrf <button type="submit" class="knopf">Karten übergeben</button>
            </form>
        </section>
    @endif

    @if ($auftrag->tickets->isNotEmpty())
        <section class="karte">
            <h2>Karten ({{ $auftrag->tickets->count() }})</h2>
            <div class="tabelle-rahmen">
                <table class="tabelle tabelle--klein">
                    <thead><tr><th>Nummer</th><th>Kategorie</th><th>Preis</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($auftrag->tickets as $t)
                        <tr>
                            <td data-titel="Nummer"><code>{{ $t->nummer }}</code>@if ($t->stapelText()) <span class="hinweis">{{ $t->stapelText() }}</span>@endif</td>
                            <td data-titel="Kategorie">{{ $t->kategorie->name }}</td>
                            <td data-titel="Preis">{{ \App\Modules\Ticket\Dienste\Geld::anzeigen($t->preis_cent) }}</td>
                            <td data-titel="Status">{{ \App\Modules\Ticket\Models\Ticket::STATUS[$t->status] ?? $t->status }}@if ($t->druckanzahl > 1) <span class="hinweis">({{ $t->druckanzahl }}× gedruckt)</span>@endif</td>
                            <td class="aktionen">
                                @if ($druckstelle && in_array($t->status, ['gedruckt', 'ausgegeben', 'verkauft']))
                                    <form method="post" action="{{ route('tickets.nachdruck', $t) }}" target="_blank" class="inline" onsubmit="return confirm('Karte {{ $t->nummer }} mit derselben Nummer nachdrucken?')">
                                        @csrf <button type="submit" class="link-knopf">Nachdruck</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
