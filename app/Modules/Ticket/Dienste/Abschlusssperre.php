<?php

namespace App\Modules\Ticket\Dienste;

use App\Modules\Ticket\Models\Abschluss;
use InvalidArgumentException;

/** Nach dem Abschluss ist eine Vorstellung gesperrt (E4b) – zentral geprüft für alle Kartenvorgänge. */
class Abschlusssperre
{
    public const TEXT = 'Diese Vorstellung ist abgeschlossen. Änderungen sind nicht mehr möglich.';

    public static function istAbgeschlossen(int $vorstellungId): bool
    {
        return Abschluss::where('vorstellung_id', $vorstellungId)->exists();
    }

    public static function pruefen(int $vorstellungId): void
    {
        if (self::istAbgeschlossen($vorstellungId)) {
            throw new InvalidArgumentException(self::TEXT);
        }
    }
}
