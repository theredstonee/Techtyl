<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Serverseitige Pruefung von Cloudflare Turnstile. Fail closed: fehlt etwas, antwortet Cloudflare
 * nicht oder passt der Hostname nicht, gilt die Pruefung als nicht bestanden.
 */
class Turnstile
{
    public const FIELD = 'cf-turnstile-response';
    public const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** Offizielle Testschluessel von Cloudflare; sie liefern den Hostnamen "example.com". */
    private const TEST_SITE_KEY = '/^[123]x0{20}(AA|AB|BB|FF)$/';

    public static function usesTestKey(): bool
    {
        return preg_match(self::TEST_SITE_KEY, (string) Config::get('turnstile_site_key')) === 1;
    }

    /** Hostname, fuer den ein Token ausgestellt sein muss (aus app.url, nicht aus der Anfrage). */
    public static function expectedHost(): string
    {
        return strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }

    public static function verify(Request $request): bool
    {
        $token = $request->input(self::FIELD);
        $secret = Config::secret('turnstile_secret');
        if (!is_string($token) || $token === '' || strlen($token) > 2048 || $secret === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(6)
                ->connectTimeout(4)
                ->withoutRedirecting()
                ->post(self::ENDPOINT, [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => (string) $request->ip(),
                ]);
            $result = $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::warning('techtyl auth: Turnstile nicht erreichbar', ['error' => $e->getMessage()]);

            return false;
        }

        if (!is_array($result) || ($result['success'] ?? false) !== true) {
            return false;
        }
        if (self::usesTestKey()) {
            return true;
        }

        $host = strtolower((string) ($result['hostname'] ?? ''));

        return $host !== '' && hash_equals(self::expectedHost(), $host);
    }
}
