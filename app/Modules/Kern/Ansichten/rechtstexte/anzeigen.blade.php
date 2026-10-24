@extends('layouts.basis')

@section('titel', $titel)

@section('inhalt')
    <article class="karte rechtstext">
        <h1>{{ $titel }}</h1>
        @if (filled($inhalt))
            {{ \App\Modules\Kern\Dienste\Rechtstext::html($inhalt) }}
        @else
            <p class="hinweis">Dieser Text ist noch nicht hinterlegt.</p>
        @endif

        @if (filled($ergaenzung ?? null))
            <h2>{{ $ergaenzungTitel }}</h2>
            {{ \App\Modules\Kern\Dienste\Rechtstext::html($ergaenzung) }}
        @endif
    </article>
@endsection
