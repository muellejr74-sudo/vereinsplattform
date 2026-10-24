@extends('layouts.basis')

@section('titel', 'Rechtstexte der Plattform')

@section('inhalt')
    <section class="karte">
        <h1>Rechtstexte der Plattform</h1>
        @include('kern::teile.fehler')
        <p class="hinweis">Diese Texte sind auf jeder Seite unten verlinkt:
            <a href="{{ route('impressum') }}" target="_blank" rel="noopener">Impressum</a> ·
            <a href="{{ route('datenschutz') }}" target="_blank" rel="noopener">Datenschutz</a></p>

        <form method="post" action="{{ route('verwaltung.rechtstexte') }}" class="formular">
            @csrf @method('put')
            @foreach (\App\Modules\Kern\Models\Plattformtext::ARTEN as $art => $bezeichnung)
                <label for="text_{{ $art }}">{{ $bezeichnung }}</label>
                <textarea id="text_{{ $art }}" name="texte[{{ $art }}]" rows="16">{{ old('texte.'.$art, $texte[$art] ?? '') }}</textarea>
            @endforeach
            @include('kern::verwaltung.teile.textformat')
            <button type="submit" class="knopf">Speichern</button>
        </form>
    </section>
@endsection
