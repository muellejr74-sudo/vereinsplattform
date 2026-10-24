<?php

namespace App\Modules\Kern\Dienste;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Wandelt gepflegte Rechtstexte in sichere HTML-Darstellung um.
 *
 * Eingabe in einfacher Textform (Markdown): Überschriften mit "#", Absätze durch
 * Leerzeilen, Links als [Text](https://…). Eingegebenes HTML wird NICHT
 * ausgeführt, sondern als Text angezeigt (Schutz vor eingeschleusten Inhalten).
 */
class Rechtstext
{
    public static function html(?string $inhalt): HtmlString
    {
        return new HtmlString(Str::markdown((string) $inhalt, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 10,
        ]));
    }
}
