@extends('layouts.basis')

@section('titel', 'Rechtstexte – '.$verein->name)

@section('inhalt')
    <section class="karte">
        <h1>Rechtstexte – {{ $verein->name }}</h1>
        @include('kern::teile.fehler')
        <p class="hinweis">Diese Texte erscheinen im Gästeportal des Vereins. Ansehen:
            <a href="{{ route('verein.impressum', $verein->kuerzel) }}" target="_blank" rel="noopener">Vereinsimpressum</a> ·
            <a href="{{ route('verein.datenschutz', $verein->kuerzel) }}" target="_blank" rel="noopener">Datenschutz mit Ergänzung</a></p>

        <form method="post" action="{{ route('verwaltung.vereine.texte', $verein) }}" class="formular">
            @csrf @method('put')
            @foreach (\App\Modules\Kern\Models\Vereinstext::ARTEN as $art => $bezeichnung)
                <label for="text_{{ $art }}">{{ $bezeichnung }}</label>
                <textarea id="text_{{ $art }}" name="texte[{{ $art }}]" rows="12">{{ old('texte.'.$art, $texte[$art] ?? '') }}</textarea>
            @endforeach
            @include('kern::verwaltung.teile.textformat')
            <div class="knopfzeile">
                <button type="submit" class="knopf">Speichern</button>
                <a href="{{ route('verwaltung.vereine.index') }}">Zurück</a>
            </div>
        </form>
    </section>
@endsection
