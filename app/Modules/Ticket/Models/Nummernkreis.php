<?php

namespace App\Modules\Ticket\Models;

use App\Modules\Kern\Mandanten\GehoertZuMandant;
use Illuminate\Database\Eloquent\Model;

/** Laufende Ticketnummer je Verein und Jahr (E45, E113). */
class Nummernkreis extends Model
{
    use GehoertZuMandant;

    protected $table = 'nummernkreise';

    protected $fillable = ['jahr', 'letzte_nummer'];
}
