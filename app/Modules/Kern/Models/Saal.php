<?php

namespace App\Modules\Kern\Models;

use App\Modules\Kern\Mandanten\GehoertZuMandant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Saal an einem Veranstaltungsort (E35). */
class Saal extends Model
{
    use GehoertZuMandant;

    protected $table = 'saele';

    protected $fillable = ['ort_id', 'name'];

    public function ort(): BelongsTo
    {
        return $this->belongsTo(Ort::class);
    }

    public function anzeigename(): string
    {
        return $this->ort ? $this->ort->name.' – '.$this->name : $this->name;
    }
}
