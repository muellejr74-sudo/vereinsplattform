<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titel', config('app.name'))</title>
    <link rel="stylesheet" href="{{ asset('css/plattform.css') }}">
</head>
<body>
    <header class="kopfzeile">
        <div class="kopfzeile__inhalt">
            <span class="logo" aria-label="{{ config('app.name') }}">{{ config('app.name') }}</span>
        </div>
    </header>

    <main class="inhalt">
        @yield('inhalt')
    </main>

    <footer class="fusszeile">
        <span>&copy; {{ now()->year }} {{ config('app.name') }}</span>
    </footer>
</body>
</html>
