<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titel', config('app.name')) – {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/plattform.css') }}?v=1a">
</head>
<body>
    <header class="kopfzeile">
        <div class="kopfzeile__inhalt">
            <a class="logo" href="{{ route('startseite') }}" aria-label="{{ config('app.name') }} – Startseite">{{ config('app.name') }}</a>

            @auth
                <nav class="hauptmenue" aria-label="Hauptmenü">
                    <a href="{{ route('startseite') }}">Start</a>
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
