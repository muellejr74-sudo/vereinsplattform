<?php

namespace App\Modules\Kern\Nachrichten;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-Mail mit zeitlich begrenztem Link zum Zurücksetzen des Passworts (E23a). */
class PasswortZuruecksetzen extends Notification
{
    public function __construct(public string $token) {}

    /** @return list<string> */
    public function via(object $empfaenger): array
    {
        return ['mail'];
    }

    public function toMail(object $empfaenger): MailMessage
    {
        $link = route('passwort.formular', ['token' => $this->token, 'email' => $empfaenger->email]);
        $minuten = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Passwort zurücksetzen – '.config('app.name'))
            ->greeting('Hallo '.$empfaenger->name.',')
            ->line('für Ihr Konto wurde das Zurücksetzen des Passworts angefordert.')
            ->action('Neues Passwort festlegen', $link)
            ->line('Der Link ist '.$minuten.' Minuten gültig.')
            ->line('Falls Sie das nicht angefordert haben, können Sie diese E-Mail ignorieren. Ihr bisheriges Passwort bleibt dann gültig.')
            ->salutation('Viele Grüße'."\n".config('app.name'));
    }
}
