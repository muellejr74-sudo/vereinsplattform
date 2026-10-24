@extends('layouts.basis')

@section('titel', 'Protokoll')

@section('inhalt')
    <h1>Protokoll – {{ $verein->name }}</h1>
    <form method="get" class="formular formular--zeile">
        <div class="feld">
            <label for="aktion">Vorgang</label>
            <select id="aktion" name="aktion">
                <option value="">alle</option>
                @foreach ($aktionen as $schluessel => $bezeichnung)
                    <option value="{{ $schluessel }}" @selected(($filter['aktion'] ?? '') === $schluessel)>{{ $bezeichnung }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="knopf knopf--zweit">Filtern</button>
    </form>

    <div class="tabelle-rahmen">
        <table class="tabelle tabelle--klein">
            <thead><tr><th>Zeitpunkt</th><th>Wer</th><th>Vorgang</th><th>Einzelheiten</th></tr></thead>
            <tbody>
            @forelse ($eintraege as $eintrag)
                <tr>
                    <td data-titel="Zeitpunkt">{{ $eintrag->zeitpunkt->format('d.m.Y H:i:s') }}</td>
                    <td data-titel="Wer">{{ $eintrag->akteur ?? '–' }}</td>
                    <td data-titel="Vorgang">{{ \App\Modules\Kern\Dienste\Protokoll::bezeichnung($eintrag->aktion) }}</td>
                    <td data-titel="Einzelheiten">@if ($eintrag->details)<code class="details">{{ json_encode($eintrag->details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code>@endif</td>
                </tr>
            @empty
                <tr><td colspan="4">Keine Einträge.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $eintraege->links() }}
@endsection
