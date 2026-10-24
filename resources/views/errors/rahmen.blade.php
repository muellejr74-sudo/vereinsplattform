<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titel') – {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/plattform.css') }}?v=1a">
</head>
<body>
    <header class="kopfzeile"><div class="kopfzeile__inhalt"><a class="logo" href="{{ url('/') }}">{{ config('app.name') }}</a></div></header>
    <main class="inhalt">
        <section class="karte karte--schmal">
            <h1>@yield('titel')</h1>
            <p>@yield('text')</p>
            <p><a class="knopf" href="{{ url('/') }}">Zur Startseite</a></p>
        </section>
    </main>
</body>
</html>
