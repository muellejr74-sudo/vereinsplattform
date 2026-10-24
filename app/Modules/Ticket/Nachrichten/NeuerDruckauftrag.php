<?php

namespace App\Modules\Ticket\Nachrichten;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-Mail an die Druckstelle bei einem neuen Druckauftrag (E132) – ohne Namen, Telefon oder Anschrift. */
class NeuerDruckauftrag extends Notification
{
    public function __construct(
        public string $verein,
        public string $art,
        public string $fuer,
        public string $vorstellung,
        public int $anzahl,
        public string $link,
    ) {}

    /** @return list<string> */
    public function via(object $empfaenger): array
    {
        return ['mail'];
    }

    public function toMail(object $empfaenger): MailMessage
    {
        return (new MailMessage)
            ->subject('Neuer Druckauftrag – '.$this->verein)
            ->greeting('Hallo '.$empfaenger->name.',')
            ->line('es liegt ein neuer Auftrag für die Druckstelle vor:')
            ->line('**'.$this->art.'** für **'.$this->fuer.'**')
            ->line($this->vorstellung.' · '.$this->anzahl.' '.($this->anzahl === 1 ? 'Karte' : 'Karten'))
            ->action('Auftrag öffnen', $this->link)
            ->line('Diese E-Mails lassen sich unter „Mein Konto“ abschalten.')
            ->salutation('Viele Grüße'."\n".config('app.name'));
    }
}
