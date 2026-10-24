@extends('layouts.basis')

@section('titel', 'Rechte – '.$person->name)

@section('inhalt')
    <section class="karte">
        <h1>{{ $person->name }}</h1>
        <p class="hinweis">{{ $person->email }} · Rechte im Verein „{{ $verein->name }}“</p>
        @include('kern::teile.fehler')

        @if ($istSelbst && ! auth()->user()->istSuperAdmin())
            <div class="meldung meldung--hinweis">Ihre eigenen Rechte können Sie nicht ändern.</div>
        @elseif (! $darfRechteAendern)
            <div class="meldung meldung--hinweis">Sie dürfen die Rechte dieser Person ansehen, aber nicht ändern.</div>
        @endif

        <form method="post" action="{{ route('verein.personen.rechte.speichern', $person) }}" class="formular" id="rechteformular">
            @csrf @method('put')
            @foreach ($gruppen as $bereich => $rechteDerGruppe)
                <fieldset class="rechte-gruppe">
                    <legend>{{ $bereich }}</legend>
                    @foreach ($rechteDerGruppe as $recht)
                        @php
                            $modul = \App\Modules\Kern\Rechte\Rechtekatalog::modul($recht);
                            $aenderbar = $darfRechteAendern && in_array($recht, $weitergebbar, true);
                            $verfuegbar = \App\Modules\Kern\Rechte\Rechtekatalog::istVerfuegbar($recht);
                        @endphp
                        <label class="auswahl {{ $aenderbar ? '' : 'recht--aus' }}">
                            <input type="checkbox" name="rechte[]" value="{{ $recht }}" @checked(in_array($recht, $vorhanden, true)) @disabled(! $aenderbar)>
                            <span>
                                {{ \App\Modules\Kern\Rechte\Rechtekatalog::bezeichnung($recht) }}
                                @unless ($verfuegbar)<span class="zusatz">folgt in Stufe {{ \App\Modules\Kern\Rechte\Rechtekatalog::stufe($recht) }}</span>@endunless
                                @if ($modul && ! $verein->hatModul($modul))<span class="zusatz">– Modul nicht freigeschaltet, Recht ruht</span>@endif
                                @if ($darfRechteAendern && ! in_array($recht, $weitergebbar, true))<span class="zusatz">– können Sie nicht vergeben</span>@endif
                            </span>
                        </label>
                        {{-- Nicht änderbare, vorhandene Rechte bleiben unverändert (Server prüft zusätzlich). --}}
                    @endforeach
                </fieldset>
            @endforeach

            @if ($darfRechteAendern)
                <div class="knopfzeile">
                    <button type="submit" class="knopf">Rechte speichern</button>
                    <button type="submit" name="vorlage" value="vereinsadmin" class="knopf knopf--zweit"
                            onclick="return confirm('Alle Rechte durch die Vorlage „Vereinsadmin“ ersetzen? Drucken, Stornieren und Abschluss sind darin ausgeschaltet.')">
                        Vorlage „Vereinsadmin“ übernehmen
                    </button>
                </div>
            @endif
        </form>
    </section>

    <section class="karte">
        <h2>Rechte nur für einzelne Vorstellungen</h2>
        <p class="hinweis">Zum Beispiel „Abendkasse“ nur am 01.12. – zusätzlich zu den Rechten oben, die für alle Veranstaltungen gelten.</p>
        @if ($vorstellungsrechte->isNotEmpty())
            <ul>
                @foreach ($vorstellungsrechte as $vr)
                    @php $vst = $alleVorstellungen->get($vr->vorstellung_id); @endphp
                    <li>
                        {{ \App\Modules\Kern\Rechte\Rechtekatalog::bezeichnung($vr->recht) }} –
                        {{ $vst ? $vst->veranstaltung->name.', '.$vst->anzeige() : 'gelöschte Vorstellung' }}
                        @if ($darfRechteAendern && in_array($vr->recht, $weitergebbar, true))
                            <form method="post" action="{{ route('verein.personen.vorstellungsrechte.entziehen', [$person, $vr]) }}" class="inline">
                                @csrf @method('delete') <button type="submit" class="link-knopf">entfernen</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="hinweis">Keine.</p>
        @endif

        @php $moduleRechte = array_values(array_filter($weitergebbar, fn ($r) => \App\Modules\Kern\Rechte\Rechtekatalog::modul($r) !== null)); @endphp
        @if ($darfRechteAendern && $vorstellungen->isNotEmpty() && $moduleRechte)
            <form method="post" action="{{ route('verein.personen.vorstellungsrechte', $person) }}" class="formular formular--zeile">
                @csrf
                <div class="feld"><label for="vr_recht">Recht</label>
                    <select id="vr_recht" name="recht">@foreach ($moduleRechte as $r)<option value="{{ $r }}">{{ \App\Modules\Kern\Rechte\Rechtekatalog::bezeichnung($r) }}</option>@endforeach</select></div>
                <div class="feld"><label for="vr_vorstellung">Vorstellung</label>
                    <select id="vr_vorstellung" name="vorstellung_id">@foreach ($vorstellungen as $v)<option value="{{ $v->id }}">{{ $v->veranstaltung->name }} – {{ $v->anzeige() }}</option>@endforeach</select></div>
                <button type="submit" class="knopf knopf--zweit">Hinzufügen</button>
            </form>
        @endif
    </section>

    @if ($darfPersonVerwalten)
        <section class="karte">
            <h2>Person im Verein</h2>
            @if ($person->aktiv && ! $person->letzte_anmeldung_am)
                <form method="post" action="{{ route('verein.personen.einladung', $person) }}" class="inline">
                    @csrf <button type="submit" class="knopf knopf--zweit">Einladung erneut senden</button>
                </form>
            @endif
            <form method="post" action="{{ route('verein.personen.entfernen', $person) }}" class="inline"
                  onsubmit="return confirm('{{ $person->name }} wirklich aus dem Verein entfernen? Alle Rechte in diesem Verein werden gelöscht. Das Konto selbst bleibt bestehen.')">
                @csrf @method('delete')
                <button type="submit" class="knopf knopf--zweit">Aus dem Verein entfernen</button>
            </form>
            <p class="hinweis">Name und E-Mail-Adresse ändern oder das Konto ganz deaktivieren kann nur der Plattformbetreiber, weil eine Person in mehreren Vereinen mitarbeiten kann.</p>
        </section>
    @endif

    <p><a href="{{ route('verein.personen.index') }}">‹ Zurück zur Übersicht</a></p>
@endsection
