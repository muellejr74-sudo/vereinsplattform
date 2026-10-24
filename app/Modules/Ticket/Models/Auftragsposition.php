<?php

namespace App\Modules\Ticket\Models;

use App\Modules\Kern\Mandanten\GehoertZuMandant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Eine Zeile eines Kartenauftrags: Kategorie und Anzahl (angefordert/bestätigt). */
class Auftragsposition extends Model
{
    use GehoertZuMandant;

    protected $table = 'auftragspositionen';

    protected $fillable = ['kartenauftrag_id', 'kategorie_id', 'anzahl', 'anzahl_bestaetigt'];

    protected function casts(): array
    {
        return ['anzahl' => 'integer', 'anzahl_bestaetigt' => 'integer'];
    }

    public function kategorie(): BelongsTo
    {
        return $this->belongsTo(Ticketkategorie::class);
    }
}
