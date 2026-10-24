<?php

namespace App\Modules\Ticket\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Ticket\Models\Ticketeinstellung;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Ticket-Einstellungen des Vereins (E40a, E121, E126, E127).
 * Allgemein: Recht „Reservierungen“. Bankverbindung: Recht „Zahlungseingang vermerken“.
 */
class EinstellungenController extends Controller
{
    public function show(Request $request): View
    {
        abort_unless($request->user()->can('ticket.reservierungen') || $request->user()->can('ticket.zahlungseingang'), 403);

        return view('ticket::einstellungen', ['e' => Ticketeinstellung::aktuell()]);
    }

    public function allgemein(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            'reservierung_standard' => ['required', Rule::in(['gedruckt', 'digital'])],
            'nicht_abgeholt_minuten' => ['required', 'integer', 'min:-240', 'max:240'],
            'zahlungsfrist_tage' => ['required', 'integer', 'min:1', 'max:60'],
        ], [], ['nicht_abgeholt_minuten' => 'Minuten', 'zahlungsfrist_tage' => 'Zahlungsfrist']);

        $e = Ticketeinstellung::firstOrNew();
        $e->fill($daten)->save();
        Protokoll::schreibe('ticket.einstellungen_geaendert', $e, $daten);

        return back()->with('hinweis', 'Einstellungen gespeichert.');
    }

    public function bank(Request $request): RedirectResponse
    {
        $request->merge(['iban' => strtoupper((string) preg_replace('/\s+/', '', (string) $request->input('iban')))]);
        $daten = $request->validate([
            'kontoinhaber' => ['nullable', 'string', 'max:80'],
            'iban' => ['nullable', 'string', 'max:34', function ($attr, $wert, $fehler) {
                if ($wert !== '' && ! self::ibanGueltig($wert)) {
                    $fehler('Die IBAN ist nicht gültig – bitte prüfen.');
                }
            }],
        ], [], ['kontoinhaber' => 'Kontoinhaber', 'iban' => 'IBAN']);

        $e = Ticketeinstellung::firstOrNew();
        $vorher = $e->iban;
        $e->fill(['kontoinhaber' => $daten['kontoinhaber'] ?? null, 'iban' => ($daten['iban'] ?? '') ?: null])->save();
        Protokoll::schreibe('ticket.bankverbindung_geaendert', $e, [
            'iban_vorher' => $vorher ? '…'.substr($vorher, -4) : null, 'iban_neu' => $e->iban ? '…'.substr($e->iban, -4) : null,
        ]);

        return back()->with('hinweis', 'Bankverbindung gespeichert.');
    }

    /** Prüfsumme nach ISO 13616 (Modulo 97). */
    public static function ibanGueltig(string $iban): bool
    {
        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban)) {
            return false;
        }
        $umgestellt = substr($iban, 4).substr($iban, 0, 4);
        $zahl = '';
        foreach (str_split($umgestellt) as $zeichen) {
            $zahl .= ctype_alpha($zeichen) ? (string) (ord($zeichen) - 55) : $zeichen;
        }
        $rest = 0;
        foreach (str_split($zahl, 7) as $teil) {
            $rest = (int) (($rest.$teil) % 97);
        }

        return $rest === 1;
    }
}
