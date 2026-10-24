@extends('layouts.basis')

@section('titel', 'Verein wählen')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>Für welchen Verein?</h1>
        <p>Um diesen Tisch zuzuordnen, wählen Sie bitte zuerst den Verein aus, für den Sie arbeiten. Scannen Sie den Code danach einfach noch einmal.</p>
        <p><a class="knopf" href="{{ route('startseite') }}">Verein auswählen</a></p>
        <p class="hinweis"><a href="{{ route('scan', ['code' => $code, 'gast' => 1]) }}">Gästeansicht anzeigen</a></p>
    </section>
@endsection
