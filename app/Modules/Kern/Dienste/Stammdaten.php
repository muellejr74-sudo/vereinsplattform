<?php

namespace App\Modules\Kern\Dienste;

use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Ort;
use App\Modules\Kern\Models\Saal;
use App\Modules\Kern\Models\Station;

/**
 * Grundausstattung eines Vereins: ein Ort mit einem Saal (E101) und die
 * Standard-Stationen (E103). Wird beim ersten Aufruf angelegt.
 */
class Stammdaten
{
    public const STANDARD_STATIONEN = [
        ['Einlass', 'einlass', true],
        ['Ausschank', 'ausschank', true],
        ['Küche', 'kueche', true],
        ['Bedienung 1', 'bedienung', false],
        ['Bedienung 2', 'bedienung', false],
        ['Bedienung 3', 'bedienung', false],
        ['Bedienung 4', 'bedienung', false],
        ['Bedienung 5', 'bedienung', false],
    ];

    public function __construct(private Mandantenkontext $kontext) {}

    public function sicherstellen(Mandant $verein): void
    {
        $this->kontext->fuer($verein, function () use ($verein) {
            if (! Saal::exists()) {
                $ort = Ort::first() ?? Ort::create(['name' => $verein->name]);
                Saal::create(['ort_id' => $ort->id, 'name' => 'Saal']);
            }

            // Nur beim allerersten Mal – löscht der Verein Stationen, kommen sie nicht wieder.
            if ($verein->stationen_angelegt_am === null) {
                if (! Station::exists()) {
                    foreach (self::STANDARD_STATIONEN as $i => [$name, $art, $mehrere]) {
                        Station::create(['name' => $name, 'art' => $art, 'mehrere_geraete' => $mehrere, 'reihenfolge' => ($i + 1) * 10]);
                    }
                }
                $verein->forceFill(['stationen_angelegt_am' => now()])->save();
            }
        });
    }

    /** Hat der Verein mehr als einen Saal? Sonst bleibt die Ort/Saal-Ebene unsichtbar (E35). */
    public function mehrereSaele(): bool
    {
        return Saal::count() > 1;
    }
}
