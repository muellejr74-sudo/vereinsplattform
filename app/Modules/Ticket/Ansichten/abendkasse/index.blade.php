@extends('layouts.basis')

@section('titel', 'Abendkasse')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>Abendkasse</h1>
        @include('kern::teile.fehler')
        @if ($offen->isEmpty())
            <p>Gerade ist keine Abendkasse geöffnet.</p>
        @else
            <p>Für welche Vorstellung?</p>
            <div class="menue-knoepfe">
                @foreach ($offen as $v)
                    <a class="menue-knopf" href="{{ route('tickets.abendkasse.show', $v) }}">
                        <strong>{{ $v->anzeige() }}</strong><span>{{ $v->veranstaltung->name }}</span>
                    </a>
                @endforeach
            </div>
        @endif
        @if ($kommend->isNotEmpty())
            <h2 style="margin-top:1.25rem">Demnächst</h2>
            @foreach ($kommend as $v)
                <p class="hinweis">{{ $v->veranstaltung->name }}, {{ $v->anzeige() }} – öffnet {{ $v->fensterBeginn()->format('d.m.') }} um {{ $v->fensterBeginn()->format('H:i') }} Uhr</p>
            @endforeach
        @endif
        <p class="hinweis" style="margin-top:1rem">Die Abendkasse öffnet 3 Stunden vor Einlass und schließt 3 Stunden nach dem Ende der Vorstellung.</p>
    </section>
@endsection
