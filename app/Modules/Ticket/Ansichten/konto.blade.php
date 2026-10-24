{{-- Abschnitt für „Mein Konto“: E-Mails zu neuen Druckaufträgen (E132) --}}
@php $abgemeldet = \Illuminate\Support\Facades\DB::table('ticket_mail_abmeldungen')->where('user_id', auth()->id())->exists(); @endphp
<section class="karte">
    <h2>E-Mails der Ticketverwaltung</h2>
    <form method="post" action="{{ route('konto.druckauftragmails') }}" class="formular">
        @csrf @method('PUT')
        <input type="hidden" name="druckauftraege" value="0">
        <label class="auswahl"><input type="checkbox" name="druckauftraege" value="1" @checked(! $abgemeldet)> E-Mail bei neuen Druckaufträgen (für die Druckstelle)</label>
        <button type="submit" class="knopf knopf--zweit">Speichern</button>
    </form>
</section>
