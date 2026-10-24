@extends('layouts.basis')

@section('titel', 'Ticket-Einstellungen')

@section('inhalt')
    <h1>Ticket-Einstellungen</h1>
    @include('kern::teile.fehler')

    <section class="karte karte--schmal">
        <h2>Reservierungen</h2>
        <form method="post" action="{{ route('tickets.einstellungen.allgemein') }}" class="formular">
            @csrf @method('PUT')
            @cannot('ticket.reservierungen')<fieldset disabled>@endcannot
            <label for="reservierung_standard">Voreinstellung bei neuen Reservierungen</label>
            <select id="reservierung_standard" name="reservierung_standard">
                <option value="gedruckt" @selected($e->reservierung_standard === 'gedruckt')>gedruckt, an der Abendkasse hinterlegt</option>
                <option value="digital" @selected($e->reservierung_standard === 'digital')>nur digital, Karten entstehen bei Abholung</option>
            </select>
            <label for="nicht_abgeholt_minuten">Nicht abgeholte Reservierungen anzeigen ab … Minuten nach Beginn</label>
            <input id="nicht_abgeholt_minuten" name="nicht_abgeholt_minuten" type="number" min="-240" max="240" value="{{ $e->nicht_abgeholt_minuten }}" class="eingabe--kurz">
            <small class="hinweis">0 = ab Vorstellungsbeginn, -15 = 15 Minuten vor Beginn.</small>
            <label for="zahlungsfrist_tage">Zahlungsfrist bei Vorkasse (Tage)</label>
            <input id="zahlungsfrist_tage" name="zahlungsfrist_tage" type="number" min="1" max="60" value="{{ $e->zahlungsfrist_tage }}" class="eingabe--kurz">
            <button type="submit" class="knopf">Speichern</button>
            @cannot('ticket.reservierungen')</fieldset><p class="hinweis">Ändern mit dem Recht „Reservierungen“.</p>@endcannot
        </form>
    </section>

    <section class="karte karte--schmal">
        <h2>Bankverbindung für Vorkasse</h2>
        @can('ticket.zahlungseingang')
            <form method="post" action="{{ route('tickets.einstellungen.bank') }}" class="formular">
                @csrf @method('PUT')
                <label for="kontoinhaber">Kontoinhaber</label>
                <input id="kontoinhaber" name="kontoinhaber" maxlength="80" value="{{ old('kontoinhaber', $e->kontoinhaber) }}">
                <label for="iban">IBAN</label>
                <input id="iban" name="iban" maxlength="42" value="{{ old('iban', $e->ibanAnzeige()) }}" autocomplete="off" spellcheck="false">
                <small class="hinweis">Wird bei Vorkasse-Reservierungen zum Durchsagen angezeigt. Änderungen werden protokolliert.</small>
                <button type="submit" class="knopf">Speichern</button>
            </form>
        @else
            <p>{{ $e->kontoinhaber ?: '–' }}<br><code>{{ $e->ibanAnzeige() ?: 'nicht hinterlegt' }}</code></p>
            <p class="hinweis">Ändern mit dem Recht „Zahlungseingang vermerken“.</p>
        @endcan
    </section>
@endsection
