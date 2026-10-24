@extends('layouts.basis')

@section('titel', 'Vereinsimpressum')

@section('inhalt')
    <section class="karte">
        <h1>Vereinsimpressum – {{ $verein->name }}</h1>
        @include('kern::teile.fehler')
        <p class="hinweis">Diese Texte erscheinen im Gästeportal Ihres Vereins. Ansehen:
            <a href="{{ route('verein.impressum', $verein->kuerzel) }}" target="_blank" rel="noopener">Impressum</a> ·
            <a href="{{ route('verein.datenschutz', $verein->kuerzel) }}" target="_blank" rel="noopener">Datenschutz</a></p>

        <form method="post" action="{{ route('verein.rechtstexte') }}" class="formular">
            @csrf @method('put')
            @foreach (\App\Modules\Kern\Models\Vereinstext::ARTEN as $art => $bezeichnung)
                <label for="text_{{ $art }}">{{ $bezeichnung }}</label>
                <textarea id="text_{{ $art }}" name="texte[{{ $art }}]" rows="12">{{ old('texte.'.$art, $texte[$art] ?? '') }}</textarea>
            @endforeach
            @include('kern::verwaltung.teile.textformat')
            <button type="submit" class="knopf">Speichern</button>
        </form>
    </section>
@endsection
