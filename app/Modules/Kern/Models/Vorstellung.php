<?php

namespace App\Modules\Kern\Models;

use App\Modules\Kern\Mandanten\GehoertZuMandant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Vorstellung – der zentrale Ankerpunkt für alle Module (E36).
 *
 * Zeitfenster (E100): beginnt 3 Stunden vor Einlass (ohne Einlass: vor Beginn),
 * endet 3 Stunden nach dem Ende – ohne Ende um 4:00 Uhr am Folgetag.
 */
class Vorstellung extends Model
{
    use GehoertZuMandant;

    public const STUNDEN_VORHER = 3;

    public const STUNDEN_NACHHER = 3;

    public const ENDE_OHNE_ENDZEIT = '04:00';

    protected $table = 'vorstellungen';

    protected $fillable = ['veranstaltung_id', 'saal_id', 'einlass_um', 'beginn_um', 'ende_um', 'kontingent'];

    protected function casts(): array
    {
        return [
            'einlass_um' => 'datetime',
            'beginn_um' => 'datetime',
            'ende_um' => 'datetime',
            'kontingent' => 'integer',
        ];
    }

    public function veranstaltung(): BelongsTo
    {
        return $this->belongsTo(Veranstaltung::class);
    }

    public function saal(): BelongsTo
    {
        return $this->belongsTo(Saal::class);
    }

    public function besetzungen(): HasMany
    {
        return $this->hasMany(Stationsbesetzung::class);
    }

    public function geraete(): HasMany
    {
        return $this->hasMany(Stationsgeraet::class);
    }

    public function fensterBeginn(): Carbon
    {
        return ($this->einlass_um ?? $this->beginn_um)->copy()->subHours(self::STUNDEN_VORHER);
    }

    public function fensterEnde(): Carbon
    {
        if ($this->ende_um) {
            return $this->ende_um->copy()->addHours(self::STUNDEN_NACHHER);
        }

        [$stunde, $minute] = array_map('intval', explode(':', self::ENDE_OHNE_ENDZEIT));

        return $this->beginn_um->copy()->addDay()->setTime($stunde, $minute);
    }

    public function istImZeitfenster(?Carbon $zeitpunkt = null): bool
    {
        $zeitpunkt ??= now();

        return $zeitpunkt->greaterThanOrEqualTo($this->fensterBeginn()) && $zeitpunkt->lessThan($this->fensterEnde());
    }

    public function istVorbei(?Carbon $zeitpunkt = null): bool
    {
        return ($zeitpunkt ?? now())->greaterThanOrEqualTo($this->fensterEnde());
    }

    /** Kurze Anzeige, z. B. „Sa 01.12.2026, 19:30 Uhr“. */
    public function anzeige(): string
    {
        return $this->beginn_um->locale('de')->isoFormat('dd DD.MM.YYYY').', '.$this->beginn_um->format('H:i').' Uhr';
    }
}
