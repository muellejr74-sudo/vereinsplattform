<?php

namespace App\Modules\Kern\Models;

use App\Modules\Kern\Mandanten\GehoertZuMandant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** Veranstaltung eines Vereins, z. B. „Theater 2026“ (E35). */
class Veranstaltung extends Model
{
    use GehoertZuMandant;

    protected $table = 'veranstaltungen';

    protected $fillable = ['name', 'kurzname', 'beschreibung'];

    public function vorstellungen(): HasMany
    {
        return $this->hasMany(Vorstellung::class)->orderBy('beginn_um');
    }

    public function tischzuordnungen(): HasMany
    {
        return $this->hasMany(Tischzuordnung::class);
    }

    /**
     * Die gerade laufende Vorstellung (Zeitfenster E100) – Grundlage für E37a.
     */
    public function laufendeVorstellung(?Carbon $zeitpunkt = null): ?Vorstellung
    {
        $zeitpunkt ??= now();

        return $this->vorstellungen()
            ->where('beginn_um', '<=', $zeitpunkt->copy()->addHours(Vorstellung::STUNDEN_VORHER + 24))
            ->where('beginn_um', '>=', $zeitpunkt->copy()->subDays(2))
            ->get()
            ->first(fn (Vorstellung $v) => $v->istImZeitfenster($zeitpunkt));
    }

    /** Vorschlag für das Ende einer Tischzuordnung (E71, E71a). */
    public function vorschlagGueltigBis(): Carbon
    {
        $letzte = $this->vorstellungen()->reorder()->orderByDesc('beginn_um')->first();

        if ($letzte && $letzte->beginn_um->copy()->addDay()->isAfter(today())) {
            return $letzte->beginn_um->copy()->addDay()->startOfDay();
        }

        return today()->addDays(5);
    }
}
