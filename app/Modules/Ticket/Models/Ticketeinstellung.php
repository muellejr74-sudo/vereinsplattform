<?php

namespace App\Modules\Ticket\Models;

use App\Modules\Kern\Mandanten\GehoertZuMandant;
use Illuminate\Database\Eloquent\Model;

/** Ticket-Einstellungen eines Vereins (E40a, E121, E126, E127). Genau eine Zeile je Verein. */
class Ticketeinstellung extends Model
{
    use GehoertZuMandant;

    public const STANDARD = [
        'reservierung_standard' => 'gedruckt',
        'nicht_abgeholt_minuten' => 0,
        'zahlungsfrist_tage' => 7,
    ];

    protected $table = 'ticket_einstellungen';

    protected $fillable = ['reservierung_standard', 'nicht_abgeholt_minuten', 'zahlungsfrist_tage', 'kontoinhaber', 'iban'];

    protected function casts(): array
    {
        return ['nicht_abgeholt_minuten' => 'integer', 'zahlungsfrist_tage' => 'integer'];
    }

    /** Einstellungen des aktiven Vereins (ohne Eintrag: Voreinstellungen, nicht gespeichert). */
    public static function aktuell(): self
    {
        return self::first() ?? new self(self::STANDARD);
    }

    /** IBAN in Vierergruppen zum Vorlesen. */
    public function ibanAnzeige(): ?string
    {
        return $this->iban ? trim(chunk_split($this->iban, 4, ' ')) : null;
    }
}
