@extends('layouts.basis')

@section('titel', 'Betreiber-Tische')

@section('inhalt')
    <h1>Betreiber-Tische</h1>
    <p class="hinweis">Ihre eigenen Tische mit fester QR-Code-Kennung. Vereine ordnen geliehene Tische selbst per Scan zu – hier sehen Sie nur, wo ein Tisch gerade verwendet wird. Freie Tische zeigen Ihre Werbeseite.</p>
    @include('kern::teile.fehler')

    <section class="karte">
        <form method="post" action="{{ route('verwaltung.tische.erzeugen') }}" class="formular formular--zeile">
            @csrf
            <div class="feld" style="flex:0 0 8rem"><label for="anzahl">Neue Tische</label><input id="anzahl" name="anzahl" type="number" min="1" max="500" value="10" required></div>
            <button type="submit" class="knopf">Anlegen</button>
        </form>
    </section>

    @if ($codes->isNotEmpty())
        <form method="get" action="{{ route('verwaltung.tische.pdf') }}" target="_blank">
            <section class="karte">
                <div class="tabelle-rahmen">
                    <table class="tabelle tabelle--klein">
                        <thead><tr><th>Drucken</th><th>Tisch</th><th>Code</th><th>Zurzeit</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($codes as $code)
                            @php $z = $zuordnungen->get($code->id); @endphp
                            <tr>
                                <td data-titel="Drucken"><input type="checkbox" name="codes[]" value="{{ $code->id }}" checked aria-label="{{ $code->bezeichnung }} drucken"></td>
                                <td data-titel="Tisch">{{ $code->bezeichnung }}</td>
                                <td data-titel="Code"><code>{{ $code->code }}</code></td>
                                <td data-titel="Zurzeit">{{ $z ? ($vereine[$z->mandant_id] ?? '?').' · '.$z->veranstaltung.' · Tisch '.$z->tischnummer.' · bis '.\Illuminate\Support\Carbon::parse($z->gueltig_bis)->format('d.m.') : 'frei' }}</td>
                                <td class="aktionen">@unless ($z)<button type="submit" form="loeschen-{{ $code->id }}" class="link-knopf">Löschen</button>@endunless</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @include('kern::teile.aufkleber-layout', ['layout' => $layout])
                <button type="submit" class="knopf">Ausgewählte als PDF drucken</button>
            </section>
        </form>
        @foreach ($codes as $code)
            <form id="loeschen-{{ $code->id }}" method="post" action="{{ route('verwaltung.tische.loeschen', $code) }}" onsubmit="return confirm('Tisch löschen?')">@csrf @method('delete')</form>
        @endforeach
    @endif
@endsection
