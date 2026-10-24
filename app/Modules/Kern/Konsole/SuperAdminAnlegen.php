<?php

namespace App\Modules\Kern\Konsole;

use App\Models\User;
use App\Modules\Kern\Dienste\Protokoll;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswortRegel;

/**
 * Legt das erste Super-Admin-Konto an (E8a) – einmalig, nur auf dem Server.
 *
 * Aufruf auf lima-city:
 *   cd ~/vereinsplattform_test && /opt/lima-php/8.4/bin/php artisan vp:superadmin-anlegen
 */
class SuperAdminAnlegen extends Command
{
    protected $signature = 'vp:superadmin-anlegen';

    protected $description = 'Legt einmalig das Super-Admin-Konto an (keine Selbstregistrierung über die Webseite).';

    public function handle(): int
    {
        if (User::where('ist_superadmin', true)->exists()) {
            $this->error('Es gibt bereits ein Super-Admin-Konto. Dieser Befehl ist nur für die erstmalige Einrichtung gedacht.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Name'));
        $email = mb_strtolower(trim((string) $this->ask('E-Mail-Adresse')));
        $passwort = (string) $this->secret('Passwort (wird nicht angezeigt, mindestens 12 Zeichen)');
        $wiederholung = (string) $this->secret('Passwort wiederholen');

        $pruefung = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $passwort, 'password_confirmation' => $wiederholung],
            [
                'name' => ['required', 'max:120'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', PasswortRegel::min(12)],
            ],
            [],
            ['name' => 'Name', 'email' => 'E-Mail-Adresse', 'password' => 'Passwort'],
        );

        if ($pruefung->fails()) {
            foreach ($pruefung->errors()->all() as $fehler) {
                $this->error($fehler);
            }

            return self::FAILURE;
        }

        $admin = new User(['name' => $name, 'email' => $email, 'aktiv' => true]);
        $admin->password = $passwort;
        $admin->forceFill(['ist_superadmin' => true])->save();

        Protokoll::schreibe('superadmin.angelegt', $admin, ['email' => $email], $admin);

        $this->info('Super-Admin-Konto angelegt.');
        $this->line('Melden Sie sich jetzt auf der Webseite an. Beim ersten Anmelden richten Sie die Zwei-Faktor-Anmeldung mit Ihrer Authenticator-App ein.');

        return self::SUCCESS;
    }
}
