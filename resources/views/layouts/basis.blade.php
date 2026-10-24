<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titel', config('app.name')) – {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/plattform.css') }}?v=1b">
    @php
        // Vereinsdesign (E76): Farben aus der Datenbank, nur wenn ein Verein aktiv ist.
        $aktiverVereinDesign = app(\App\Modules\Kern\Mandanten\Mandantenkontext::class)->mandant();
        $designVariablen = \App\Modules\Kern\Dienste\Vereinsdesign::variablen($aktiverVereinDesign);
    @endphp
    @if ($designVariablen)
        <style>:root { @foreach ($designVariablen as $name => $wert){{ $name }}: {{ $wert }}; @endforeach }</style>
    @endif
</head>
<body>
    <header class="kopfzeile">
        <div class="kopfzeile__inhalt">
            <a class="logo" href="{{ route('startseite') }}" aria-label="{{ config('app.name') }} – Startseite">
                @if ($aktiverVereinDesign)
                    @if ($aktiverVereinDesign->hatLogo())
                        <img class="logo__bild" src="{{ $aktiverVereinDesign->logoAdresse() }}" alt="">
                    @endif
                    <span>{{ $aktiverVereinDesign->name }}</span>
                @else
                    <span>{{ config('app.name') }}</span>
                @endif
            </a>

            @auth
                <nav class="hauptmenue" aria-label="Hauptmenü">
                    <a href="{{ route('startseite') }}">Start</a>
                    @if ($aktiverVereinDesign)
                        @can('verein.verwalten')
                            <a href="{{ route('verein.uebersicht') }}">Verein</a>
                        @endcan
                    @endif
                    @if (auth()->user()->istSuperAdmin())
                        <a href="{{ route('verwaltung.uebersicht') }}">Verwaltung</a>
                    @endif
                    <a href="{{ route('konto') }}">Mein Konto</a>
                    <form method="post" action="{{ route('abmelden') }}" class="inline">
                        @csrf
                        <button type="submit" class="link-knopf">Abmelden</button>
                    </form>
                </nav>
            @endauth
        </div>
    </header>

    <main class="inhalt">
        @if (session('hinweis'))
            <div class="meldung meldung--erfolg" role="status">{{ session('hinweis') }}</div>
        @endif

        @yield('inhalt')
    </main>

    <footer class="fusszeile">
        <a href="{{ route('impressum') }}">Impressum</a>
        <span aria-hidden="true">·</span>
        <a href="{{ route('datenschutz') }}">Datenschutz</a>
        <span aria-hidden="true">·</span>
        <span>&copy; {{ now()->year }} {{ config('app.name') }}</span>
    </footer>
</body>
</html>
