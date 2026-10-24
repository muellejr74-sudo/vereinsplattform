@extends('layouts.basis')

@section('titel', 'Bestätigungscode')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>Bestätigungscode</h1>
        <p>Bitte öffnen Sie Ihre Authenticator-App und geben Sie den 6-stelligen Code ein.</p>
        @include('kern::teile.fehler')

        <form method="post" action="{{ route('anmeldung.zweifaktor') }}" class="formular">
            @csrf
            <label for="code">Code aus der App</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required autofocus class="eingabe--code">

            <label class="auswahl">
                <input type="checkbox" name="geraet_merken" value="1">
                Diesem Gerät 30 Tage vertrauen (nur auf dem eigenen Gerät anhaken)
            </label>

            <button type="submit" class="knopf">Bestätigen</button>
        </form>

        <details class="hinweis">
            <summary>Handy nicht zur Hand?</summary>
            <p>Geben Sie statt des App-Codes einen Ihrer <strong>Notfall-Codes</strong> ein (Form XXXXX-XXXXX). Jeder Notfall-Code funktioniert nur einmal.</p>
        </details>
        <p class="hinweis"><a href="{{ route('anmeldung') }}">Zurück zur Anmeldung</a></p>
    </section>
@endsection
