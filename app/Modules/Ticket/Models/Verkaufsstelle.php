<?php

namespace App\Modules\Ticket\Models;

use App\Models\User;
use App\Modules\Kern\Mandanten\GehoertZuMandant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Vorverkaufsstelle oder Abendkasse mit zugeordneten Benutzerkonten (E24, E46, E115). */
class Verkaufsstelle extends Model
{
    use GehoertZuMandant;

    public const ARTEN = ['vorverkauf' => 'Vorverkaufsstelle', 'abendkasse' => 'Abendkasse'];

    protected $table = 'verkaufsstellen';

    protected $fillable = ['name', 'art', 'aktiv', 'druckt_selbst'];

    protected function casts(): array
    {
        return ['aktiv' => 'boolean', 'druckt_selbst' => 'boolean'];
    }

    public function benutzer(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'verkaufsstellen_benutzer')->withTimestamps()->orderBy('name');
    }

    public function istZugeordnet(User $benutzer): bool
    {
        return $this->benutzer()->whereKey($benutzer->id)->exists();
    }
}
