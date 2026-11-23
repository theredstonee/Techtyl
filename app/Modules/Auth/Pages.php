<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth;

use Illuminate\Http\Response;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Design;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Settings;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Notifications\VerifyRegistration;

/** Rendert die eigenen Seiten des Moduls im Techtyl-Design und haelt die Texte der Hinweisseiten. */
class Pages
{
    public const BASE = '/extensions/{identifier}/auth';
    private const VIEWS = 'blueprint.extensions.{identifier}.auth.';
    public const TURNSTILE_ATTRIBUTE = 'techtyl_auth_turnstile';

    /**
     * Notice pages: code (part of the URL) => [tone, text key, target of the main action, label key].
     * Tone: "info" or "warn". Texts: auth.notice_<key>_title / auth.notice_<key>_text.
     */
    public const NOTICES = [
        'geschlossen' => ['info', 'closed', '/auth/login', 'to_login'],
        'link' => ['warn', 'link', self::BASE . '/register', 'register_again'],
        'einladung' => ['warn', 'invite', self::BASE . '/register', 'register_again'],
        'domain' => ['warn', 'domain', self::BASE . '/register', 'to_register'],
        'anbieter-aus' => ['info', 'provider_off', '/auth/login', 'to_login'],
        'anbieter-abbruch' => ['warn', 'provider_cancel', '/auth/login', 'to_login'],
        'anbieter-fehler' => ['warn', 'provider_error', '/auth/login', 'to_login'],
        'anbieter-unbekannt' => ['info', 'provider_unknown', '/auth/login', 'to_login'],
        'fehler' => ['warn', 'error', '/auth/login', 'to_login'],
    ];

    /** Translates a text of this module. */
    public static function t(string $key, array $replace = []): string
    {
        return I18n::t('auth.' . $key, $replace);
    }

    /** @param array<string, mixed> $data */
    public static function view(string $name, array $data = [], int $status = 200, bool $turnstile = false): Response
    {
        // Nur Seiten mit Widget geben Cloudflare in der CSP frei (siehe SecurityHeaders).
        $turnstile = $turnstile && Config::turnstileOn();
        request()->attributes->set(self::TURNSTILE_ATTRIBUTE, $turnstile);
        $design = Settings::designForPage(auth()->id());
        $attributes = Design::attributes($design);

        return response()->view(self::VIEWS . $name, $data + [
            'base' => self::BASE,
            'lang' => I18n::locale(),
            't' => static fn (string $key, array $replace = []): string => self::t($key, $replace),
            'appName' => (string) config('app.name', 'Panel'),
            'variant' => $attributes['data-techtyl-variant'],
            'motion' => $attributes['data-techtyl-motion'],
            'turnstileKey' => $turnstile ? (string) Config::get('turnstile_site_key') : '',
            'turnstileTheme' => $attributes['data-techtyl-variant'] === 'papier' ? 'light' : 'dark',
        ], $status);
    }

    public static function notice(string $code, int $status = 200): Response
    {
        $notice = self::NOTICES[$code] ?? self::NOTICES['fehler'];

        return self::view('notice', [
            'tone' => $notice[0],
            'title' => self::t('notice_' . $notice[1] . '_title'),
            'text' => self::t('notice_' . $notice[1] . '_text'),
            'actionUrl' => $notice[2],
            'actionLabel' => self::t($notice[3]),
        ], $status);
    }

    /** Design-Variablen als Stylesheet (statt Inline-Stil, damit die CSP ohne 'unsafe-inline' auskommt). */
    public static function themeCss(): string
    {
        $design = Settings::designForPage(auth()->id());

        return Design::toCss($design) . ':root{--techtyl-accent-ink:' . VerifyRegistration::inkFor((string) $design['accent']) . ';}';
    }
}
