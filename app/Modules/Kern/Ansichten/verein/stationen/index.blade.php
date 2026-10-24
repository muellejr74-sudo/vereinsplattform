@extends('layouts.basis')

@section('titel', 'Stationen')

@php $arten = \App\Modules\Kern\Models\Station::ARTEN; @endphp

@section('inhalt')
    @include('kern::teile.sortierbar')
    <h1>Stationen</h1>
    <p class="hinweis">Stationen sind die Plätze des Abendpersonals (ohne eigenes Konto). Welche Person an welchem Abend an welcher Station ist, tragen Sie je Vorstellung unter „Personal &amp; Einsatz-Code“ ein.</p>
    @include('kern::teile.fehler')

    <details class="karte" style="margin-bottom:1rem" @if ($errors->any() && old('_neu')) open @endif>
        <summary class="knopf" style="display:inline-block">+ Station hinzufügen</summary>
        <form method="post" action="{{ route('verein.stationen.neu') }}" class="formular" style="margin-top:0.75rem">
            @csrf <input type="hidden" name="_neu" value="1">
            <label for="neu_name">Name</label><input id="neu_name" name="name" maxlength="60" required placeholder="z. B. Bedienung 6" value="{{ old('name') }}">
            <label for="neu_art">Art</label>
            <select id="neu_art" name="art">@foreach ($arten as $k => $b)<option value="{{ $k }}">{{ $b }}</option>@endforeach</select>
            <label class="auswahl"><input type="checkbox" name="mehrere_geraete" value="1"> mehrere Geräte gleichzeitig</label>
            <button type="submit" class="knopf">Hinzufügen</button>
        </form>
    </details>

    <p class="hinweis">Reihenfolge ändern: am Griff ☰ ziehen oder mit ▲ ▼. Zum Bearbeiten auf die Station tippen.</p>
    <ul class="sortier-liste" data-sortierbar data-url="{{ route('verein.stationen.reihenfolge') }}" data-token="{{ csrf_token() }}">
        @foreach ($stationen as $station)
            <li data-id="{{ $station->id }}">
                <details>
                    <summary>
                        <span class="griff" title="Zum Verschieben ziehen" aria-hidden="true">☰</span>
                        <span class="sortier-name">{{ $station->name }}<span class="marke">{{ $arten[$station->art] ?? $station->art }}</span>@if ($station->mehrere_geraete)<span class="marke">mehrere Geräte</span>@endif</span>
                        <span class="sortier-knoepfe">
                            <button type="button" data-schieben="hoch" aria-label="{{ $station->name }} nach oben">▲</button>
                            <button type="button" data-schieben="runter" aria-label="{{ $station->name }} nach unten">▼</button>
                        </span>
                        <span class="sortier-bearbeiten-knopf">✎</span>
                    </summary>
                    <form method="post" action="{{ route('verein.stationen.speichern', $station) }}" class="formular">
                        @csrf @method('put')
                        <label for="n{{ $station->id }}">Name</label><input id="n{{ $station->id }}" name="name" value="{{ $station->name }}" maxlength="60" required>
                        <label for="a{{ $station->id }}">Art</label>
                        <select id="a{{ $station->id }}" name="art">@foreach ($arten as $k => $b)<option value="{{ $k }}" @selected($station->art === $k)>{{ $b }}</option>@endforeach</select>
                        <label class="auswahl"><input type="checkbox" name="mehrere_geraete" value="1" @checked($station->mehrere_geraete)> mehrere Geräte gleichzeitig</label>
                        <div class="knopfzeile">
                            <button type="submit" class="knopf">Speichern</button>
                            <button type="submit" form="loeschen{{ $station->id }}" class="link-knopf">Löschen</button>
                        </div>
                    </form>
                    <form id="loeschen{{ $station->id }}" method="post" action="{{ route('verein.stationen.loeschen', $station) }}" onsubmit="return confirm('Station „{{ $station->name }}“ löschen?')">@csrf @method('delete')</form>
                </details>
            </li>
        @endforeach
    </ul>
    <p class="hinweis" style="margin-top:1rem">Bedienungen: ein Gerät pro Station. Ausschank, Küche und Einlass dürfen mehrere Geräte gleichzeitig nutzen.</p>
@endsection
