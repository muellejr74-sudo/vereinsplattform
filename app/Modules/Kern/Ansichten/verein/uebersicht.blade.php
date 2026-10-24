@extends('layouts.basis')

@section('titel', 'Verein verwalten')

@section('inhalt')
    <h1>{{ $verein->name }} verwalten</h1>
    <div class="kacheln">
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
