<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth;

use Illuminate\Support\Facades\Log;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Client\BlueprintClientLibrary;

/**
 * Einstellungen des Moduls (ein JSON-Eintrag "auth_settings"). Geheimnisse liegen verschluesselt
 * darin und verlassen den Server nie; nach aussen gibt es nur "gesetzt / nicht gesetzt".
 */
class Config
{
    public const TABLE = 'techtyl';
    public const RECORD = 'auth_settings';

    /** Anbieter-Schluessel => Anzeigename */
    public const PROVIDERS = ['discord' => 'Discord', 'google' => 'Google'];

    public const MIN_PASSWORD_FLOOR = 8;
    public const MIN_PASSWORD_CEILING = 64;

    /** @var array<string, mixed>|null */
    private static ?array $loaded = null;

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'registration' => false,
            'min_password' => 12,
            'invite_required' => false,
            'domains' => [],
            'turnstile' => false,
            'turnstile_login' => true,
            'turnstile_site_key' => '',
            'turnstile_secret' => '',
            'discord' => false,
            'discord_register' => false,
            'discord_client_id' => '',
            'discord_secret' => '',
            'google' => false,
            'google_register' => false,
            'google_client_id' => '',
            'google_secret' => '',
        ];
    }

    /** @return array<string, mixed> Gepruefte Einstellungen (Geheimnisse weiterhin verschluesselt). */
    public static function all(): array
    {
        if (self::$loaded === null) {
            $decoded = null;
            try {
                $stored = app(BlueprintClientLibrary::class)->dbGet(self::TABLE, self::RECORD, '{}');
                $decoded = is_string($stored) ? json_decode($stored, true) : null;
            } catch (\Throwable $e) {
                Log::warning('techtyl auth: Einstellungen nicht lesbar', ['error' => $e->getMessage()]);
            }
            self::$loaded = self::sanitize(is_array($decoded) ? $decoded : []);
        }

        return self::$loaded;
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? null;
    }

    /** @param array<string, mixed> $values Teilmenge der Einstellungen; der Rest bleibt erhalten. */
    public static function save(array $values): void
    {
        $merged = self::sanitize(array_merge(self::all(), $values));
        app(BlueprintClientLibrary::class)->dbSet(self::TABLE, self::RECORD, json_encode($merged));
        self::$loaded = $merged;
    }

    public static function flush(): void
    {
        self::$loaded = null;
    }

    /** Modul eingeschaltet? Faellt bei jedem Fehler auf "aus". */
    public static function moduleOn(): bool
    {
        try {
            return Modules::enabled('auth');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Entschluesseltes Geheimnis oder leere Zeichenkette. */
    public static function secret(string $key): string
    {
        $value = self::get($key);
        if (!is_string($value) || $value === '') {
            return '';
        }
        try {
            return (string) decrypt($value);
        } catch (\Throwable $e) {
            Log::warning('techtyl auth: Geheimnis nicht entschluesselbar', ['key' => $key]);

            return '';
        }
    }

    public static function hasSecret(string $key): bool
    {
        return self::secret($key) !== '';
    }

    /** Turnstile ist nur aktiv, wenn Schalter, Site-Key und Secret vorhanden sind. */
    public static function turnstileOn(): bool
    {
        return self::moduleOn() && self::get('turnstile') === true;
    }

    public static function turnstileProtectsLogin(): bool
    {
        return self::turnstileOn() && self::get('turnstile_login') === true;
    }

    public static function providerOn(string $provider): bool
    {
        return isset(self::PROVIDERS[$provider])
            && self::moduleOn()
            && self::get($provider) === true
            && self::get($provider . '_client_id') !== ''
            && self::hasSecret($provider . '_secret');
    }

    /** Feste Redirect-URI aus app.url; genau diese Adresse traegt der Betreiber beim Anbieter ein. */
    public static function redirectUri(string $provider): string
    {
        return rtrim((string) config('app.url'), '/') . '/extensions/{identifier}/auth/oauth/' . $provider . '/callback';
    }

    /** @return string[] Kleingeschriebene Domains; leer = alle erlaubt. */
    public static function domains(): array
    {
        return self::get('domains');
    }

    public static function domainAllowed(string $email): bool
    {
        $domains = self::domains();
        if ($domains === []) {
            return true;
        }
        $at = strrpos($email, '@');
        if ($at === false) {
            return false;
        }

        return in_array(strtolower(substr($email, $at + 1)), $domains, true);
    }

    /**
     * Zerlegt die Eingabe aus dem Admin-Feld in gueltige Domains.
     *
     * @return string[]
     */
    public static function parseDomains(string $input): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', strtolower($input)) ?: [] as $domain) {
            $domain = ltrim(trim($domain), '@');
            if ($domain !== '' && strlen($domain) <= 190 && preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $domain)) {
                $out[$domain] = true;
            }
        }

        return array_slice(array_keys($out), 0, 200);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private static function sanitize(array $input): array
    {
        $d = self::defaults();
        $bool = static fn (string $key): bool => filter_var($input[$key] ?? $d[$key], FILTER_VALIDATE_BOOLEAN);
        $text = static fn (string $key, int $max): string => is_string($input[$key] ?? null) && strlen($input[$key]) <= $max
            ? trim($input[$key])
            : '';

        $domains = [];
        if (is_array($input['domains'] ?? null)) {
            $domains = self::parseDomains(implode(' ', array_filter($input['domains'], 'is_string')));
        }

        return [
            'registration' => $bool('registration'),
            'min_password' => max(self::MIN_PASSWORD_FLOOR, min(self::MIN_PASSWORD_CEILING, (int) ($input['min_password'] ?? $d['min_password']))),
            'invite_required' => $bool('invite_required'),
            'domains' => $domains,
            'turnstile' => $bool('turnstile'),
            'turnstile_login' => $bool('turnstile_login'),
            'turnstile_site_key' => $text('turnstile_site_key', 100),
            'turnstile_secret' => $text('turnstile_secret', 2000),
            'discord' => $bool('discord'),
            'discord_register' => $bool('discord_register'),
            'discord_client_id' => $text('discord_client_id', 200),
            'discord_secret' => $text('discord_secret', 2000),
            'google' => $bool('google'),
            'google_register' => $bool('google_register'),
            'google_client_id' => $text('google_client_id', 200),
            'google_secret' => $text('google_secret', 2000),
        ];
    }
}
