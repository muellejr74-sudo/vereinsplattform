<?php

namespace App\Modules\Kern\Models;

use App\Modules\Kern\Mandanten\GehoertZuMandant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Veranstaltungsort eines Vereins (E35). */
class Ort extends Model
{
    use GehoertZuMandant;

    protected $table = 'orte';

    protected $fillable = ['name'];

    public function saele(): HasMany
    {
        return $this->hasMany(Saal::class)->orderBy('name');
    }
}
