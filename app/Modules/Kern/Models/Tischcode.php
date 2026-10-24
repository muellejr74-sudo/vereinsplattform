<?php

namespace App\Modules\Kern\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * QR-Code eines Tisches.
 *
 * Bewusst NICHT automatisch mandantengebunden: Betreiber-Tische (mandant_id leer)
 * gehören dem Super-Admin und werden von Vereinen per Scan übernommen (E52a).
 * Vereins-Codes werden immer ausdrücklich mit vomVerein() abgefragt.
 */
class Tischcode extends Model
{
    /** Ohne leicht verwechselbare Zeichen (0/O, 1/I/L). */
    public const ZEICHEN = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const LAENGE = 10;

    protected $table = 'qr_codes';

    protected $fillable = ['mandant_id', 'code', 'bezeichnung', 'laufnummer', 'einheitscode'];

    protected function casts(): array
    {
        return ['einheitscode' => 'boolean', 'mandant_id' => 'integer'];
    }

    public function mandant(): BelongsTo
    {
        return $this->belongsTo(Mandant::class);
    }

    public function zuordnung(): HasOne
    {
        return $this->hasOne(Tischzuordnung::class, 'qr_code_id')->withoutGlobalScopes();
    }

    public function scopeBetreiber(Builder $abfrage): void
    {
        $abfrage->whereNull('mandant_id');
    }

    public function scopeVomVerein(Builder $abfrage, int $mandantId): void
    {
        $abfrage->where('mandant_id', $mandantId);
    }

    public function istBetreiberTisch(): bool
    {
        return $this->mandant_id === null;
    }

    public function adresse(): string
    {
        return route('scan', $this->code);
    }

    public static function neuerCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::LAENGE; $i++) {
                $code .= self::ZEICHEN[random_int(0, strlen(self::ZEICHEN) - 1)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
