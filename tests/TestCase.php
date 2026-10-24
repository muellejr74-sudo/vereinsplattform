<?php

namespace Tests;

use App\Models\User;
use App\Modules\Kern\Dienste\Totp;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Bekanntes Testgeheimnis für die Authenticator-App. */
    protected const TEST_GEHEIMNIS = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    /** Super-Admin mit fertig eingerichteter Zwei-Faktor-Anmeldung. */
    protected function superAdmin(array $attribute = []): User
    {
        $admin = User::factory()->superAdmin()->create($attribute);
        $admin->forceFill([
            'zwei_faktor_geheimnis' => self::TEST_GEHEIMNIS,
            'zwei_faktor_notfallcodes' => [],
            'zwei_faktor_aktiv_seit' => now(),
        ])->save();

        return $admin->fresh();
    }

    protected function aktuellerCode(int $verschiebungSchritte = 0): string
    {
        return Totp::code(self::TEST_GEHEIMNIS, Totp::zeitschritt() + $verschiebungSchritte);
    }
}
