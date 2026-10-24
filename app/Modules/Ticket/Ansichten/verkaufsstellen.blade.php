@extends('layouts.basis')

@section('titel', 'Verkaufsstellen')

@php $arten = \App\Modules\Ticket\Models\Verkaufsstelle::ARTEN; @endphp

@section('inhalt')
    <h1>Verkaufsstellen</h1>
    @include('kern::teile.fehler')

    <form method="get" class="formular formular--zeile" style="margin-bottom:1rem">
        <select name="stelle" aria-label="Verkaufsstelle wählen" onchange="this.form.submit()" style="flex:1 1 auto;min-width:0;max-width:100%;width:100%">
            <option value="">+ Neue Verkaufsstelle anlegen</option>
            @foreach ($stellen as $s)
                <option value="{{ $s->id }}" @selected($gewaehlt?->id === $s->id)>{{ $s->name }} – {{ $arten[$s->art] }}{{ $s->druckt_selbst ? ', druckt selbst' : '' }}{{ $s->aktiv ? '' : ' (inaktiv)' }} · {{ $s->benutzer->count() }} {{ $s->benutzer->count() === 1 ? 'Person' : 'Personen' }}</option>
            @endforeach
        </select>
        <noscript><button type="submit" class="knopf knopf--zweit">Öffnen</button></noscript>
    </form>

    <section class="karte">
        @if ($gewaehlt)
            <h2>{{ $gewaehlt->name }}</h2>
            <form method="post" action="{{ route('tickets.verkaufsstellen.speichern', $gewaehlt) }}" class="formular">
                @csrf @method('put')
                <div class="formular--zeile" style="margin:0">
                    <div class="feld"><label for="name">Name</label><input id="name" name="name" value="{{ old('name', $gewaehlt->name) }}" maxlength="80" required></div>
                    <div class="feld" style="flex:0 0 12rem"><label for="art">Art</label>
                        <select id="art" name="art">@foreach ($arten as $k => $b)<option value="{{ $k }}" @selected($gewaehlt->art === $k)>{{ $b }}</option>@endforeach</select></div>
                </div>
                <label class="auswahl"><input type="checkbox" name="aktiv" value="1" @checked($gewaehlt->aktiv)> aktiv</label>
                <label class="auswahl"><input type="checkbox" name="druckt_selbst" value="1" @checked($gewaehlt->druckt_selbst)> druckt selbst – Karten werden erst beim Verkauf an der Stelle gedruckt (eigener Etikettendrucker nötig)</label>
                @include('ticket::teile.mitglieder', ['ausgewaehlt' => $gewaehlt->benutzer->pluck('id')->all(), 'praefix' => 's'.$gewaehlt->id])
                <button type="submit" class="knopf">Speichern</button>
            </form>
        @else
            <h2>Neue Verkaufsstelle</h2>
            <form method="post" action="{{ route('tickets.verkaufsstellen.neu') }}" class="formular">
                @csrf
                <div class="formular--zeile" style="margin:0">
                    <div class="feld"><label for="neu_name">Name</label><input id="neu_name" name="name" maxlength="80" required value="{{ old('name') }}" placeholder="z. B. Bäckerei Müller"></div>
                    <div class="feld" style="flex:0 0 12rem"><label for="neu_art">Art</label>
                        <select id="neu_art" name="art">@foreach ($arten as $k => $b)<option value="{{ $k }}">{{ $b }}</option>@endforeach</select></div>
                </div>
                <label class="auswahl"><input type="checkbox" name="druckt_selbst" value="1"> druckt selbst – Karten werden erst beim Verkauf an der Stelle gedruckt (eigener Etikettendrucker nötig)</label>
                @include('ticket::teile.mitglieder', ['ausgewaehlt' => [], 'praefix' => 'neu'])
                <button type="submit" class="knopf">Anlegen</button>
            </form>
        @endif
    </section>

    <p class="hinweis">Zugeordnete Personen dürfen für ihre Stelle Karten und Freikarten anfordern und sehen nur deren Aufträge. Mit „druckt selbst“ verkaufen und drucken sie Karten direkt, ohne Druckstelle. Personen müssen dem Verein angehören (Verein → Personen &amp; Rechte).</p>
@endsection
