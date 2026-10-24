<?php

namespace App\Modules\Kern\Models;

use App\Modules\Kern\Mandanten\GehoertZuMandant;
use Illuminate\Database\Eloquent\Model;

/** Station für Abendpersonal ohne eigenes Konto (E29, E34). */
class Station extends Model
{
    use GehoertZuMandant;

    public const ARTEN = [
        'bedienung' => 'Bedienung',
        'ausschank' => 'Ausschank',
        'kueche' => 'Küche / Essensausgabe',
        'einlass' => 'Einlass',
        'sonstige' => 'Sonstige',
    ];

    protected $table = 'stationen';

    protected $fillable = ['name', 'art', 'mehrere_geraete', 'reihenfolge'];

    protected function casts(): array
    {
        return ['mehrere_geraete' => 'boolean'];
    }
}
