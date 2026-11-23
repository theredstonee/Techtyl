<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Notifications\Messages\MailMessage;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Settings;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Registrations;

/**
 * Bestaetigungs-Mail der Registrierung. Enthaelt nur den Link, nie ein Passwort und keine vom
 * Absender der Registrierung frei waehlbaren Texte (sonst liesse sich die Mail fuer Spam missbrauchen). Wie alle Mails
 * des Panels laeuft sie ueber die Warteschlange (der Queue-Worker des Panels muss laufen).
 */
class VerifyRegistration extends Notification implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable;

    public function __construct(private string $token, private string $language = 'en')
    {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(): MailMessage
    {
        $app = (string) config('app.name', 'Panel');
        $accent = '#f0a33b';
        try {
            $accent = (string) Settings::design()['accent'];
        } catch (\Throwable $e) {
            // Standardfarbe genuegt.
        }

        $minutes = Registrations::LIFETIME_MINUTES;
        $url = rtrim((string) config('app.url'), '/') . '/extensions/{identifier}/auth/verify/' . $this->token;

        // The mail is rendered later by the queue worker, so all texts are resolved here, in the
        // language the registration was made in.
        return I18n::using($this->language, fn () => (new MailMessage())
            ->subject(I18n::t('auth.mail_subject', ['app' => $app]))
            ->view([
                'html' => 'blueprint.extensions.{identifier}.auth.mail.verify',
                'text' => 'blueprint.extensions.{identifier}.auth.mail.verify-text',
            ], [
                'lang' => I18n::locale(),
                'app' => $app,
                'url' => $url,
                'accent' => $accent,
                'ink' => self::inkFor($accent),
                'title' => I18n::t('auth.mail_title'),
                'preheader' => I18n::t('auth.mail_preheader', ['minutes' => $minutes]),
                'heading' => I18n::t('auth.mail_heading'),
                'hello' => I18n::t('auth.mail_hello'),
                'body' => I18n::t('auth.mail_body', ['app' => $app]),
                'textOpen' => I18n::t('auth.mail_text_open'),
                'button' => I18n::t('auth.mail_button'),
                'valid' => I18n::t('auth.mail_valid', ['minutes' => $minutes]),
                'validText' => I18n::t('auth.mail_valid_text', ['minutes' => $minutes]),
                'ignore' => I18n::t('auth.mail_ignore'),
            ]));
    }

    /** Dunkle oder helle Schrift auf der Akzentfarbe, je nach deren Helligkeit. */
    public static function inkFor(string $hex): string
    {
        if (preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m) !== 1) {
            return '#17130b';
        }
        $linear = array_map(static function (string $channel): float {
            $c = hexdec($channel) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, [$m[1], $m[2], $m[3]]);
        $luminance = 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];

        return $luminance > 0.36 ? '#17130b' : '#ffffff';
    }
}
