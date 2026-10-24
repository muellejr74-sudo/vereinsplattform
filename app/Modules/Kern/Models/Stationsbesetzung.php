<?php

namespace App\Modules\Kern\Models;

use App\Modules\Kern\Mandanten\GehoertZuMandant;
use Illuminate\Database\Eloquent\Model;

/** Wer ist heute „Bedienung 1“? Namenszuordnung je Vorstellung (E34). */
class Stationsbesetzung extends Model
{
    use GehoertZuMandant;

    protected $table = 'stationsbesetzungen';

    protected $fillable = ['vorstellung_id', 'station_id', 'name'];
}
