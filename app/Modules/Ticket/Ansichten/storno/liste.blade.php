@if ($liste->isEmpty())
    <p class="hinweis">Noch keine Karten in der Liste.</p>
@else
    <div class="tabelle-rahmen">
        <table class="tabelle tabelle--klein">
            <thead><tr><th>Nummer</th><th>Vorstellung</th><th>Kategorie</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($liste as $t)
                <tr>
                    <td data-titel="Nummer"><code>{{ $t->nummer }}</code>@if ($t->name) <span class="hinweis">{{ $t->name }}</span>@endif</td>
                    <td data-titel="Vorstellung">{{ $t->vorstellung->veranstaltung->kurzname ?: $t->vorstellung->veranstaltung->name }}, {{ $t->vorstellung->anzeige() }}</td>
                    <td data-titel="Kategorie">{{ $t->ist_freikarte ? 'Freikarte' : $t->kategorie->name }}</td>
                    <td data-titel="Status">{{ $t->statusText() }}@if ($h = $dienst->hinweis($t))<br><strong>{{ $h }}</strong>@endif</td>
                    <td class="aktionen">
                        <form method="post" action="{{ route('tickets.storno.entfernen', $t) }}" class="inline">@csrf <button type="submit" class="link-knopf">entfernen</button></form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <form method="post" action="{{ route('tickets.storno.ausfuehren') }}" onsubmit="return confirm('{{ $liste->count() }} Karten endgültig stornieren? Die Nummern werden dauerhaft ungültig.')" style="margin-top:0.75rem">
        @csrf <button type="submit" class="knopf knopf--gross" style="background:var(--status-storniert);border-color:var(--status-storniert)">{{ $liste->count() }} Karten stornieren</button>
    </form>
    <form method="post" action="{{ route('tickets.storno.leeren') }}" style="margin-top:0.5rem">@csrf <button type="submit" class="link-knopf">Liste leeren (nichts stornieren)</button></form>
@endif
