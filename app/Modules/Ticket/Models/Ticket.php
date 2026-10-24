<?php

namespace App\Modules\Ticket\Models;

use App\Modules\Kern\Mandanten\GehoertZuMandant;
use App\Modules\Kern\Models\Vorstellung;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eintrittskarte (E43, E45). Zustände: erzeugt → gedruckt → ausgegeben → verkauft → eingelassen;
 * storniert. „Freikarte“ ist ein Merkmal der Karte (E118).
 */
class Ticket extends Model
{
    use GehoertZuMandant;

    public const STATUS = [
        'erzeugt' => 'erzeugt (Druck ausstehend)',
        'gedruckt' => 'gedruckt',
        'ausgegeben' => 'ausgegeben',
        'verkauft' => 'verkauft',
        'eingelassen' => 'eingelassen',
        'storniert' => 'storniert',
    ];

    /** Zustände, die einen Platz im Kontingent belegen. */
    public const BELEGEND = ['erzeugt', 'gedruckt', 'ausgegeben', 'verkauft', 'eingelassen'];

    protected $table = 'tickets';

    protected $fillable = [
        'nummer', 'pruefzeichen', 'vorstellung_id', 'kategorie_id', 'preis_cent', 'ist_freikarte', 'status', 'name',
        'stapel_position', 'stapel_groesse', 'kartenauftrag_id', 'verkaufsstelle_id',
    ];

    protected $hidden = ['pruefzeichen'];

    protected function casts(): array
    {
        return [
            'preis_cent' => 'integer',
            'ist_freikarte' => 'boolean',
            'gedruckt_am' => 'datetime',
            'ausgegeben_am' => 'datetime',
        ];
    }

    public function vorstellung(): BelongsTo
    {
        return $this->belongsTo(Vorstellung::class);
    }

    public function kategorie(): BelongsTo
    {
        return $this->belongsTo(Ticketkategorie::class);
    }

    /** Inhalt des QR-Codes: kurzer Link mit Prüfzeichen (E112). */
    public function qrInhalt(): string
    {
        return route('ticket.pruefen', $this->nummer.'.'.$this->pruefzeichen);
    }

    public function stapelText(): ?string
    {
        return $this->stapel_groesse > 1 ? $this->stapel_position.'/'.$this->stapel_groesse : null;
    }
}
