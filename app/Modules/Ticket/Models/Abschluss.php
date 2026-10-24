<?php

namespace App\Modules\Ticket\Models;

use App\Models\User;
use App\Modules\Kern\Mandanten\GehoertZuMandant;
use App\Modules\Kern\Models\Vorstellung;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Abschluss einer Vorstellung (E4): danach ist die Vorstellung gesperrt (E4b). */
class Abschluss extends Model
{
    use GehoertZuMandant;

    protected $table = 'ticket_abschluesse';

    protected $fillable = ['vorstellung_id', 'abgeschlossen_von', 'abgeschlossen_am'];

    protected function casts(): array
    {
        return ['abgeschlossen_am' => 'datetime'];
    }

    public function vorstellung(): BelongsTo
    {
        return $this->belongsTo(Vorstellung::class);
    }

    public function durch(): BelongsTo
    {
        return $this->belongsTo(User::class, 'abgeschlossen_von');
    }
}
