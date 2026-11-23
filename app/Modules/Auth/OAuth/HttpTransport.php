<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\OAuth;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/** Echte HTTP-Aufrufe: nur https, nur fest erlaubte Hosts, keine Weiterleitungen, kurze Zeitlimits. */
class HttpTransport implements Transport
{
    private const HOSTS = ['discord.com', 'oauth2.googleapis.com'];

    public function postForm(string $url, array $form): ?array
    {
        return $this->run($url, fn ($http) => $http->asForm()->post($url, $form));
    }

    public function getJson(string $url, string $bearer): ?array
    {
        return $this->run($url, fn ($http) => $http->withToken($bearer)->get($url));
    }

    private function run(string $url, \Closure $send): ?array
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || !in_array($parts['host'] ?? '', self::HOSTS, true)) {
            return null;
        }

        try {
            $response = $send(Http::acceptJson()->timeout(8)->connectTimeout(4)->withoutRedirecting());
            if (!$response->successful()) {
                // Nur Host und Status; Antworttexte koennen Codes oder Token enthalten.
                Log::warning('techtyl auth: Anbieter antwortet mit Fehler', ['host' => $parts['host'], 'status' => $response->status()]);

                return null;
            }
            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (\Throwable $e) {
            Log::warning('techtyl auth: Anbieter nicht erreichbar', ['host' => $parts['host'], 'error' => get_class($e)]);

            return null;
        }
    }
}
