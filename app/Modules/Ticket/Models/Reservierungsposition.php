<?php

namespace App\Modules\Ticket\Models;

use App\Modules\Kern\Mandanten\GehoertZuMandant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservierungsposition extends Model
{
    use GehoertZuMandant;

    protected $table = 'reservierungspositionen';

    protected $fillable = ['reservierung_id', 'kategorie_id', 'anzahl', 'preis_cent', 'anzahl_abgeholt'];

    protected function casts(): array
    {
        return ['anzahl' => 'integer', 'preis_cent' => 'integer', 'anzahl_abgeholt' => 'integer'];
    }

    public function kategorie(): BelongsTo
    {
        return $this->belongsTo(Ticketkategorie::class);
    }
}
