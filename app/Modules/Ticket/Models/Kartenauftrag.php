<?php

namespace App\Modules\Ticket\Models;

use App\Models\User;
use App\Modules\Kern\Mandanten\GehoertZuMandant;
use App\Modules\Kern\Models\Vorstellung;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Kartenauftrag einer Verkaufsstelle oder – bei Freikarten – auch vereinsintern (E26, E26a, E114c).
 * Ablauf: angefordert → bestätigt → gedruckt → ausgegeben (oder abgelehnt/zurückgezogen).
 */
class Kartenauftrag extends Model
{
    use GehoertZuMandant;

    public const STATUS = [
        'angefordert' => 'angefordert',
        'bestaetigt' => 'bestätigt – Druck ausstehend',
        'gedruckt' => 'gedruckt – zur Abholung',
        'ausgegeben' => 'ausgegeben',
        'abgelehnt' => 'abgelehnt',
        'zurueckgezogen' => 'zurückgezogen',
    ];

    protected $table = 'kartenauftraege';

    protected $fillable = ['vorstellung_id', 'verkaufsstelle_id', 'art', 'status', 'name', 'bemerkung', 'angefordert_von'];

    protected function casts(): array
    {
        return ['bestaetigt_am' => 'datetime', 'gedruckt_am' => 'datetime', 'ausgegeben_am' => 'datetime'];
    }

    public function positionen(): HasMany
    {
        return $this->hasMany(Auftragsposition::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class)->orderBy('id');
    }

    public function vorstellung(): BelongsTo
    {
        return $this->belongsTo(Vorstellung::class);
    }

    public function verkaufsstelle(): BelongsTo
    {
        return $this->belongsTo(Verkaufsstelle::class);
    }

    public function anfordernder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'angefordert_von');
    }

    public function istFreikarten(): bool
    {
        return $this->art === 'freikarten';
    }

    public function anzahlAngefordert(): int
    {
        return (int) $this->positionen->sum('anzahl');
    }

    public function anzahlBestaetigt(): int
    {
        return (int) $this->positionen->sum('anzahl_bestaetigt');
    }

    /** Druckaufträge aus Reservierungen (E40) und Vorkasse-Versand (E124) – ohne Bestätigungsschritt. */
    public function istReservierung(): bool
    {
        return in_array($this->art, ['reservierung', 'versand'], true);
    }

    public function reservierung(): HasOne
    {
        return $this->hasOne(Reservierung::class);
    }

    public function statusText(): string
    {
        if ($this->status === 'gedruckt' && $this->art === 'reservierung') {
            return 'gedruckt – an der Abendkasse hinterlegt';
        }
        if ($this->status === 'gedruckt' && $this->art === 'versand') {
            return 'gedruckt – Versand ausstehend';
        }
        if ($this->status === 'ausgegeben' && $this->art === 'versand') {
            return 'versendet';
        }

        return self::STATUS[$this->status] ?? $this->status;
    }

    public function artText(): string
    {
        return match ($this->art) {
            'freikarten' => 'Freikarten-Stapel',
            'reservierung' => 'Reservierung (Abendkasse)',
            'versand' => 'Vorkasse – Versand per Post',
            default => 'Karten für den Verkauf',
        };
    }

    public function stelleText(): string
    {
        if ($this->istReservierung()) {
            return 'Reservierung '.$this->name;
        }

        return $this->verkaufsstelle?->name ?? 'Verein (intern)';
    }
}
