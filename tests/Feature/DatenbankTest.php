<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Stufe 0: Die Datenbank lässt sich vollständig aufbauen.
 *
 * In der automatischen Prüfung auf GitHub läuft dieser Test gegen MySQL –
 * dieselbe Datenbankart wie bei lima-city.
 */
class DatenbankTest extends TestCase
{
    use RefreshDatabase;

    public function test_alle_tabellen_werden_angelegt(): void
    {
        foreach (['users', 'sessions', 'cache', 'jobs', 'migrations', 'mandanten', 'mitgliedschaften', 'protokoll',
            'plattformtexte', 'vereinstexte', 'vertraute_geraete', 'einladung_tokens', 'rechtevergaben', 'mandant_module',
            'orte', 'saele', 'veranstaltungen', 'vorstellungen', 'stationen', 'stationsbesetzungen', 'stationsgeraete', 'qr_codes', 'tischzuordnungen',
            'ticketkategorien', 'preisabweichungen', 'verkaufsstellen', 'verkaufsstellen_benutzer', 'kartenauftraege', 'auftragspositionen', 'tickets', 'nummernkreise'] as $tabelle) {
            $this->assertTrue(Schema::hasTable($tabelle), "Tabelle {$tabelle} fehlt.");
        }
    }

    public function test_datenbank_ist_erreichbar(): void
    {
        $this->assertSame(1, (int) DB::selectOne('select 1 as wert')->wert);
    }
}
