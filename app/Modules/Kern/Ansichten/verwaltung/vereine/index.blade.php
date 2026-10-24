@extends('layouts.basis')

@section('titel', 'Vereine')

@section('inhalt')
    <div class="titelzeile">
        <h1>Vereine</h1>
        <a class="knopf" href="{{ route('verwaltung.vereine.create') }}">Verein anlegen</a>
    </div>

    @if ($vereine->isEmpty())
        <p class="karte">Noch keine Vereine angelegt.</p>
    @else
        <div class="tabelle-rahmen">
            <table class="tabelle">
                <thead><tr><th>Name</th><th>Kürzel</th><th>Personen</th><th>Module</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach ($vereine as $verein)
                    <tr>
                        <td data-titel="Name">{{ $verein->name }}</td>
                        <td data-titel="Kürzel"><code>{{ $verein->kuerzel }}</code></td>
                        <td data-titel="Personen">{{ $verein->benutzer_count }}</td>
                        <td data-titel="Module">{{ collect($verein->module())->map(fn ($m) => \App\Modules\Kern\Rechte\Rechtekatalog::MODULE[$m] ?? $m)->join(', ') ?: '–' }}</td>
                        <td data-titel="Status">
                            <span class="status {{ $verein->aktiv ? 'status--fertig' : 'status--storniert' }}">{{ $verein->aktiv ? 'aktiv' : 'deaktiviert' }}</span>
                        </td>
                        <td class="aktionen">
                            <a href="{{ route('verwaltung.vereine.edit', $verein) }}">Bearbeiten</a>
                            <a href="{{ route('verwaltung.vereine.design', $verein) }}">Design &amp; Module</a>
                            @if ($verein->aktiv)
                                <a href="{{ route('verwaltung.vereine.personen', $verein) }}">Personen &amp; Rechte</a>
                            @endif
                            <a href="{{ route('verwaltung.vereine.texte', $verein) }}">Rechtstexte</a>
                            <form method="post" action="{{ route('verwaltung.vereine.aktiv', $verein) }}" class="inline"
                                  onsubmit="return confirm('{{ $verein->aktiv ? 'Verein wirklich deaktivieren? Seine Benutzer können dann nicht mehr in diesem Verein arbeiten.' : 'Verein wieder aktivieren?' }}')">
                                @csrf
                                <button type="submit" class="link-knopf">{{ $verein->aktiv ? 'Deaktivieren' : 'Aktivieren' }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
