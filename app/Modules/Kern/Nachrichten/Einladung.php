<?php

namespace App\Modules\Kern\Nachrichten;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Einladung für ein neu angelegtes Konto: Die Person legt ihr Passwort selbst fest. */
class Einladung extends Notification
{
    public function __construct(public string $token) {}

    /** @return list<string> */
    public function via(object $empfaenger): array
    {
        return ['mail'];
    }

    public function toMail(object $empfaenger): MailMessage
    {
        $link = route('einladung.formular', ['token' => $this->token, 'email' => $empfaenger->email]);
        $tage = intdiv((int) config('auth.passwords.einladungen.expire', 4320), 1440);

        return (new MailMessage)
            ->subject('Ihr Zugang zur '.config('app.name'))
            ->greeting('Hallo '.$empfaenger->name.',')
            ->line('für Sie wurde ein Zugang zur '.config('app.name').' angelegt.')
            ->line('Ihre Anmeldung erfolgt mit dieser E-Mail-Adresse. Bitte legen Sie über den folgenden Link Ihr persönliches Passwort fest.')
            ->action('Passwort festlegen', $link)
            ->line('Der Link ist '.$tage.' Tage gültig. Danach kann der Plattformbetreiber eine neue Einladung senden.')
            ->salutation('Viele Grüße'."\n".config('app.name'));
    }
}
