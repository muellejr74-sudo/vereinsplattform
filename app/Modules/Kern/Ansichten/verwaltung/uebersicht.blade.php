@extends('layouts.basis')

@section('titel', 'Verwaltung')

@section('inhalt')
    <h1>Plattformverwaltung</h1>
    <div class="kacheln">
        <a class="kachel" href="{{ route('verwaltung.vereine.index') }}"><strong>Vereine</strong><span>anlegen, bearbeiten, deaktivieren, Vereinsimpressum</span></a>
        <a class="kachel" href="{{ route('verwaltung.benutzer.index') }}"><strong>Benutzer</strong><span>anlegen, einladen, Vereinen zuordnen</span></a>
        <a class="kachel" href="{{ route('verwaltung.tische') }}"><strong>Betreiber-Tische</strong><span>eigene Tische mit QR-Code, Aufkleber drucken</span></a>
        <a class="kachel" href="{{ route('verwaltung.protokoll') }}"><strong>Protokoll</strong><span>wer hat wann was getan</span></a>
        <a class="kachel" href="{{ route('verwaltung.rechtstexte') }}"><strong>Rechtstexte</strong><span>Impressum und Datenschutzerklärung der Plattform</span></a>
    </div>
@endsection
