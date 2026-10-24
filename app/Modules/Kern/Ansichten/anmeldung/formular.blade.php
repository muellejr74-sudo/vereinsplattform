@extends('layouts.basis')

@section('titel', 'Anmelden')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>Anmelden</h1>
        @include('kern::teile.fehler')

        <form method="post" action="{{ route('anmeldung') }}" class="formular">
            @csrf
            <label for="email">E-Mail-Adresse</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>

            <label for="password">Passwort</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>

            <button type="submit" class="knopf">Anmelden</button>
        </form>

        <p class="hinweis"><a href="{{ route('passwort.vergessen') }}">Passwort vergessen?</a></p>
    </section>
@endsection
