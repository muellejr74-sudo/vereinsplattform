<?php

namespace App\Modules\Ticket\Models;

use App\Modules\Kern\Mandanten\GehoertZuMandant;
use App\Modules\Kern\Models\Veranstaltung;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ticketkategorie mit Preis, gültig für alle Vorstellungen einer Veranstaltung (E38). */
class Ticketkategorie extends Model
{
    use GehoertZuMandant;

    public const FREIKARTE = 'Freikarte';

    protected $table = 'ticketkategorien';

    protected $fillable = ['veranstaltung_id', 'name', 'preis_cent', 'ist_freikarte', 'reihenfolge'];

    protected function casts(): array
    {
        return ['preis_cent' => 'integer', 'ist_freikarte' => 'boolean'];
    }

    public function veranstaltung(): BelongsTo
    {
        return $this->belongsTo(Veranstaltung::class);
    }

    /** Preis für eine bestimmte Vorstellung – mit Abweichung, falls hinterlegt (E38). */
    public function preisFuer(int $vorstellungId): int
    {
        if ($this->ist_freikarte) {
            return 0;
        }

        return (int) (Preisabweichung::where('vorstellung_id', $vorstellungId)->where('kategorie_id', $this->id)->value('preis_cent') ?? $this->preis_cent);
    }

    /** Die feste Kategorie „Freikarte“ einer Veranstaltung (E118) – wird bei Bedarf angelegt. */
    public static function freikarte(Veranstaltung $veranstaltung): self
    {
        return static::firstOrCreate(
            ['veranstaltung_id' => $veranstaltung->id, 'ist_freikarte' => true],
            ['name' => self::FREIKARTE, 'preis_cent' => 0, 'reihenfolge' => 9999],
        );
    }
}
