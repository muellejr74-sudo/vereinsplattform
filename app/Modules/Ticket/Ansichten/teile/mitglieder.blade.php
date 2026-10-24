<fieldset class="rechte-gruppe">
    <legend>Zugeordnete Personen</legend>
    @forelse ($mitglieder as $m)
        <label class="auswahl"><input type="checkbox" name="benutzer[]" value="{{ $m->id }}" @checked(in_array($m->id, $ausgewaehlt, true))> {{ $m->name }} <span class="hinweis">{{ $m->email }}</span></label>
    @empty
        <p class="hinweis">Dem Verein sind noch keine Personen zugeordnet.</p>
    @endforelse
</fieldset>
