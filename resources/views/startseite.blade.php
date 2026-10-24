@extends('layouts.basis')

@section('titel', 'Willkommen')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>Willkommen bei der {{ config('app.name') }}</h1>
        <p>Hier organisieren Vereine ihre Veranstaltungen: Eintrittskarten, Bestellungen im Saal und das digitale Gästeportal.</p>
        <p><a class="knopf" href="{{ route('anmeldung') }}">Anmelden</a></p>
    </section>
@endsection
