@extends('layouts.basis')

@section('titel', 'Station wählen')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>Station wählen</h1>
        <p class="hinweis">{{ $vorstellung->veranstaltung->name }} · {{ $vorstellung->anzeige() }}</p>
        @include('kern::teile.fehler')
        <form method="post" action="{{ route('einsatz.station') }}" class="stationswahl">
            @csrf
            @foreach ($stationen as $eintrag)
                <button type="submit" name="station_id" value="{{ $eintrag['station']->id }}" class="station-knopf" @disabled($eintrag['belegt'])>
                    <strong>{{ $eintrag['station']->name }}</strong>
                    @if ($eintrag['name'])<span>{{ $eintrag['name'] }}</span>@endif
                    @if ($eintrag['belegt'])<span class="status status--storniert">belegt</span>@endif
                </button>
            @endforeach
        </form>
        <p class="hinweis">Dieses Handy bleibt bis zum Ende des Abends an die gewählte Station gebunden.</p>
    </section>
@endsection
