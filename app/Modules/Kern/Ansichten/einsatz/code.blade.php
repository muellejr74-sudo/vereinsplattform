@extends('layouts.basis')

@section('titel', 'Einsatz')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>Anmeldung für das Personal</h1>
        <p>Bitte den <strong>Einsatz-Code</strong> eingeben, den Sie vom Verein für heute bekommen haben.</p>
        @include('kern::teile.fehler')
        <form method="post" action="{{ route('einsatz') }}" class="formular">
            @csrf
            <label for="code">Einsatz-Code</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="off" maxlength="12" required autofocus class="eingabe--code" placeholder="123 456">
            <button type="submit" class="knopf">Weiter</button>
        </form>
        <p class="hinweis">Mitarbeitende mit eigenem Konto (z. B. Abendkasse) melden sich stattdessen <a href="{{ route('anmeldung') }}">hier</a> an.</p>
    </section>
@endsection
