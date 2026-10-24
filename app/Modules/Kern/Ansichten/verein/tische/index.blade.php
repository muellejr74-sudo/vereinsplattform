@extends('layouts.basis')

@section('titel', 'QR-Codes & Tische')

@section('inhalt')
    <h1>QR-Codes &amp; Tische</h1>
    @include('kern::teile.fehler')

    <section class="karte">
        <h2>So funktioniert es</h2>
        <ol class="schritte">
            <li>Eigene QR-Codes erzeugen und als Aufkleber drucken – oder Tische des Plattformbetreibers leihen.</li>
            <li>Am Aufbautag angemeldet (im richtigen Verein) jeden Aufkleber mit der <strong>normalen Handy-Kamera</strong> scannen.</li>
            <li>Veranstaltung und Tischnummer wählen, Nummer ins Freifeld des Aufklebers schreiben – fertig.</li>
        </ol>
    </section>

    <section class="karte">
        <h2>Eigene QR-Codes erzeugen</h2>
        <form method="post" action="{{ route('verein.tische.erzeugen') }}" class="formular formular--zeile">
            @csrf
            <div class="feld" style="flex:0 0 8rem"><label for="anzahl">Anzahl</label><input id="anzahl" name="anzahl" type="number" min="1" max="200" value="10" required></div>
            <button type="submit" class="knopf">Erzeugen</button>
        </form>
        <form method="post" action="{{ route('verein.tische.erzeugen') }}">
            @csrf <input type="hidden" name="anzahl" value="1"><input type="hidden" name="einheitscode" value="1">
            <button type="submit" class="link-knopf">Einheitscode erzeugen (ein Code für alle Tische, nur zum Anzeigen – ohne Bestellen)</button>
        </form>
    </section>

    @if ($codes->isNotEmpty())
        <form method="get" action="{{ route('verein.tische.pdf') }}" target="_blank">
            <section class="karte">
                <h2>Ihre QR-Codes ({{ $codes->count() }})</h2>
                <div class="tabelle-rahmen">
                    <table class="tabelle tabelle--klein">
                        <thead><tr><th>Drucken</th><th>Bezeichnung</th><th>Zugeordnet</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($codes as $code)
                            @php $z = $zuordnungen->get($code->id); @endphp
                            <tr>
                                <td data-titel="Drucken"><input type="checkbox" name="codes[]" value="{{ $code->id }}" checked aria-label="{{ $code->bezeichnung }} drucken"></td>
                                <td data-titel="Bezeichnung">{{ $code->bezeichnung }} <span class="hinweis">{{ $code->code }}</span></td>
                                <td data-titel="Zugeordnet">{{ $z ? ($z->veranstaltung?->name.' · '.($z->tischnummer ? 'Tisch '.$z->tischnummer : 'alle Tische').' · bis '.$z->gueltig_bis->format('d.m.')) : 'frei' }}</td>
                                <td class="aktionen">
                                    <a href="{{ route('verein.tische.zuordnen', ['code' => $code->code]) }}">Zuordnen</a>
                                    @unless ($z)
                                        <button type="submit" form="loeschen-{{ $code->id }}" class="link-knopf">Löschen</button>
                                    @endunless
                                </td>
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
            <form id="loeschen-{{ $code->id }}" method="post" action="{{ route('verein.tische.loeschen', $code) }}" onsubmit="return confirm('QR-Code löschen? Gedruckte Aufkleber mit diesem Code funktionieren danach nicht mehr.')">@csrf @method('delete')</form>
        @endforeach
    @endif
@endsection
