@extends('layouts.basis')

@section('titel', 'Tisch zugeordnet')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>✔ Gespeichert</h1>
        <p>„{{ $veranstaltung->name }}“ hat jetzt <strong>{{ $anzahl }}</strong> Tische.</p>
        <p><strong>Nächster Tisch:</strong> einfach den nächsten Aufkleber mit der Handy-Kamera scannen.</p>
        <p><a href="{{ route('verein.veranstaltungen.show', $veranstaltung) }}">Alle Tische dieser Veranstaltung ansehen</a></p>
    </section>
@endsection
