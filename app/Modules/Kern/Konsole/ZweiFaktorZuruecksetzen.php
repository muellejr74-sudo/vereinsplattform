<?php

namespace App\Modules\Kern\Konsole;

use App\Models\User;
use App\Modules\Kern\Dienste\Protokoll;
use App\Modules\Kern\Dienste\ZweiFaktor;
use Illuminate\Console\Command;

/**
 * Notausgang bei verlorenem Handy UND verlorenen Notfall-Codes (E16c).
 * Nur mit Zugang zum Server möglich.
 *
 * Aufruf auf lima-city:
 *   cd ~/vereinsplattform_test && /opt/lima-php/8.4/bin/php artisan vp:zweifaktor-zuruecksetzen name@beispiel.de
 */
class ZweiFaktorZuruecksetzen extends Command
{
    protected $signature = 'vp:zweifaktor-zuruecksetzen {email : E-Mail-Adresse des Kontos}';

    protected $description = 'Setzt die Zwei-Faktor-Anmeldung eines Kontos zurück (z. B. bei verlorenem Handy).';

    public function handle(ZweiFaktor $zweiFaktor): int
    {
        $benutzer = User::where('email', mb_strtolower(trim($this->argument('email'))))->first();

        if (! $benutzer) {
            $this->error('Kein Konto mit dieser E-Mail-Adresse gefunden.');

            return self::FAILURE;
        }

        if (! $benutzer->hatZweiFaktor()) {
            $this->warn('Für dieses Konto ist keine Zwei-Faktor-Anmeldung eingerichtet.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Zwei-Faktor-Anmeldung für '.$benutzer->name.' ('.$benutzer->email.') wirklich zurücksetzen?')) {
            $this->line('Abgebrochen.');

            return self::FAILURE;
        }

        $zweiFaktor->zuruecksetzen($benutzer);
        Protokoll::schreibe('zweifaktor.zurueckgesetzt', $benutzer, [], $benutzer);

        $this->info('Zurückgesetzt. Bei der nächsten Anmeldung wird die Zwei-Faktor-Anmeldung neu eingerichtet.');
        $this->line('Bitte die alte Einrichtung in der Authenticator-App löschen.');

        return self::SUCCESS;
    }
}
