<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant;

use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;

/**
 * Prueft die beiden Adressen, die ein Admin frei eintragen kann (eigener Server, Azure).
 * Nutzer koennen keine Adresse beeinflussen. Trotzdem gilt: https, keine Zugangsdaten in der
 * Adresse, keine Ziele im internen Netz - ausser der Admin hat das ausdruecklich erlaubt.
 * Link-Local-Adressen (u. a. Cloud-Metadaten 169.254.169.254) sind immer gesperrt.
 */
class Endpoint
{
    private const AZURE_HOST = '/^[a-z0-9][a-z0-9-]{0,62}\.(openai\.azure\.com|services\.ai\.azure\.com|cognitiveservices\.azure\.com)$/';

    /**
     * Basisadresse eines OpenAI-kompatiblen Servers, z. B. https://ki.example.org/v1.
     *
     * @return string bereinigte Adresse ohne Schraegstrich am Ende
     * @throws \InvalidArgumentException mit einer Meldung fuer den Admin
     */
    public static function custom(string $url, bool $allowPrivate): string
    {
        $parts = self::parse($url);
        $scheme = $parts['scheme'];
        $host = $parts['host'];

        $private = self::classify($host);
        if ($private && !$allowPrivate) {
            throw new \InvalidArgumentException(I18n::t('assistant.url_private'));
        }
        if ($scheme === 'http' && !$private) {
            throw new \InvalidArgumentException(I18n::t('assistant.url_http'));
        }

        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        if ($path !== '' && preg_match('/^(\/(?!\.{1,2}(\/|$))[A-Za-z0-9._~-]+)+$/', $path) !== 1) {
            throw new \InvalidArgumentException(I18n::t('assistant.url_path'));
        }

        return $scheme . '://' . (str_contains($host, ':') ? '[' . $host . ']' : $host) . $port . $path;
    }

    /**
     * Azure-Endpunkt, z. B. https://meine-ressource.openai.azure.com.
     *
     * @return string https://<host>
     * @throws \InvalidArgumentException
     */
    public static function azure(string $url): string
    {
        $parts = self::parse($url);
        if ($parts['scheme'] !== 'https' || isset($parts['port'])) {
            throw new \InvalidArgumentException(I18n::t('assistant.azure_https'));
        }
        if (preg_match(self::AZURE_HOST, $parts['host']) !== 1) {
            throw new \InvalidArgumentException(I18n::t('assistant.azure_host'));
        }

        return 'https://' . $parts['host'];
    }

    /**
     * @return array{scheme: string, host: string, port?: int, path?: string}
     */
    private static function parse(string $url): array
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 200 || preg_match('/[\s\x00-\x1f\\\\]/', $url) === 1) {
            throw new \InvalidArgumentException(I18n::t('assistant.url_invalid'));
        }
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException(I18n::t('assistant.url_incomplete'));
        }
        $parts['scheme'] = strtolower($parts['scheme']);
        if (!in_array($parts['scheme'], ['https', 'http'], true)) {
            throw new \InvalidArgumentException(I18n::t('assistant.url_scheme'));
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException(I18n::t('assistant.url_parts'));
        }
        $host = strtolower(trim($parts['host'], '[]'));
        if (filter_var($host, FILTER_VALIDATE_IP) === false
            && preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/', $host) !== 1) {
            throw new \InvalidArgumentException(I18n::t('assistant.url_host'));
        }
        $parts['host'] = $host;

        return $parts;
    }

    /**
     * Loest den Host auf und ordnet ihn ein.
     *
     * @return bool true = internes Netz (localhost, private Bereiche), false = oeffentlich
     * @throws \InvalidArgumentException wenn der Host nicht aufloesbar ist oder ein gesperrtes Ziel enthaelt
     */
    public static function classify(string $host): bool
    {
        $addresses = self::resolve($host);
        if ($addresses === []) {
            throw new \InvalidArgumentException(I18n::t('assistant.url_resolve'));
        }

        $private = false;
        foreach ($addresses as $ip) {
            $ip = self::unmap($ip);
            if (self::forbidden($ip)) {
                throw new \InvalidArgumentException(I18n::t('assistant.url_forbidden'));
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false || self::carrierGrade($ip)) {
                $private = true;
            }
        }

        return $private;
    }

    /**
     * Vor jedem Aufruf: Die Adresse muss noch denselben Regeln genuegen wie beim Speichern
     * (der DNS-Eintrag kann sich geaendert haben).
     *
     * @throws \InvalidArgumentException
     */
    public static function assertAllowed(string $url, bool $allowPrivate): void
    {
        self::custom($url, $allowPrivate);
    }

    /** @return string[] */
    private static function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }
        $addresses = @gethostbynamel($host) ?: [];
        $records = @dns_get_record($host, DNS_AAAA);
        foreach (is_array($records) ? $records : [] as $record) {
            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }

    /** Link-Local, "dieses Netz", Multicast und Broadcast sind nie erlaubt. */
    private static function forbidden(string $ip): bool
    {
        $ip = self::unmap($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $long = ip2long($ip);
            $first = ($long >> 24) & 0xFF;
            $second = ($long >> 16) & 0xFF;

            return $first === 0 || ($first === 169 && $second === 254) || $first >= 224;
        }
        $packed = @inet_pton($ip);
        if ($packed === false || strlen($packed) !== 16) {
            return true;
        }
        $head = ord($packed[0]);
        $next = ord($packed[1]);

        // fe80::/10 (Link-Local), ff00::/8 (Multicast), :: (unbestimmt)
        return ($head === 0xFE && ($next & 0xC0) === 0x80) || $head === 0xFF || $packed === str_repeat("\0", 16);
    }

    /** 100.64.0.0/10 (Carrier-Grade NAT) zaehlt als intern. */
    private static function carrierGrade(string $ip): bool
    {
        $ip = self::unmap($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }
        $long = ip2long($ip);

        return (($long >> 24) & 0xFF) === 100 && ((($long >> 16) & 0xFF) & 0xC0) === 64;
    }

    /** ::ffff:a.b.c.d wird wie die IPv4-Adresse behandelt. */
    private static function unmap(string $ip): string
    {
        $packed = @inet_pton($ip);
        if ($packed !== false && strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
            return (string) inet_ntop(substr($packed, 12));
        }

        return $ip;
    }
}
