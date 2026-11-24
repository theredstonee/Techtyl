<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Support\Facades\RateLimiter;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Einzige Stelle fuer ausgehende Aufrufe der Server-Werkzeuge.
 *
 * - nur https, nur Hosts aus den festen Listen unten (kein SSRF), keine Weiterleitungen
 * - kurze Timeouts, Groessenlimit fuer jede Antwort
 * - eindeutiger User-Agent ohne persoenliche Daten
 * - Antworten liegen im Panel-Cache; Limits der Dienste (Header) werden beachtet
 *
 * Einen weiteren Dienst ergaenzt man mit einem Eintrag in SERVICES (API) bzw. DOWNLOAD_HOSTS
 * (Dateien, die Wings oder das Panel laden duerfen).
 */
final class ExternalHttp
{
    public const USER_AGENT = 'Techtyl-ServerTools/0.1 (Pterodactyl extension)';

    /** Dienst => [Anzeigename, Basis-URL, eigene Obergrenze je Minute] */
    private const SERVICES = [
        'modrinth' => ['Modrinth', 'https://api.modrinth.com', 240],
        'fill' => ['PaperMC', 'https://fill.papermc.io', 120],
        'purpur' => ['Purpur', 'https://api.purpurmc.org', 120],
        'piston' => ['Mojang', 'https://piston-meta.mojang.com', 120],
        'fabric' => ['Fabric', 'https://meta.fabricmc.net', 120],
        // Profile lookup for player heads. The path is a validated player name, never a free URL.
        'mojang' => ['Mojang', 'https://api.minecraftservices.com', 50],
        // Public log paste. The caller masks the text before it is sent.
        'mclo' => ['mclo.gs', 'https://api.mclo.gs', 30],
        // Forge promotions are JSON. Maven metadata is XML and is read with text(), not get().
        'forge_files' => ['Forge', 'https://files.minecraftforge.net', 60],
        'forge_maven' => ['Forge', 'https://maven.minecraftforge.net', 60],
        'neoforge' => ['NeoForge', 'https://maven.neoforged.net', 60],
    ];

    /** Hosts, von denen Dateien geladen werden duerfen (durch Wings per pull oder durch das Panel). */
    private const DOWNLOAD_HOSTS = [
        'cdn.modrinth.com',
        'fill-data.papermc.io',
        'api.purpurmc.org',
        'piston-data.mojang.com',
        'meta.fabricmc.net',
        'files.minecraftforge.net',
        'maven.minecraftforge.net',
        'maven.neoforged.net',
    ];

    private const CONNECT_TIMEOUT = 5;
    private const TIMEOUT = 12;
    private const TEXT_TIMEOUT = 20;
    private const MAX_JSON_BYTES = 6 * 1024 * 1024;
    private const MAX_TEXT_BYTES = 8 * 1024 * 1024;

    /**
     * GET auf einen Dienst, Antwort als Array. $path muss mit "/" beginnen und darf nur bereits
     * gepruefte bzw. per rawurlencode kodierte Teile enthalten.
     *
     * @param array<string, scalar> $query
     */
    public static function get(string $service, string $path, array $query = [], int $ttl = 300): array
    {
        return self::cached($service, 'GET', self::url($service, $path, $query), null, $ttl);
    }

    /**
     * GET whose body is text, not JSON. Used for Maven metadata XML. https only, no redirects,
     * host taken from SERVICES. Empty bodies are not cached.
     */
    public static function text(string $service, string $path, array $query = [], int $ttl = 600): string
    {
        $url = self::url($service, $path, $query);
        $key = 'techtyl:tools:http:text:' . sha1('GET ' . $url);
        if ($ttl > 0) {
            $hit = Cache::get($key);
            if (is_string($hit) && $hit !== '') {
                return $hit;
            }
        }
        $data = self::request($service, 'GET', $url, null, self::MAX_TEXT_BYTES, self::TEXT_TIMEOUT, '*/*');
        if ($ttl > 0 && $data !== '') {
            Cache::put($key, $data, $ttl);
        }

        return $data;
    }

