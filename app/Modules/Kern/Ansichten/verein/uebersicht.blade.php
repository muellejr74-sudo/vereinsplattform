@extends('layouts.basis')

@section('titel', 'Verein verwalten')

@section('inhalt')
    <h1>{{ $verein->name }} verwalten</h1>
    <div class="kacheln">
        @can('veranstaltungen.verwalten')
            <a class="kachel" href="{{ route('verein.veranstaltungen.index') }}"><strong>Veranstaltungen</strong><span>Veranstaltungen, Vorstellungen, Personal &amp; Einsatz-Code</span></a>
        @endcan
        @can('qrcodes.verwalten')
            <a class="kachel" href="{{ route('verein.tische') }}"><strong>QR-Codes &amp; Tische</strong><span>Codes erzeugen, Aufkleber drucken, Tische zuordnen</span></a>
        @endcan
        @can('stationen.verwalten')
            <a class="kachel" href="{{ route('verein.stationen') }}"><strong>Stationen</strong><span>Bedienung, Ausschank, Küche, Einlass</span></a>
        @endcan
        @can('orte.verwalten')
            <a class="kachel" href="{{ route('verein.orte') }}"><strong>Orte &amp; Säle</strong><span>nur nötig bei mehreren Sälen</span></a>
        @endcan
        @if (auth()->user()->can('benutzer.verwalten') || auth()->user()->can('rechte.vergeben'))
            <a class="kachel" href="{{ route('verein.personen.index') }}"><strong>Personen &amp; Rechte</strong><span>Personen hinzufügen, entfernen, Rechte vergeben</span></a>
        @endif
        @can('verein.impressum')
            <a class="kachel" href="{{ route('verein.rechtstexte') }}"><strong>Vereinsimpressum</strong><span>Impressum und Datenschutz-Ergänzung des Vereins</span></a>
        @endcan
        @can('protokoll.ansehen')
            <a class="kachel" href="{{ route('verein.protokoll') }}"><strong>Protokoll</strong><span>Vorgänge in diesem Verein</span></a>
        @endcan
    </div>

    <section class="karte" style="margin-top: 1rem">
        <h2>Freigeschaltete Module</h2>
        @if ($module)
            <p>{{ implode(' · ', $module) }}</p>
        @else
            <p class="hinweis">Noch keine Module freigeschaltet.</p>
        @endif
        <p class="hinweis">Module, Logo und Vereinsfarben legt der Plattformbetreiber fest.</p>
    </section>
@endsection
