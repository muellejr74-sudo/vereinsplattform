@extends('layouts.basis')

@section('titel', 'Stationen')

@section('inhalt')
    <h1>Stationen</h1>
    <p class="hinweis">Stationen sind die Plätze des Abendpersonals (ohne eigenes Konto). Welche Person heute an welcher Station ist, tragen Sie pro Vorstellung ein.</p>
    @include('kern::teile.fehler')

    <div class="tabelle-rahmen">
        <table class="tabelle">
            <thead><tr><th>Name</th><th>Art</th><th>Geräte</th><th>Reihenfolge</th><th></th></tr></thead>
            <tbody>
            @foreach ($stationen as $station)
                <tr>
                    <td colspan="4" style="padding:0">
                        <form method="post" action="{{ route('verein.stationen.speichern', $station) }}" class="formular formular--zeile" style="margin:0; padding:0.5rem 0.75rem">
                            @csrf @method('put')
                            <div class="feld"><label class="sr" for="n{{ $station->id }}">Name</label><input id="n{{ $station->id }}" name="name" value="{{ $station->name }}" maxlength="60" required></div>
                            <div class="feld"><label class="sr" for="a{{ $station->id }}">Art</label>
                                <select id="a{{ $station->id }}" name="art">@foreach (\App\Modules\Kern\Models\Station::ARTEN as $k => $b)<option value="{{ $k }}" @selected($station->art === $k)>{{ $b }}</option>@endforeach</select></div>
                            <label class="auswahl" style="font-weight:normal"><input type="checkbox" name="mehrere_geraete" value="1" @checked($station->mehrere_geraete)> mehrere Geräte</label>
                            <div class="feld" style="flex:0 0 6rem"><label class="sr" for="r{{ $station->id }}">Reihenfolge</label><input id="r{{ $station->id }}" name="reihenfolge" type="number" value="{{ $station->reihenfolge }}"></div>
                            <button type="submit" class="knopf knopf--zweit">Speichern</button>
                        </form>
                    </td>
                    <td class="aktionen">
                        <form method="post" action="{{ route('verein.stationen.loeschen', $station) }}" onsubmit="return confirm('Station löschen?')">
                            @csrf @method('delete') <button type="submit" class="link-knopf">Löschen</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <section class="karte" style="margin-top:1rem">
        <h2>Station hinzufügen</h2>
        <form method="post" action="{{ route('verein.stationen.neu') }}" class="formular formular--zeile">
            @csrf
            <div class="feld"><label for="neu_name">Name</label><input id="neu_name" name="name" maxlength="60" required placeholder="z. B. Bedienung 6"></div>
            <div class="feld"><label for="neu_art">Art</label>
                <select id="neu_art" name="art">@foreach (\App\Modules\Kern\Models\Station::ARTEN as $k => $b)<option value="{{ $k }}">{{ $b }}</option>@endforeach</select></div>
            <label class="auswahl" style="font-weight:normal"><input type="checkbox" name="mehrere_geraete" value="1"> mehrere Geräte gleichzeitig</label>
            <button type="submit" class="knopf">Hinzufügen</button>
        </form>
        <p class="hinweis">Bedienungen: ein Gerät pro Station. Ausschank, Küche und Einlass dürfen mehrere Geräte gleichzeitig nutzen.</p>
    </section>
@endsection