    /** POST mit JSON-Koerper (nur lesende Abfragen wie der Update-Abgleich), Antwort als Array. */
    public static function post(string $service, string $path, array $body, int $ttl = 300): array
    {
        return self::cached($service, 'POST', self::url($service, $path, []), $body, $ttl);
    }

    /**
     * GET auf eine vollstaendige Adresse, die ein Dienst selbst geliefert hat (z. B. die
     * Versionsdatei von Mojang). Der Host muss zum genannten Dienst gehoeren.
     */
    public static function getUrl(string $service, string $url, int $ttl = 300): array
    {
        $base = self::SERVICES[$service][1] ?? null;
        $parts = self::parse($url);
        if ($base === null || $parts === null || 'https://' . $parts['host'] !== $base) {
            throw ToolsException::upstream(self::label($service));
        }

        return self::cached($service, 'GET', $url, null, $ttl);
    }

    /**
     * Prueft eine Download-Adresse, bevor sie an Wings geht oder vom Panel geladen wird:
     * https, Host aus der Liste, kein Port, keine Zugangsdaten. Liefert die Adresse zurueck.
     */
    public static function downloadUrl(string $url): string
    {
        $parts = self::parse($url);
        if ($parts === null || !in_array($parts['host'], self::DOWNLOAD_HOSTS, true)) {
            Log::warning('techtyl tools: Download-Adresse abgelehnt', ['host' => $parts['host'] ?? null]);
            throw ToolsException::invalid('host_denied');
        }

        return $url;
    }

    /** Darf ein Bild von dieser Adresse im Browser angezeigt werden? (nur cdn.modrinth.com) */
    public static function imageUrl(mixed $url): ?string
    {
        if (!is_string($url) || strlen($url) > 255) {
            return null;
        }
        $parts = self::parse($url);

        return $parts !== null && $parts['host'] === 'cdn.modrinth.com' ? $url : null;
    }

    /** Laedt eine kleine Datei ins Panel (z. B. den Fabric-Starter). Wirft bei Ueberschreitung von $maxBytes. */
    public static function bytes(string $url, int $maxBytes): string
    {
        $url = self::downloadUrl($url);
        $host = self::parse($url)['host'];
        self::throttle($host, 60, $host);
        try {
            $response = self::client()->request('GET', $url, ['stream' => true, 'timeout' => 30, 'headers' => ['User-Agent' => self::USER_AGENT, 'Accept' => '*/*']]);
        } catch (GuzzleException $e) {
            Log::warning('techtyl tools: Download fehlgeschlagen', ['host' => $host, 'error' => $e->getMessage()]);
            throw ToolsException::upstream($host);
        }
        if ($response->getStatusCode() !== 200) {
            Log::warning('techtyl tools: Download mit unerwartetem Status', ['host' => $host, 'status' => $response->getStatusCode()]);
            throw ToolsException::upstream($host);
        }

        return self::read($response, $maxBytes, $host);
    }

    public static function label(string $service): string
    {
        return self::SERVICES[$service][0] ?? 'The external service';
    }

