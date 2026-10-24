<?php

namespace Tests;

use App\Models\User;
use App\Modules\Kern\Dienste\Totp;
use App\Modules\Kern\Mandanten\Mandantenkontext;
use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Rechtevergabe;
use App\Modules\Kern\Rechte\Rechtekatalog;
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

    /** Person mit Rechten in einem Verein (Mitgliedschaft wird angelegt). */
    protected function personMitRechten(Mandant $verein, array $rechte, array $attribute = []): User
    {
        $person = User::factory()->create($attribute);
        $person->vereine()->attach($verein->id);
        app(Mandantenkontext::class)->fuer($verein, function () use ($person, $rechte) {
            foreach ($rechte as $recht) {
                Rechtevergabe::create(['user_id' => $person->id, 'recht' => $recht]);
            }
        });

        return $person;
    }

    /** Vereinsadmin nach Vorlage. */
    protected function vereinsadmin(Mandant $verein, array $attribute = []): User
    {
        return $this->personMitRechten($verein, Rechtekatalog::vorlageVereinsadmin(), $attribute);
    }

    protected function aktuellerCode(int $verschiebungSchritte = 0): string
    {
        return Totp::code(self::TEST_GEHEIMNIS, Totp::zeitschritt() + $verschiebungSchritte);
    }
}
