<?php

namespace App\Modules\Kern\Models;

use App\Models\User;
use App\Modules\Kern\Mandanten\GehoertZuMandant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein vergebenes Recht (E19): WAS (recht) + WO (Verein) + WANN (vorstellung_id, null = alle).
 * Mandantengebunden – Rechte gelten immer nur in einem Verein.
 */
class Rechtevergabe extends Model
{
    use GehoertZuMandant;

    protected $table = 'rechtevergaben';

    protected $fillable = ['user_id', 'recht', 'vorstellung_id', 'vergeben_von'];

    public function benutzer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
