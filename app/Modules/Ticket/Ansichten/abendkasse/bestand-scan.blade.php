@extends('layouts.basis')

@section('titel', 'Gedruckte Karte verkaufen')

@section('inhalt')
    <section class="karte karte--schmal">
        @include('ticket::abendkasse.kopf', ['titel' => 'Gedruckte Karte verkaufen'])
        <p>Karte aus dem Bestand der Abendkasse scannen oder die Nummer eintippen. Danach Zahlungsart wählen.</p>
        @include('ticket::teile.scanner', ['aktion' => route('tickets.abendkasse.bestand.scan', $v), 'json' => false, 'knopf' => 'Weiter'])
    </section>
@endsection
