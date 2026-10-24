<?php

namespace App\Modules\Kern\Models;

use App\Modules\Kern\Mandanten\GehoertZuMandant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Verknüpfung QR-Code → Veranstaltung mit Tischnummer und Pflicht-Enddatum (E54, E54a, E71). */
class Tischzuordnung extends Model
{
    use GehoertZuMandant;

    protected $table = 'tischzuordnungen';

    protected $fillable = ['qr_code_id', 'veranstaltung_id', 'tischnummer', 'gueltig_bis', 'zugeordnet_von'];

    protected function casts(): array
    {
        return ['gueltig_bis' => 'date'];
    }

    public function tischcode(): BelongsTo
    {
        return $this->belongsTo(Tischcode::class, 'qr_code_id');
    }

    public function veranstaltung(): BelongsTo
    {
        return $this->belongsTo(Veranstaltung::class);
    }

    /** Gültig bis einschließlich Enddatum; frei ab Mitternacht danach (E107). */
    public function scopeGueltig(Builder $abfrage): void
    {
        $abfrage->whereDate('gueltig_bis', '>=', today());
    }

    public function istGueltig(): bool
    {
        return $this->gueltig_bis->endOfDay()->isFuture();
    }
}
