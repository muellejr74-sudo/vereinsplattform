<?php

namespace App\Modules\Kern\Models;

use Illuminate\Database\Eloquent\Model;

/** Zentraler Rechtstext des Betreibers (E83): Plattform-Impressum, Datenschutzerklärung. */
class Plattformtext extends Model
{
    public const IMPRESSUM = 'impressum';

    public const DATENSCHUTZ = 'datenschutz';

    public const ARTEN = [
        self::IMPRESSUM => 'Impressum',
        self::DATENSCHUTZ => 'Datenschutzerklärung',
    ];

    protected $table = 'plattformtexte';

    protected $fillable = ['art', 'inhalt'];

    public static function inhalt(string $art): ?string
    {
        return static::where('art', $art)->value('inhalt');
    }
}
