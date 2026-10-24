@extends('layouts.basis')

@section('titel', 'Verkaufsstellen')

@section('inhalt')
    <h1>Verkaufsstellen</h1>
    <p class="hinweis">Zugeordnete Personen dürfen für ihre Stelle Karten und Freikarten anfordern und sehen nur deren Aufträge. Mit „druckt selbst“ verkaufen und drucken sie Karten direkt, ohne Druckstelle. Personen müssen dem Verein angehören (Verein → Personen &amp; Rechte).</p>
    @include('kern::teile.fehler')

    @foreach ($stellen as $stelle)
        <section class="karte">
            <form method="post" action="{{ route('tickets.verkaufsstellen.speichern', $stelle) }}" class="formular">
                @csrf @method('put')
                <div class="formular--zeile" style="margin:0">
                    <div class="feld"><label for="n{{ $stelle->id }}">Name</label><input id="n{{ $stelle->id }}" name="name" value="{{ $stelle->name }}" maxlength="80" required></div>
                    <div class="feld" style="flex:0 0 12rem"><label for="a{{ $stelle->id }}">Art</label>
                        <select id="a{{ $stelle->id }}" name="art">@foreach (\App\Modules\Ticket\Models\Verkaufsstelle::ARTEN as $k => $b)<option value="{{ $k }}" @selected($stelle->art === $k)>{{ $b }}</option>@endforeach</select></div>
                </div>
                <label class="auswahl"><input type="checkbox" name="aktiv" value="1" @checked($stelle->aktiv)> aktiv</label>
                <label class="auswahl"><input type="checkbox" name="druckt_selbst" value="1" @checked($stelle->druckt_selbst)> druckt selbst – Karten werden erst beim Verkauf an der Stelle gedruckt (eigener Etikettendrucker nötig)</label>
                @include('ticket::teile.mitglieder', ['ausgewaehlt' => $stelle->benutzer->pluck('id')->all(), 'praefix' => 's'.$stelle->id])
                <button type="submit" class="knopf knopf--zweit">Speichern</button>
            </form>
        </section>
    @endforeach

    <section class="karte">
        <h2>Neue Verkaufsstelle</h2>
        <form method="post" action="{{ route('tickets.verkaufsstellen.neu') }}" class="formular">
            @csrf
            <div class="formular--zeile" style="margin:0">
                <div class="feld"><label for="neu_name">Name</label><input id="neu_name" name="name" maxlength="80" required placeholder="z. B. Bäckerei Müller"></div>
                <div class="feld" style="flex:0 0 12rem"><label for="neu_art">Art</label>
                    <select id="neu_art" name="art">@foreach (\App\Modules\Ticket\Models\Verkaufsstelle::ARTEN as $k => $b)<option value="{{ $k }}">{{ $b }}</option>@endforeach</select></div>
            </div>
            <label class="auswahl"><input type="checkbox" name="druckt_selbst" value="1"> druckt selbst – Karten werden erst beim Verkauf an der Stelle gedruckt (eigener Etikettendrucker nötig)</label>
            @include('ticket::teile.mitglieder', ['ausgewaehlt' => [], 'praefix' => 'neu'])
            <button type="submit" class="knopf">Anlegen</button>
        </form>
    </section>
@endsection
