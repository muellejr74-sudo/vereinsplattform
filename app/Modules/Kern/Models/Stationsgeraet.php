<?php

namespace App\Modules\Kern\Models;

use App\Modules\Kern\Mandanten\GehoertZuMandant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ein Gerät, das für einen Abend an eine Station gebunden ist (E29a). */
class Stationsgeraet extends Model
{
    use GehoertZuMandant;

    protected $table = 'stationsgeraete';

    protected $fillable = ['vorstellung_id', 'station_id', 'token_pruefsumme', 'geraet', 'angemeldet_am', 'zuletzt_aktiv_am', 'abgemeldet_am'];

    protected function casts(): array
    {
        return [
            'angemeldet_am' => 'datetime',
            'zuletzt_aktiv_am' => 'datetime',
            'abgemeldet_am' => 'datetime',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function vorstellung(): BelongsTo
    {
        return $this->belongsTo(Vorstellung::class);
    }

    public function scopeAktiv(Builder $abfrage): void
    {
        $abfrage->whereNull('abgemeldet_am');
    }
}
