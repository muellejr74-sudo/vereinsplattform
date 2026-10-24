@extends('layouts.basis')

@section('titel', 'Person hinzufügen')

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>Person hinzufügen</h1>
        <p>Geben Sie die <strong>genaue E-Mail-Adresse</strong> ein.</p>
        <ul class="hinweis">
            <li>Hat die Person schon ein Konto, wird es Ihrem Verein hinzugefügt.</li>
            <li>Gibt es noch kein Konto, geben Sie zusätzlich den Namen ein. Die Person erhält dann eine Einladung per E-Mail und legt ihr Passwort selbst fest.</li>
        </ul>
        @include('kern::teile.fehler')

        <form method="post" action="{{ route('verein.personen.hinzufuegen') }}" class="formular">
            @csrf
            <label for="email">E-Mail-Adresse</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" required autofocus>

            <label for="name">Name (nur bei neuen Personen nötig)</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" maxlength="120">

            <div class="knopfzeile">
                <button type="submit" class="knopf">Hinzufügen</button>
                <a href="{{ route('verein.personen.index') }}">Abbrechen</a>
            </div>
        </form>
    </section>
@endsection
