@extends('layouts.basis')

@section('titel', 'Mein Konto')

@section('inhalt')
    @include('kern::teile.fehler')

    @if ($neueCodes)
        <section class="karte karte--wichtig">
            <h2>Ihre Notfall-Codes</h2>
            <p><strong>Diese Codes werden nur jetzt angezeigt.</strong> Bitte ausdrucken oder an einem sicheren Ort aufbewahren (nicht auf dem Handy mit der Authenticator-App).
               Mit jedem Code können Sie sich <strong>einmal</strong> ohne Handy anmelden.</p>
            <ol class="codes">
                @foreach ($neueCodes as $code)
                    <li><code>{{ $code }}</code></li>
                @endforeach
            </ol>
            <p><button type="button" class="knopf knopf--zweit" onclick="window.print()">Drucken</button></p>
        </section>
    @endif

    <section class="karte">
        <h1>Mein Konto</h1>
        <dl class="angaben">
            <dt>Name</dt><dd>{{ $benutzer->name }}</dd>
            <dt>E-Mail-Adresse</dt><dd>{{ $benutzer->email }}</dd>
            <dt>Vereine</dt><dd>{{ $benutzer->istSuperAdmin() ? 'alle (Super-Administrator)' : ($benutzer->vereine->pluck('name')->join(', ') ?: '–') }}</dd>
        </dl>
    </section>

    <section class="karte">
        <h2>Passwort ändern</h2>
        <form method="post" action="{{ route('konto.passwort') }}" class="formular">
            @csrf @method('put')
            <label for="aktuelles_passwort">Aktuelles Passwort</label>
            <input id="aktuelles_passwort" name="aktuelles_passwort" type="password" autocomplete="current-password" required>
            <label for="password">Neues Passwort</label>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required>
            <small class="hinweis">Mindestens 10 Zeichen.</small>
            <label for="password_confirmation">Neues Passwort wiederholen</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            <button type="submit" class="knopf">Passwort ändern</button>
        </form>
    </section>

    @if ($benutzer->istSuperAdmin())
        <section class="karte">
            <h2>Zwei-Faktor-Anmeldung</h2>
            @if ($benutzer->hatZweiFaktor())
                <p><span class="status status--fertig">eingerichtet</span> seit {{ $benutzer->zwei_faktor_aktiv_seit->format('d.m.Y') }}</p>
                <p>Verbleibende Notfall-Codes: <strong>{{ $notfallcodes }}</strong> von {{ \App\Modules\Kern\Dienste\ZweiFaktor::ANZAHL_NOTFALLCODES }}</p>

                <form method="post" action="{{ route('zweifaktor.notfallcodes') }}" class="formular formular--zeile">
                    @csrf
                    <label for="pw_codes">Passwort zur Bestätigung</label>
                    <input id="pw_codes" name="aktuelles_passwort" type="password" autocomplete="current-password" required>
                    <button type="submit" class="knopf knopf--zweit">Neue Notfall-Codes erzeugen</button>
                </form>

                <p>Gemerkte Geräte (Code nur etwa einmal im Monat): <strong>{{ $geraete }}</strong></p>
                <form method="post" action="{{ route('zweifaktor.geraete') }}">
                    @csrf
                    <button type="submit" class="knopf knopf--zweit">Alle gemerkten Geräte abmelden</button>
                </form>
            @else
                <p><a class="knopf" href="{{ route('zweifaktor.einrichten') }}">Jetzt einrichten</a></p>
            @endif
        </section>
    @endif
    {{-- Ergänzungen der Module (z. B. Ticketverwaltung: E-Mails an die Druckstelle) --}}
    @if (\Illuminate\Support\Facades\View::exists('ticket::konto') && auth()->user()->can('ticket.drucken'))
        @include('ticket::konto')
    @endif
@endsection
