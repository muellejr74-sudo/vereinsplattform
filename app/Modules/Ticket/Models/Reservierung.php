<?php

namespace App\Modules\Ticket\Models;

use App\Models\User;
use App\Modules\Kern\Mandanten\GehoertZuMandant;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Vorstellung;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Telefonische Reservierung als Stapel mit einem Namen (E40, E114, E120, E124).
 *  - gedruckt: Karten entstehen sofort, werden gedruckt und an der Abendkasse hinterlegt.
 *  - digital:  Plätze vorgemerkt, Karten entstehen bei Abholung (sofort eingelassen, E44).
 *  - vorkasse: Plätze vorgemerkt; nach Geldeingang entstehen die Karten und werden versendet.
 * Status: offen → abgeholt | bezahlt → versendet | storniert.
 */
class Reservierung extends Model
{
    use GehoertZuMandant;

    public const ARTEN = [
        'gedruckt' => 'gedruckt, an der Abendkasse hinterlegt',
        'digital' => 'nur digital, Abholung an der Abendkasse',
        'vorkasse' => 'Vorkasse per Überweisung, Versand per Post',
    ];

    public const STATUS = [
        'offen' => 'offen',
        'bezahlt' => 'bezahlt – Druck/Versand ausstehend',
        'versendet' => 'versendet',
        'abgeholt' => 'abgeholt',
        'storniert' => 'storniert',
    ];

    public const ZAHLUNGSARTEN = ['bar' => 'bar', 'karte' => 'Karte/EC', 'ueberweisung' => 'Überweisung'];

    protected $table = 'reservierungen';

    protected $fillable = [
        'nummer', 'vorstellung_id', 'verkaufsstelle_id', 'art', 'status', 'name', 'telefon', 'strasse', 'plz', 'ort',
        'bemerkung', 'zahlungsfrist', 'angelegt_von',
    ];

    protected function casts(): array
    {
        return [
            'zahlungsfrist' => 'date',
            'bezahlt_am' => 'datetime',
            'versendet_am' => 'datetime',
            'abgeholt_am' => 'datetime',
            'storniert_am' => 'datetime',
        ];
    }

    public function positionen(): HasMany
    {
        return $this->hasMany(Reservierungsposition::class)->orderBy('id');
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

    public function kartenauftrag(): BelongsTo
    {
        return $this->belongsTo(Kartenauftrag::class);
    }

    public function angelegtVon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'angelegt_von');
    }

    /** Plätze vorgemerkt, aber noch keine Karten (zählt zum Kontingent, E43). */
    public function scopeVorgemerkt(Builder $abfrage): void
    {
        $abfrage->where('status', 'offen')->whereIn('art', ['digital', 'vorkasse']);
    }

    public function scopeUeberfaellig(Builder $abfrage): void
    {
        $abfrage->where('status', 'offen')->where('art', 'vorkasse')->whereDate('zahlungsfrist', '<', today());
    }

    public function anzahl(): int
    {
        return (int) $this->positionen->sum('anzahl');
    }

    public function betragCent(): int
    {
        return (int) $this->positionen->sum(fn ($p) => $p->anzahl * $p->preis_cent);
    }

    public function kennung(): string
    {
        return sprintf('R-%04d', $this->nummer);
    }

    /** Verwendungszweck für die Überweisung, z. B. „TVM R-0042 Meier“ (E124). */
    public function verwendungszweck(): string
    {
        $kuerzel = Mandant::find($this->mandant_id)?->kuerzel;

        return trim($kuerzel.' '.$this->kennung().' '.mb_substr($this->name, 0, 40));
    }

    public function istUeberfaellig(): bool
    {
        return $this->status === 'offen' && $this->art === 'vorkasse' && $this->zahlungsfrist?->lt(today());
    }

    public function statusText(): string
    {
        if ($this->status === 'offen') {
            return match ($this->art) {
                'vorkasse' => $this->istUeberfaellig() ? 'Zahlung überfällig' : 'Zahlung offen',
                'gedruckt' => $this->tickets->contains('status', 'erzeugt') ? 'reserviert – Druck ausstehend' : 'reserviert – an der Abendkasse hinterlegt',
                default => 'reserviert – Abholung an der Abendkasse',
            };
        }

        return self::STATUS[$this->status] ?? $this->status;
    }

    public function anschrift(): array
    {
        return array_values(array_filter([$this->name, $this->strasse, trim($this->plz.' '.$this->ort)]));
    }

    public function stelleText(): string
    {
        return $this->verkaufsstelle?->name ?? 'Verein';
    }
}
