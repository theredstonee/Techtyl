<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tickets\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Pterodactyl\Models\User;

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

    public function toMail(User $user): MailMessage
    {
        // Render in the recipient's language. "de" is German, anything else uses the admin default.
        $locale = $user->language === 'de' ? 'de' : I18n::fallbackLocale();

        return I18n::using($locale, fn () => $this->compose());
    }

    private function compose(): MailMessage
    {
        $number = '#' . $this->ticketId;
        $userLink = url('/account/tickets?ticket=' . $this->ticketId);
        $teamLink = url('/admin/extensions/{identifier}?tab=tickets&ticket=' . $this->ticketId);
        $mail = (new MailMessage())->greeting(I18n::t('tickets.mail_greeting'));

        return match ($this->kind) {
            self::NEW_TICKET => $mail
                ->subject(I18n::t('tickets.mail_new_subject', ['number' => $number]))
                ->line(I18n::t('tickets.mail_new_line', ['number' => $number]))
                ->action(I18n::t('tickets.mail_view'), $teamLink),
            self::USER_REPLIED => $mail
                ->subject(I18n::t('tickets.mail_user_subject', ['number' => $number]))
                ->line(I18n::t('tickets.mail_user_line', ['number' => $number]))
                ->action(I18n::t('tickets.mail_view'), $teamLink),
            self::AUTO_CLOSED => $mail
                ->subject(I18n::t('tickets.mail_auto_subject', ['number' => $number]))
                ->line(I18n::t('tickets.mail_auto_line', ['number' => $number]))
                ->line(I18n::t('tickets.mail_auto_reopen'))
                ->action(I18n::t('tickets.mail_view'), $userLink),
            default => $mail
                ->subject(I18n::t('tickets.mail_team_subject', ['number' => $number]))
                ->line(I18n::t('tickets.mail_team_line', ['number' => $number]))
                ->line(I18n::t('tickets.mail_team_private'))
                ->action(I18n::t('tickets.mail_read'), $userLink),
        };
    }
}
