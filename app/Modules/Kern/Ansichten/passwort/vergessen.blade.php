@extends('layouts.basis')

@section('titel', 'Passwort vergessen')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>Passwort vergessen</h1>
        <p>Geben Sie Ihre E-Mail-Adresse ein. Sie erhalten dann einen Link, mit dem Sie ein neues Passwort festlegen können.</p>
        @include('kern::teile.fehler')

        <form method="post" action="{{ route('passwort.vergessen') }}" class="formular">
            @csrf
            <label for="email">E-Mail-Adresse</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            <button type="submit" class="knopf">Link anfordern</button>
        </form>

        <p class="hinweis"><a href="{{ route('anmeldung') }}">Zurück zur Anmeldung</a></p>
    </section>
@endsection
