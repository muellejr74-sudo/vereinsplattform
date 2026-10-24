<?php

namespace App\Modules\Kern\Models;

use App\Models\User;
use Database\Factories\MandantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Ein Mandant (E66): Verein oder – später – Privatkunde.
 * Bewusst NICHT mandantengebunden: Die Liste der Mandanten gehört dem Super-Admin.
 */
class Mandant extends Model
{
    /** @use HasFactory<MandantFactory> */
    use HasFactory;

    public const TYP_VEREIN = 'verein';

    public const TYP_PRIVATKUNDE = 'privatkunde';

    protected $table = 'mandanten';

    protected $fillable = ['typ', 'name', 'kuerzel', 'aktiv'];

    protected function casts(): array
    {
        return ['aktiv' => 'boolean'];
    }

    protected static function newFactory(): MandantFactory
    {
        return MandantFactory::new();
    }

    public function benutzer(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mitgliedschaften', 'mandant_id', 'user_id')->withTimestamps();
    }

    public function scopeAktiv(Builder $abfrage): void
    {
        $abfrage->where('aktiv', true);
    }

    /** Bezeichnung in der Oberfläche je nach Typ (E66). */
    public function typBezeichnung(): string
    {
        return $this->typ === self::TYP_PRIVATKUNDE ? 'Privatfest' : 'Verein';
    }
}
