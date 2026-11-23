<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\OAuth;

use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Config;

/**
 * Authorization-Code-Flow fuer Discord und Google ohne Zusatzpakete.
 * Discord: state. Google (OpenID Connect): state, PKCE (S256) und nonce.
 */
class Providers
{
    private const DISCORD_AUTHORIZE = 'https://discord.com/oauth2/authorize';
    private const DISCORD_TOKEN = 'https://discord.com/api/oauth2/token';
    private const DISCORD_USER = 'https://discord.com/api/users/@me';
    private const GOOGLE_AUTHORIZE = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const GOOGLE_TOKEN = 'https://oauth2.googleapis.com/token';
    private const GOOGLE_ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];

    public static function transport(): Transport
    {
        return app()->bound(Transport::class) ? app(Transport::class) : new HttpTransport();
    }

    public static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /** Adresse, zu der der Browser fuer die Anmeldung beim Anbieter geschickt wird. */
    public static function authorizeUrl(string $provider, string $state, string $verifier, string $nonce): string
    {
        $query = [
            'response_type' => 'code',
            'client_id' => (string) Config::get($provider . '_client_id'),
            'redirect_uri' => Config::redirectUri($provider),
            'state' => $state,
        ];

        if ($provider === 'google') {
            return self::GOOGLE_AUTHORIZE . '?' . http_build_query($query + [
                'scope' => 'openid email profile',
                'nonce' => $nonce,
                'code_challenge' => self::challenge($verifier),
                'code_challenge_method' => 'S256',
                'prompt' => 'select_account',
            ]);
        }

        return self::DISCORD_AUTHORIZE . '?' . http_build_query($query + [
            'scope' => 'identify email',
            'prompt' => 'none',
        ]);
    }

    /**
     * Tauscht den Code ein und liefert die Identitaet beim Anbieter.
     *
     * @return array{id: string, email: string, verified: bool, name: string, label: string}|null
     */
    public static function identity(string $provider, string $code, string $verifier, string $nonce): ?array
    {
        $form = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => (string) Config::get($provider . '_client_id'),
            'client_secret' => Config::secret($provider . '_secret'),
            'redirect_uri' => Config::redirectUri($provider),
        ];

        return $provider === 'google'
            ? self::google($form + ['code_verifier' => $verifier], $nonce)
            : self::discord($form);
    }

    /** @param array<string, string> $form */
    private static function discord(array $form): ?array
    {
        $token = self::transport()->postForm(self::DISCORD_TOKEN, $form);
        $access = $token['access_token'] ?? null;
        if (!is_string($access) || $access === '') {
            return null;
        }

        $user = self::transport()->getJson(self::DISCORD_USER, $access);
        $id = $user['id'] ?? null;
        if (!is_string($id) || preg_match('/^\d{5,25}$/', $id) !== 1) {
            return null;
        }
        $name = self::text($user['global_name'] ?? null) ?: self::text($user['username'] ?? null);

        return [
            'id' => $id,
            'email' => self::email($user['email'] ?? null),
            'verified' => ($user['verified'] ?? false) === true,
            'name' => $name,
            'label' => self::text($user['username'] ?? null) ?: $name,
        ];
    }

    /** @param array<string, string> $form */
    private static function google(array $form, string $nonce): ?array
    {
        $token = self::transport()->postForm(self::GOOGLE_TOKEN, $form);
        $jwt = $token['id_token'] ?? null;
        if (!is_string($jwt) || strlen($jwt) > 8192) {
            return null;
        }

        // Das ID-Token kommt direkt und TLS-gesichert vom Token-Endpunkt von Google; laut
        // OpenID Connect Core 3.1.3.7 darf dann die TLS-Pruefung die Signaturpruefung ersetzen.
        // Aussteller, Empfaenger, Ablauf und nonce werden geprueft.
        $segments = explode('.', $jwt);
        if (count($segments) !== 3) {
            return null;
        }
        $claims = json_decode((string) base64_decode(strtr($segments[1], '-_', '+/'), true), true);
        if (!is_array($claims)) {
            return null;
        }

        $audience = $claims['aud'] ?? null;
        $id = $claims['sub'] ?? null;
        $valid = in_array($claims['iss'] ?? null, self::GOOGLE_ISSUERS, true)
            && is_string($audience) && hash_equals((string) Config::get('google_client_id'), $audience)
            && is_numeric($claims['exp'] ?? null) && (int) $claims['exp'] > time() - 30
            && is_string($claims['nonce'] ?? null) && hash_equals($nonce, $claims['nonce'])
            && is_string($id) && preg_match('/^[A-Za-z0-9._-]{1,64}$/', $id) === 1;
        if (!$valid) {
            return null;
        }

        $email = self::email($claims['email'] ?? null);

        return [
            'id' => $id,
            'email' => $email,
            'verified' => in_array($claims['email_verified'] ?? false, [true, 'true'], true),
            'name' => self::text($claims['name'] ?? null),
            'label' => $email,
        ];
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? ''), 0, 120) : '';
    }

    private static function email(mixed $value): string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';

        return $value !== '' && strlen($value) <= 191 && filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? $value : '';
    }
}
