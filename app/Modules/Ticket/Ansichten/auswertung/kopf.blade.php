<div class="titelzeile">
    <h1>{{ $titel }}</h1>
    <a class="knopf knopf--zweit" href="{{ route('tickets.veranstaltung', $v->veranstaltung) }}">Zur Veranstaltung</a>
</div>
<p class="hinweis">{{ $v->veranstaltung->name }} · {{ $v->anzeige() }}</p>
<nav class="knopfzeile" style="margin-bottom:0.75rem" aria-label="Vorstellung">
    @can('tickets.auswerten')<a class="knopf knopf--zweit" href="{{ route('tickets.auswertung', $v) }}">Auswertung</a>@endcan
    @can('ticket.abschluss')<a class="knopf knopf--zweit" href="{{ route('tickets.abschluss', $v) }}">Abschluss</a>@endcan
    @can('tickets.nachbuchen')<a class="knopf knopf--zweit" href="{{ route('tickets.nachbuchen', $v) }}">Nachbuchen</a>@endcan
    @can('tickets.notfallliste')<a class="knopf knopf--zweit" href="{{ route('tickets.notfallliste', $v) }}" target="_blank">Notfallliste (PDF)</a>@endcan
</nav>
@include('kern::teile.fehler')
