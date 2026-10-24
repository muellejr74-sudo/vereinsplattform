<?php

namespace App\Modules\Ticket\Dienste;

use App\Models\User;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Rechtevergabe;
use App\Modules\Ticket\Models\Kartenauftrag;
use App\Modules\Ticket\Nachrichten\NeuerDruckauftrag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * E-Mail an alle Personen des Vereins mit dem Recht „Druckaufträge drucken“, wenn ein neuer
 * Druckauftrag vorliegt (E132). Versand erst nach erfolgreichem Speichern; ein Fehler beim
 * Versand verhindert den Auftrag nicht.
 */
class Druckstellenmeldung
{
    public function __construct(private Mandantenkontext $kontext) {}

    public function melden(Kartenauftrag $auftrag): void
    {
        $verein = $this->kontext->mandant();
        if (! $verein) {
            return;
        }

        DB::afterCommit(function () use ($auftrag, $verein) {
            try {
                $ids = Rechtevergabe::where('recht', 'ticket.drucken')->whereNull('vorstellung_id')->pluck('user_id');
                $abgemeldet = DB::table('ticket_mail_abmeldungen')->pluck('user_id');
                $empfaenger = User::whereIn('id', $ids)->whereNotIn('id', $abgemeldet)->where('aktiv', true)
                    ->whereHas('vereine', fn ($q) => $q->whereKey($verein->id))->get();
                if ($empfaenger->isEmpty()) {
                    return;
                }
                $auftrag->loadMissing(['vorstellung.veranstaltung', 'verkaufsstelle', 'positionen']);
                $nachricht = new NeuerDruckauftrag(
                    $verein->name,
                    $auftrag->artText(),
                    $auftrag->istReservierung() ? 'Reservierung '.$auftrag->bemerkung : $auftrag->stelleText(),
                    $auftrag->vorstellung->veranstaltung->name.', '.$auftrag->vorstellung->anzeige(),
                    (int) $auftrag->positionen->sum('anzahl'),
                    route('tickets.auftraege.show', $auftrag),
                );
                foreach ($empfaenger as $person) {
                    $person->notify($nachricht);
                }
            } catch (Throwable $fehler) {
                Log::warning('Druckauftrags-E-Mail nicht gesendet: '.$fehler->getMessage(), ['auftrag' => $auftrag->id]);
            }
        });
    }
}
