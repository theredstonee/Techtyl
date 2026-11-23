<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Log;

/**
 * Minecraft Server List Ping (1.7 and newer) from the panel to this server's own allocation.
 * The host and port come only from the allocation record. Any failure is "unknown".
 */
final class ServerListPing
{
    private const TIMEOUT = 3;

    private const PROTOCOL = 767;

    private const MAX_PACKET = 65536;

    /** @return array{status: string, online: ?int, max: ?int, sample: array<int, array{name: string, uuid: ?string}>} */
    public static function query(Server $server): array
    {
        $unknown = self::unknown();
        try {
            $allocation = $server->allocation;
        } catch (\Throwable $e) {
            return $unknown;
        }
        if ($allocation === null) {
            return $unknown;
        }
        $alias = is_string($allocation->ip_alias) ? self::host($allocation->ip_alias) : null;
        $address = self::host((string) $allocation->ip);
        $host = $alias ?? $address;
        $port = (int) $allocation->port;
        if ($host === null || $port < 1 || $port > 65535) {
            return $unknown;
        }

        return self::ping($server, $host, $port);
    }

    /** @return array{status: string, online: ?int, max: ?int, sample: array<int, array{name: string, uuid: ?string}>} */
    public static function unknown(): array
    {
        return ['status' => 'unknown', 'online' => null, 'max' => null, 'sample' => []];
    }

    /** Unsigned VarInt used by the Minecraft protocol. */
    public static function varInt(int $value): string
    {
        $out = '';
        for ($i = 0; $i < 5; $i++) {
            $byte = $value & 0x7F;
            $value >>= 7;
            if ($value !== 0) {
                $out .= chr($byte | 0x80);
            } else {
                $out .= chr($byte);

                return $out;
            }
        }

        return $out;
    }

    private static function host(string $value): ?string
    {
        $host = strtolower(trim($value));
        if ($host === '' || strlen($host) > 253 || !preg_match('/^[a-z0-9.:-]{1,253}$/', $host)) {
            return null;
        }
        if (in_array($host, ['0.0.0.0', '::', '::0', '*'], true) || str_contains($host, '..')) {
            return null;
        }

        return $host;
    }

    /** @return array{status: string, online: ?int, max: ?int, sample: array<int, array{name: string, uuid: ?string}>} */
    private static function ping(Server $server, string $host, int $port): array
    {
        $errno = 0;
        $errstr = '';
        $remote = str_contains($host, ':') ? '[' . $host . ']' : $host;
        $socket = @fsockopen($remote, $port, $errno, $errstr, self::TIMEOUT);
        if (!is_resource($socket)) {
            return self::unknown();
        }
        stream_set_timeout($socket, self::TIMEOUT);
        try {
            $body = self::varInt(0)
                . self::varInt(self::PROTOCOL)
                . self::varInt(strlen($host))
                . $host
                . pack('n', $port)
                . self::varInt(1);
            $packet = self::varInt(strlen($body)) . $body . self::varInt(1) . self::varInt(0);
            if (@fwrite($socket, $packet) !== strlen($packet)) {
                return self::unknown();
            }
            $length = self::readVarInt($socket);
            if ($length === null || $length < 2 || $length > self::MAX_PACKET) {
                return self::unknown();
            }
            $payload = self::readExact($socket, $length);
            if ($payload === null) {
                return self::unknown();
            }

            return self::parse($payload) ?? self::unknown();
        } catch (\Throwable $e) {
            Log::info('techtyl tools: Server-List-Ping fehlgeschlagen', ['server' => $server->uuid]);

            return self::unknown();
        } finally {
            fclose($socket);
        }
    }

    /** @return array{status: string, online: ?int, max: ?int, sample: array<int, array{name: string, uuid: ?string}>}|null */
    private static function parse(string $payload): ?array
    {
        $offset = 0;
        $id = self::varIntAt($payload, $offset);
        if ($id !== 0) {
            return null;
        }
        $size = self::varIntAt($payload, $offset);
        if ($size === null || $size < 2 || $offset + $size > strlen($payload)) {
            return null;
        }
        $data = json_decode(substr($payload, $offset, $size), true);
        if (!is_array($data) || !is_array($data['players'] ?? null)) {
            return null;
        }
        $online = self::count($data['players']['online'] ?? null);
        $max = self::count($data['players']['max'] ?? null);
        if ($online === null || $max === null) {
            return null;
        }
        $sample = [];
        foreach (is_array($data['players']['sample'] ?? null) ? $data['players']['sample'] : [] as $entry) {
            if (count($sample) >= 20 || !is_array($entry) || !is_string($entry['name'] ?? null)) {
                continue;
            }
            $label = $entry['name'];
            if ($label === '' || strlen($label) > 32 || preg_match('/[\x00-\x1f\x7f]/', $label)) {
                continue;
            }
            $uuid = is_string($entry['id'] ?? null) ? Players::canonicalUuid($entry['id']) : null;
            $sample[] = ['name' => $label, 'uuid' => $uuid];
        }

        return ['status' => 'ok', 'online' => $online, 'max' => $max, 'sample' => $sample];
    }

    private static function count(mixed $value): ?int
    {
        if (is_int($value) && $value >= 0 && $value <= 10000000) {
            return $value;
        }
        if (is_float($value) && $value >= 0 && $value <= 10000000 && floor($value) === $value) {
            return (int) $value;
        }

        return null;
    }

    /** @param resource $socket */
    private static function readVarInt($socket): ?int
    {
        $result = 0;
        for ($i = 0; $i < 5; $i++) {
            $byte = self::readExact($socket, 1);
            if ($byte === null) {
                return null;
            }
            $value = ord($byte);
            $result |= ($value & 0x7F) << (7 * $i);
            if (($value & 0x80) === 0) {
                return $result >= 0 ? $result : null;
            }
        }

        return null;
    }

    private static function varIntAt(string $buf, int &$offset): ?int
    {
        $result = 0;
        $length = strlen($buf);
        for ($i = 0; $i < 5; $i++) {
            if ($offset >= $length) {
                return null;
            }
            $value = ord($buf[$offset]);
            $offset++;
            $result |= ($value & 0x7F) << (7 * $i);
            if (($value & 0x80) === 0) {
                return $result >= 0 ? $result : null;
            }
        }

        return null;
    }

    /** @param resource $socket */
    private static function readExact($socket, int $length): ?string
    {
        $data = '';
        $deadline = microtime(true) + self::TIMEOUT;
        while (strlen($data) < $length) {
            if (microtime(true) > $deadline) {
                return null;
            }
            $chunk = fread($socket, $length - strlen($data));
            if (!is_string($chunk) || $chunk === '') {
                $meta = stream_get_meta_data($socket);
                if (!empty($meta['timed_out']) || !empty($meta['eof'])) {
                    return null;
                }
                usleep(20000);
                continue;
            }
            $data .= $chunk;
        }

        return $data;
    }
}
