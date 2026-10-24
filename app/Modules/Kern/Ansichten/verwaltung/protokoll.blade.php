@extends('layouts.basis')

@section('titel', 'Protokoll')

@section('inhalt')
    <h1>Protokoll</h1>
    <p class="hinweis">Einträge sind unveränderbar und werden nach {{ \App\Modules\Kern\Models\Protokolleintrag::AUFBEWAHRUNG_JAHRE }} Jahren automatisch gelöscht.</p>

    <form method="get" class="formular formular--zeile">
        <div class="feld">
        <label for="verein">Bereich</label>
        <select id="verein" name="verein">
            <option value="">alle</option>
            <option value="plattform" @selected(($filter['verein'] ?? '') === 'plattform')>nur Plattform (ohne Verein)</option>
            @foreach ($vereine as $verein)
                <option value="{{ $verein->id }}" @selected(($filter['verein'] ?? '') === (string) $verein->id)>{{ $verein->name }}</option>
            @endforeach
        </select>
        </div>
        <div class="feld">
        <label for="aktion">Vorgang</label>
        <select id="aktion" name="aktion">
            <option value="">alle</option>
            @foreach ($aktionen as $schluessel => $bezeichnung)
                <option value="{{ $schluessel }}" @selected(($filter['aktion'] ?? '') === $schluessel)>{{ $bezeichnung }}</option>
            @endforeach
        </select>
        </div>
        <div class="feld">
        <label for="suche">Suche</label>
        <input id="suche" name="suche" type="search" value="{{ $filter['suche'] ?? '' }}" placeholder="Name, E-Mail …">
        </div>
        <button type="submit" class="knopf knopf--zweit">Filtern</button>
    </form>

    <div class="tabelle-rahmen">
        <table class="tabelle tabelle--klein">
            <thead><tr><th>Zeitpunkt</th><th>Wer</th><th>Vorgang</th><th>Verein</th><th>Einzelheiten</th><th>IP</th></tr></thead>
            <tbody>
            @forelse ($eintraege as $eintrag)
                <tr>
                    <td data-titel="Zeitpunkt">{{ $eintrag->zeitpunkt->format('d.m.Y H:i:s') }}</td>
                    <td data-titel="Wer">{{ $eintrag->akteur ?? '–' }}</td>
                    <td data-titel="Vorgang">{{ \App\Modules\Kern\Dienste\Protokoll::bezeichnung($eintrag->aktion) }}</td>
                    <td data-titel="Verein">{{ $eintrag->mandant?->kuerzel ?? '–' }}</td>
                    <td data-titel="Einzelheiten">
                        @if ($eintrag->ziel_typ) <span class="hinweis">{{ $eintrag->ziel_typ }} #{{ $eintrag->ziel_id }}</span> @endif
                        @if ($eintrag->details)
                            <code class="details">{{ json_encode($eintrag->details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code>
                        @endif
                    </td>
                    <td data-titel="IP">{{ $eintrag->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Keine Einträge.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $eintraege->links() }}
@endsection
