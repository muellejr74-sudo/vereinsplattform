@extends('layouts.basis')

@section('titel', 'Design & Module – '.$verein->name)

@section('inhalt')
    <section class="karte karte--schmal">
        <h1>Design &amp; Module</h1>
        <p class="hinweis">{{ $verein->name }} ({{ $verein->kuerzel }}) – diese Einstellungen kann nur der Super-Administrator ändern.</p>
        @include('kern::teile.fehler')
        @foreach (session('designhinweise', $hinweise) as $hinweis)
            <div class="meldung meldung--hinweis">{{ $hinweis }}</div>
        @endforeach

        <form method="post" action="{{ route('verwaltung.vereine.design', $verein) }}" enctype="multipart/form-data" class="formular">
            @csrf @method('put')

            <fieldset class="rechte-gruppe">
                <legend>Module</legend>
                @foreach ($module as $schluessel => $bezeichnung)
                    <label class="auswahl">
                        <input type="checkbox" name="module[]" value="{{ $schluessel }}" @checked(in_array($schluessel, old('module', $verein->module()), true))>
                        {{ $bezeichnung }}
                    </label>
                @endforeach
                <p class="hinweis">Wird ein Modul ausgeschaltet, bleiben die vergebenen Rechte gespeichert, wirken aber nicht.</p>
            </fieldset>

            <fieldset class="rechte-gruppe">
                <legend>Vereinsfarben</legend>
                @foreach (['farbe_primaer' => 'Primärfarbe (Kopfzeile, Knöpfe, Links)', 'farbe_sekundaer' => 'Sekundärfarbe (helle Flächen, Tabellenköpfe)'] as $feld => $bezeichnung)
                    <label for="{{ $feld }}">{{ $bezeichnung }}</label>
                    <div class="farbwahl">
                        <input type="color" value="{{ old($feld, $verein->$feld) ?? ($feld === 'farbe_primaer' ? '#1f4e79' : '#e8eef5') }}"
                               aria-label="{{ $bezeichnung }} auswählen"
                               oninput="document.getElementById('{{ $feld }}').value = this.value">
                        <input id="{{ $feld }}" name="{{ $feld }}" type="text" value="{{ old($feld, $verein->$feld) }}" placeholder="z. B. #1f4e79" maxlength="7"
                               pattern="#[0-9a-fA-F]{6}">
                    </div>
                @endforeach
                <p class="hinweis">Leer lassen = Plattformfarben. Die Schriftfarbe (schwarz oder weiß) wird automatisch passend gewählt.</p>
            </fieldset>

            <fieldset class="rechte-gruppe">
                <legend>Logo</legend>
                @if ($verein->hatLogo())
                    <img class="logo-vorschau" src="{{ $verein->logoAdresse() }}" alt="Aktuelles Logo">
                    <label class="auswahl"><input type="checkbox" name="logo_entfernen" value="1"> Logo entfernen</label>
                @endif
                <label for="logo">{{ $verein->hatLogo() ? 'Neues Logo hochladen' : 'Logo hochladen' }}</label>
                <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp">
                <p class="hinweis">PNG, JPG oder WebP, höchstens 2 MB. Querformat mit transparentem Hintergrund wirkt am besten.</p>
            </fieldset>

            <div class="knopfzeile">
                <button type="submit" class="knopf">Speichern</button>
                <a href="{{ route('verwaltung.vereine.index') }}">Zurück</a>
            </div>
        </form>
    </section>
@endsection
