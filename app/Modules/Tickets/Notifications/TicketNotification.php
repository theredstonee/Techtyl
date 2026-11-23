<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tickets\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Hinweis-Mail zu einem Ticket. Enthaelt bewusst weder Betreff noch Nachrichtentext (dort koennen
 * Zugangsdaten stehen), nur die Ticketnummer und einen Link ins Panel.
 */
class TicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TEAM_REPLIED = 'team-replied';
    public const NEW_TICKET = 'new-ticket';
    public const USER_REPLIED = 'user-replied';
    public const AUTO_CLOSED = 'auto-closed';

    public function __construct(public int $ticketId, public string $kind)
    {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(): MailMessage
    {
        $number = '#' . $this->ticketId;
        $userLink = url('/account/tickets?ticket=' . $this->ticketId);
        $teamLink = url('/admin/extensions/{identifier}?tab=tickets&ticket=' . $this->ticketId);
        $mail = (new MailMessage())->greeting('Hallo!');

        return match ($this->kind) {
            self::NEW_TICKET => $mail
                ->subject('Neues Ticket ' . $number)
                ->line('Ein Nutzer hat das Ticket ' . $number . ' eröffnet.')
                ->action('Ticket ansehen', $teamLink),
            self::USER_REPLIED => $mail
                ->subject('Neue Antwort im Ticket ' . $number)
                ->line('Im Ticket ' . $number . ' gibt es eine neue Antwort des Nutzers.')
                ->action('Ticket ansehen', $teamLink),
            self::AUTO_CLOSED => $mail
                ->subject('Ticket ' . $number . ' wurde geschlossen')
                ->line('Dein Ticket ' . $number . ' wurde geschlossen, weil längere Zeit keine Antwort kam.')
                ->line('Du kannst es im Panel jederzeit wieder öffnen.')
                ->action('Ticket ansehen', $userLink),
            default => $mail
                ->subject('Antwort auf dein Ticket ' . $number)
                ->line('Das Team hat auf dein Ticket ' . $number . ' geantwortet.')
                ->line('Aus Sicherheitsgründen steht der Inhalt nur im Panel.')
                ->action('Antwort lesen', $userLink),
        };
    }
}
