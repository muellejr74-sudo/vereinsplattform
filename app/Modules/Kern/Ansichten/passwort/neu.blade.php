@extends('layouts.basis')

@section('titel', 'Passwort festlegen')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>{{ $titel }}</h1>
        @include('kern::teile.fehler')

        <form method="post" action="{{ $aktion }}" class="formular">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <label for="email">E-Mail-Adresse</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="username" required>

            <label for="password">Neues Passwort</label>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required>
            <small class="hinweis">Mindestens 10 Zeichen. Tipp: ein Satz aus mehreren Wörtern ist sicher und gut zu merken.</small>

            <label for="password_confirmation">Neues Passwort wiederholen</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>

            <button type="submit" class="knopf">Passwort speichern</button>
        </form>
    </section>
@endsection
