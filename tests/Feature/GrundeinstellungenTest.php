<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Stufe 0: Grundeinstellungen der Plattform.
 */
class GrundeinstellungenTest extends TestCase
{
    public function test_sprache_ist_deutsch(): void
    {
        $this->assertSame('de', config('app.locale'));
        $this->assertSame('de', config('app.fallback_locale'));
    }

    public function test_zeitzone_ist_berlin(): void
    {
        $this->assertSame('Europe/Berlin', config('app.timezone'));
        $this->assertSame('Europe/Berlin', date_default_timezone_get());
    }

    public function test_php_version_reicht_aus(): void
    {
        // Ziel auf lima-city ist PHP 8.4; mindestens 8.3 wird vorausgesetzt.
        $this->assertTrue(version_compare(PHP_VERSION, '8.3.0', '>='), 'PHP 8.3 oder neuer erforderlich.');
    }
}
