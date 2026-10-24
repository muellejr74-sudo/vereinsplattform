{{-- Kopf jeder Abendkassen-Unterseite: Vorstellung und Weg zurück ins Menü --}}
<div class="titelzeile">
    <h1>{{ $titel }}</h1>
    <a class="knopf knopf--zweit" href="{{ route('tickets.abendkasse.show', $v) }}">← Menü</a>
</div>
<p class="hinweis" title="{{ $v->veranstaltung->name }}">{{ $v->anzeige() }}</p>
@include('kern::teile.fehler')
