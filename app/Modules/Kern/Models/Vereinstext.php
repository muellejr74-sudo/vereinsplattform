<?php

namespace App\Modules\Kern\Models;

use App\Modules\Kern\Mandanten\GehoertZuMandant;
use Illuminate\Database\Eloquent\Model;

/** Rechtstext eines Vereins (E83) – mandantengebunden. */
class Vereinstext extends Model
{
    use GehoertZuMandant;

    public const IMPRESSUM = 'impressum';

    public const DATENSCHUTZ_ERGAENZUNG = 'datenschutz_ergaenzung';

    public const ARTEN = [
        self::IMPRESSUM => 'Vereinsimpressum',
        self::DATENSCHUTZ_ERGAENZUNG => 'Ergänzung zur Datenschutzerklärung',
    ];

    protected $table = 'vereinstexte';

    protected $fillable = ['art', 'inhalt'];
}
