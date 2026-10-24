<?php

namespace App\Modules\Kern\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Eintrag im unveränderbaren Protokoll (E84).
 *
 * Einträge können nur angelegt, nie geändert oder einzeln gelöscht werden.
 * Einzige Ausnahme ist die automatische Löschung nach 2 Jahren (E89), die als
 * Sammellöschung über "model:prune" läuft.
 */
class Protokolleintrag extends Model
{
    use MassPrunable;

    public const AUFBEWAHRUNG_JAHRE = 2;

    public $timestamps = false;

    protected $table = 'protokoll';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'zeitpunkt' => 'datetime',
            'details' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Protokolleinträge sind unveränderbar.'));
        static::deleting(fn () => throw new LogicException('Protokolleinträge können nicht einzeln gelöscht werden.'));
    }

    /** Für die automatische Löschung nach Ablauf der Aufbewahrungsfrist. */
    public function prunable(): Builder
    {
        return static::where('zeitpunkt', '<', now()->subYears(self::AUFBEWAHRUNG_JAHRE));
    }

    public function mandant(): BelongsTo
    {
        return $this->belongsTo(Mandant::class);
    }
}
