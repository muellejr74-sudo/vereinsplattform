<?php

namespace App\Modules\Kern\Models;

use App\Models\User;
use App\Modules\Kern\Rechte\Rechtekatalog;
use Database\Factories\MandantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

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

    /** @var list<string>|null */
    private ?array $moduleZwischenspeicher = null;

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

    /** @return list<string> freigeschaltete Module (E66: pro Verein durch den Super-Admin) */
    public function module(): array
    {
        return $this->moduleZwischenspeicher ??= DB::table('mandant_module')
            ->where('mandant_id', $this->id)->orderBy('modul')->pluck('modul')->all();
    }

    public function hatModul(string $modul): bool
    {
        return in_array($modul, $this->module(), true);
    }

    /**
     * @param  list<string>  $module
     * @return array{an: list<string>, aus: list<string>}
     */
    public function setzeModule(array $module): array
    {
        $module = array_values(array_intersect(array_unique($module), array_keys(Rechtekatalog::MODULE)));
        $bisher = $this->module();
        $an = array_values(array_diff($module, $bisher));
        $aus = array_values(array_diff($bisher, $module));

        DB::transaction(function () use ($an, $aus) {
            foreach ($an as $modul) {
                DB::table('mandant_module')->insert(['mandant_id' => $this->id, 'modul' => $modul, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('mandant_module')->where('mandant_id', $this->id)->whereIn('modul', $aus)->delete();
        });

        $this->moduleZwischenspeicher = null;

        return ['an' => $an, 'aus' => $aus];
    }

    public function hatLogo(): bool
    {
        return $this->logo_datei !== null;
    }

    public function logoAdresse(): ?string
    {
        return $this->hatLogo() ? route('verein.logo', ['kuerzel' => $this->kuerzel, 'v' => substr(md5((string) $this->logo_datei), 0, 8)]) : null;
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
