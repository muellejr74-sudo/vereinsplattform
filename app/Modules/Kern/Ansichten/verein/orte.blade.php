@extends('layouts.basis')

@section('titel', 'Orte & Säle')

@section('inhalt')
    <h1>Orte &amp; Säle</h1>
    @include('kern::teile.fehler')
    <p class="hinweis">Solange es nur einen Saal gibt, muss bei Vorstellungen kein Saal ausgewählt werden.</p>

    @foreach ($orte as $ort)
        <section class="karte">
            <form method="post" action="{{ route('verein.orte.speichern', $ort) }}" class="formular formular--zeile">
                @csrf @method('put')
                <div class="feld"><label for="ort_{{ $ort->id }}">Ort</label>
                    <input id="ort_{{ $ort->id }}" name="name" type="text" value="{{ $ort->name }}" maxlength="120" required></div>
                <button type="submit" class="knopf knopf--zweit">Speichern</button>
            </form>

            <h2>Säle</h2>
            @foreach ($ort->saele as $saal)
                <div class="formular formular--zeile">
                    <form method="post" action="{{ route('verein.saele.speichern', $saal) }}" class="formular formular--zeile" style="flex:1; margin:0">
                        @csrf @method('put')
                        <input type="hidden" name="ort_id" value="{{ $ort->id }}">
                        <div class="feld"><label for="saal_{{ $saal->id }}" class="sr">Saal</label>
                            <input id="saal_{{ $saal->id }}" name="name" type="text" value="{{ $saal->name }}" maxlength="120" required></div>
                        <button type="submit" class="knopf knopf--zweit">Speichern</button>
                    </form>
                    <form method="post" action="{{ route('verein.saele.loeschen', $saal) }}" onsubmit="return confirm('Saal löschen?')">
                        @csrf @method('delete') <button type="submit" class="link-knopf">Löschen</button>
                    </form>
                </div>
            @endforeach

            <form method="post" action="{{ route('verein.saele.neu') }}" class="formular formular--zeile">
                @csrf
                <input type="hidden" name="ort_id" value="{{ $ort->id }}">
                <div class="feld"><label for="neuer_saal_{{ $ort->id }}">Weiteren Saal anlegen</label>
                    <input id="neuer_saal_{{ $ort->id }}" name="name" type="text" maxlength="120" required placeholder="z. B. Nebenraum"></div>
                <button type="submit" class="knopf">Anlegen</button>
            </form>
        </section>
    @endforeach

    <section class="karte">
        <form method="post" action="{{ route('verein.orte.neu') }}" class="formular formular--zeile">
            @csrf
            <div class="feld"><label for="neuer_ort">Weiteren Ort anlegen</label>
                <input id="neuer_ort" name="name" type="text" maxlength="120" required placeholder="z. B. Turnhalle"></div>
            <button type="submit" class="knopf">Anlegen</button>
        </form>
    </section>
@endsection
