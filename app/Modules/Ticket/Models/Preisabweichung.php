<?php

namespace App\Modules\Ticket\Models;

use App\Modules\Kern\Mandanten\GehoertZuMandant;
use Illuminate\Database\Eloquent\Model;

/** Abweichender Preis einer Kategorie für eine einzelne Vorstellung (E38). */
class Preisabweichung extends Model
{
    use GehoertZuMandant;

    protected $table = 'preisabweichungen';

    protected $fillable = ['vorstellung_id', 'kategorie_id', 'preis_cent'];
}
