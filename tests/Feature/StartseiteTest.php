<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Stufe 0/1a: Die Startseite ist erreichbar, deutschsprachig und nutzt das
 * Plattform-Stylesheet.
 */
class StartseiteTest extends TestCase
{
    public function test_startseite_ist_erreichbar(): void
    {
        $antwort = $this->get('/');

        $antwort->assertOk();
    }

    public function test_startseite_ist_deutsch_und_zeigt_den_plattformnamen(): void
    {
        $antwort = $this->get('/');

        $antwort->assertSee('<html lang="de">', false);
        $antwort->assertSee('Willkommen bei der Vereinsplattform');
    }

    public function test_startseite_ist_fuer_smartphones_vorbereitet(): void
    {
        $antwort = $this->get('/');

        $antwort->assertSee('name="viewport"', false);
        $antwort->assertSee('css/plattform.css', false);
    }

    public function test_statusseite_fuer_ueberwachung_antwortet(): void
    {
        // Laravel-Standardroute /up – für eine spätere Erreichbarkeitsüberwachung.
        $this->get('/up')->assertOk();
    }
}