    /** @param array<string, scalar> $query */
    private static function url(string $service, string $path, array $query): string
    {
        if (!isset(self::SERVICES[$service]) || !str_starts_with($path, '/') || str_contains($path, '..') || preg_match('/[\s#?\\\\]/', $path)) {
            throw new \InvalidArgumentException('Unbekannter Dienst oder ungültiger Pfad.');
        }
        $url = self::SERVICES[$service][1] . $path;

        return $query === [] ? $url : $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array{host: string}|null Nur https ohne Port, Zugangsdaten und Fragment. */
    private static function parse(string $url): ?array
    {
        if (strlen($url) > 2000 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            return null;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || !isset($parts['host'])
            || isset($parts['port']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return null;
        }

        return ['host' => strtolower($parts['host'])];
    }

    private static function cached(string $service, string $method, string $url, ?array $body, int $ttl): array
    {
        $key = 'techtyl:tools:http:' . sha1($method . ' ' . $url . ' ' . json_encode($body));
        if ($ttl > 0) {
            $hit = Cache::get($key);
            if (is_array($hit)) {
                return $hit;
            }
        }

        $data = self::send($service, $method, $url, $body);
        if ($ttl > 0) {
            Cache::put($key, $data, $ttl);
        }

        return $data;
    }

    private static function send(string $service, string $method, string $url, ?array $body): array
    {
        $raw = self::request($service, $method, $url, $body, self::MAX_JSON_BYTES, self::TIMEOUT, 'application/json');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            Log::warning('techtyl tools: externe Antwort ist kein JSON', ['service' => $service]);
            throw ToolsException::upstream(self::label($service));
        }

        return $data;
    }

    /** Shared https GET/POST. Never follows a redirect. $accept is application/json or star-slash-star. */
    private static function request(string $service, string $method, string $url, ?array $body, int $maxBytes, int $timeout, string $accept): string
    {
        [$label, , $perMinute] = self::SERVICES[$service];
        $blockKey = 'techtyl:tools:pause:' . $service;
        $until = (int) Cache::get($blockKey, 0);
        if ($until > time()) {
            throw ToolsException::busy($label, $until - time());
        }
        self::throttle($service, $perMinute, $label);

        $options = ['stream' => true, 'timeout' => $timeout, 'headers' => ['Accept' => $accept]];
        if ($body !== null) {
            $options['json'] = $body;
        }

        try {
            $response = self::client()->request($method, $url, $options);
        } catch (GuzzleException $e) {
            Log::warning('techtyl tools: externer Aufruf fehlgeschlagen', ['service' => $service, 'error' => $e->getMessage()]);
            throw ToolsException::upstream($label);
        }

        // Limits des Dienstes beachten: ist das Kontingent fast leer oder kommt 429, pausieren
        // alle weiteren Aufrufe bis zum genannten Zeitpunkt.
        $status = $response->getStatusCode();
        $remaining = $response->getHeaderLine('X-Ratelimit-Remaining');
        $reset = (int) $response->getHeaderLine('X-Ratelimit-Reset');
        $retry = (int) $response->getHeaderLine('Retry-After');
        if ($status === 429) {
            $pause = max(5, min(600, $retry > 0 ? $retry : ($reset > 0 ? $reset : 60)));
            Cache::put($blockKey, time() + $pause, $pause);
            throw ToolsException::busy($label, $pause);
        }
        if ($remaining !== '' && ctype_digit($remaining) && (int) $remaining <= 5) {
            $pause = max(1, min(120, $reset > 0 ? $reset : 30));
            Cache::put($blockKey, time() + $pause, $pause);
        }

        if ($status === 404 || $status === 400 || $status === 410) {
            throw ToolsException::notFound('upstream_not_found', ['service' => $label]);
        }
        if ($status !== 200) {
            Log::warning('techtyl tools: externer Dienst antwortet mit Fehler', ['service' => $service, 'status' => $status]);
            throw ToolsException::upstream($label);
        }

        return self::read($response, $maxBytes, $label);
    }

    /** Liest hoechstens $max Bytes; groessere Antworten werden verworfen. */
    private static function read(ResponseInterface $response, int $max, string $label): string
    {
        $declared = $response->getHeaderLine('Content-Length');
        if ($declared !== '' && ctype_digit($declared) && (int) $declared > $max) {
            throw ToolsException::upstream($label);
        }
        $stream = $response->getBody();
        $data = '';
        while (!$stream->eof()) {
            $data .= $stream->read(65536);
            if (strlen($data) > $max) {
                $stream->close();
                Log::warning('techtyl tools: externe Antwort zu gross', ['from' => $label]);
                throw ToolsException::upstream($label);
            }
        }

        return $data;
    }

    /** Eigene Obergrenze je Minute, damit das Panel nie selbst ein Limit reisst. */
    private static function throttle(string $name, int $perMinute, string $label): void
    {
        $key = 'techtyl-tools-ext:' . $name;
        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            throw ToolsException::busy($label, RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 60);
    }

    private static function client(): Client
    {
        return new Client([
            'allow_redirects' => false,
            'http_errors' => false,
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'timeout' => self::TIMEOUT,
            'verify' => true,
            'headers' => ['User-Agent' => self::USER_AGENT, 'Accept' => 'application/json'],
        ]);
    }
}
