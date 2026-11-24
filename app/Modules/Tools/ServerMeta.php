<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Was die Werkzeuge ueber einen Server wissen: Typ, erkannte Version, Jar-Datei, verfuegbare
 * Werkzeuge und die Rechte des Nutzers. Die Erkennung kostet Wings-Aufrufe und liegt deshalb
 * kurz im Cache; flush() nach jeder Aenderung an der Jar aufrufen.
 */
final class ServerMeta
{
    /** Recht je Faehigkeit (vorhandene Panel-Rechte, eigene sind nicht moeglich). */
    public const PERMISSIONS = [
        'read' => 'file.read',
        'read_content' => 'file.read-content',
        'install' => 'file.create',
        'update_files' => 'file.update',
        'remove' => 'file.delete',
        'archive' => 'file.archive',
        'console' => 'control.console',
        'start' => 'control.start',
        'stop' => 'control.stop',
        'restart' => 'control.restart',
        'version' => 'startup.update',
        'startup_read' => 'startup.read',
        'image' => 'startup.docker-image',
        'backup' => 'backup.create',
        'reinstall' => 'settings.reinstall',
    ];

    private const CACHE_SECONDS = 90;
    private const JAR_PATTERN = '/^[\w.-]{1,60}\.jar$/';

    /** Fuer GET /meta. */
    public static function describe(Server $server, User $user): array
    {
        $resolved = ServerTypes::forServer($server);
        $type = $resolved['type'];
        $content = ServerTypes::CONTENT[$type] ?? null;

        $tools = [];
        foreach (array_keys(ServerTypes::TOOLS) as $tool) {
            $tools[$tool] = ['available' => ServerTypes::toolAvailable($tool, $type)];
        }
        $tools['versions']['planned'] = in_array($type, ServerTypes::VERSION_PLANNED, true);
        if ($content !== null) {
            $tools['content'] += ['kind' => $content['kind'], 'directory' => $content['directory'], 'loaders' => $content['loaders']];
        }

        $permissions = [];
        foreach (self::PERMISSIONS as $key => $permission) {
            $permissions[$key] = $user->can($permission, $server);
        }

        $detected = ['version' => null, 'build' => null, 'source' => null, 'loader_version' => null];
        if ($type !== ServerTypes::NONE && $type !== 'other' && $permissions['read']) {
            try {
                $detected = self::detect($server, $type);
            } catch (\Throwable $e) {
                // Node nicht erreichbar: Typ und Rechte reichen fuer die Seite, Version bleibt offen.
                Log::info('techtyl tools: Version nicht erkennbar', ['server' => $server->uuid, 'error' => substr($e->getMessage(), 0, 200)]);
            }
        }

        return [
            'type' => $type,
            'type_label' => ServerTypes::label($type),
            'type_source' => $resolved['source'],
            'minecraft' => $type !== ServerTypes::NONE,
            'egg' => (string) $server->egg->name,
            // Bei Velocity ist "version" die Proxy-Version, sonst die Minecraft-Version.
            'version' => $detected['version'],
            'build' => $detected['build'],
            'version_source' => $detected['source'],
            'loader' => $content['loaders'][0] ?? ($type === 'vanilla' ? 'vanilla' : null),
            'loader_version' => $detected['loader_version'],
            'tools' => $tools,
            'permissions' => $permissions,
        ];
    }

    /** Minecraft-Version fuer Filter (null bei Velocity und wenn unbekannt). */
    public static function gameVersion(Server $server): ?string
    {
        $type = ServerTypes::forServer($server)['type'];
        if ($type === 'velocity' || $type === ServerTypes::NONE || $type === 'other') {
            return null;
        }
        try {
            return self::detect($server, $type)['version'];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Name der Server-Jar laut Egg-Variable SERVER_JARFILE, sonst server.jar. */
    public static function jarName(Server $server): string
    {
        $value = self::variable($server, 'SERVER_JARFILE');

        return $value !== null && preg_match(self::JAR_PATTERN, $value) ? $value : 'server.jar';
    }

    public static function variable(Server $server, string $key): ?string
    {
        foreach ($server->variables as $variable) {
            if ($variable->env_variable === $key) {
                $value = $variable->server_value;

                return is_string($value) && $value !== '' ? $value : null;
            }
        }

        return null;
    }

    public static function flush(Server $server): void
    {
        Cache::forget(self::cacheKey($server));
    }

    /**
     * Reihenfolge: eigener letzter Wechsel (wenn die Jar noch dieselbe Groesse hat), dann
     * version_history.json, der Ordner versions/, die Egg-Variable und zuletzt der Abgleich der
     * Jar-Groesse mit den Builds des Dienstes.
     *
     * @return array{version: ?string, build: ?string, source: ?string, loader_version: ?string}
     */
    public static function detect(Server $server, string $type): array
    {
        return Cache::remember(self::cacheKey($server), self::CACHE_SECONDS, function () use ($server, $type) {
            $wings = Wings::for($server);
            $jarName = self::jarName($server);
            $jar = null;
            $hasVersionsDir = false;
            $hasHistory = false;
            foreach ($wings->list('/') as $entry) {
                if ($entry['file'] && $entry['name'] === $jarName) {
                    $jar = $entry;
                } elseif (!$entry['file'] && $entry['name'] === 'versions') {
                    $hasVersionsDir = true;
                } elseif ($entry['file'] && $entry['name'] === 'version_history.json') {
                    $hasHistory = true;
                }
            }
            $result = ['version' => null, 'build' => null, 'source' => null, 'loader_version' => null];

            // 1. Eigener Wechsel.
            $last = DB::table(Tasks::TABLE)->where('server_id', $server->id)
                ->whereIn('kind', ['version.switch', 'software.switch'])
                ->where('status', Tasks::DONE)->orderByDesc('id')->value('result');
            $last = is_string($last) ? json_decode($last, true) : null;
            if (is_array($last) && $jar !== null && ($last['type'] ?? null) === $type && (int) ($last['jar_size'] ?? -1) === $jar['size']
                && VersionSources::validId($last['version'] ?? null)) {
                return [
                    'version' => $last['version'],
                    'build' => $type === 'fabric' || $type === 'vanilla' ? null : (VersionSources::validId($last['build'] ?? null) ? $last['build'] : null),
                    'source' => 'techtyl',
                    'loader_version' => $type === 'fabric' && VersionSources::validId($last['build'] ?? null) ? $last['build'] : null,
                ];
            }

            // 2. version_history.json (Paper, Purpur, Folia schreiben sie beim Start).
            if ($hasHistory && in_array($type, ['paper', 'purpur', 'folia'], true)) {
                $history = json_decode((string) $wings->read('/version_history.json', 8192), true);
                $current = is_array($history) ? ($history['currentVersion'] ?? null) : null;
                if (is_string($current) && preg_match('/\(MC: ([A-Za-z0-9][A-Za-z0-9.+_ -]{0,30})\)/', $current, $m)) {
                    $version = str_replace(' ', '-', strtolower(trim($m[1])));
                    if (VersionSources::validId($version)) {
                        $result['version'] = $version;
                        $result['source'] = 'files';
                        if (preg_match('/^(?:git-\w+-)?(?:[\w.]+-)?(\d{1,6})-[0-9a-f]{6,}/i', $current, $b) || preg_match('/^git-\w+-(\d{1,6})\b/i', $current, $b)) {
                            $result['build'] = $b[1];
                        }
                    }
                }
            }

            // 3. Ordner versions/ (Paper und Vanilla entpacken dorthin; der juengste Eintrag zaehlt).
            if ($result['version'] === null && $hasVersionsDir && $type !== 'velocity') {
                $newest = null;
                foreach ($wings->list('/versions') as $entry) {
                    if (!$entry['file'] && VersionSources::validId($entry['name']) && preg_match('/^\d/', $entry['name'])
                        && ($newest === null || strcmp($entry['modified'], $newest['modified']) > 0)) {
                        $newest = $entry;
                    }
                }
                if ($newest !== null) {
                    $result['version'] = $newest['name'];
                    $result['source'] = 'files';
                }
            }

            // 4. Egg-Variable, wenn sie eine feste Version nennt.
            $variable = match ($type) {
                'vanilla' => 'VANILLA_VERSION',
                'velocity' => 'VELOCITY_VERSION',
                'fabric', 'forge', 'neoforge' => 'MC_VERSION',
                default => 'MINECRAFT_VERSION',
            };
            $value = self::variable($server, $variable);
            $fixed = $value !== null && VersionSources::validId($value) && preg_match('/^\d/', $value) ? $value : null;
            if ($result['version'] === null && $fixed !== null) {
                $result['version'] = $fixed;
                $result['source'] = 'variable';
            }
            if ($type === 'fabric') {
                $loader = self::variable($server, 'LOADER_VERSION');
                $result['loader_version'] = $loader !== null && VersionSources::validId($loader) && preg_match('/^\d/', $loader) ? $loader : null;
            }
            if ($type === 'forge') {
                $forge = self::variable($server, 'FORGE_VERSION');
                if ($forge !== null && VersionSources::validId($forge)) {
                    $result['build'] = $forge;
                }
            }
            if ($type === 'neoforge') {
                $neo = self::variable($server, 'NEOFORGE_VERSION');
                if ($neo !== null && VersionSources::validId($neo)) {
                    $result['build'] = $neo;
                    if ($result['version'] === null) {
                        $game = VersionSources::neoforgeGame($neo);
                        if ($game !== null) {
                            $result['version'] = $game;
                            $result['source'] = 'variable';
                        }
                    }
                }
                if ($result['version'] === null) {
                    $alt = self::variable($server, 'MINECRAFT_VERSION');
                    if ($alt !== null && VersionSources::validId($alt) && preg_match('/^\d/', $alt)) {
                        $result['version'] = $alt;
                        $result['source'] = 'variable';
                    }
                }
            }

            // 5. Abgleich der Jar-Groesse mit dem Dienst (liefert bei Paper auch den Build).
            if ($jar !== null && $result['build'] === null) {
                try {
                    if (in_array($type, ['paper', 'folia', 'velocity'], true)) {
                        $candidates = $result['version'] !== null
                            ? [$result['version']]
                            : array_slice(array_column(array_filter(VersionSources::versions($type), fn ($v) => $v['stable']), 'id'), 0, 3);
                        foreach ($candidates as $candidate) {
                            $match = VersionSources::fillBuildBySize($type, $candidate, $jar['size']);
                            if ($match !== null) {
                                $result['build'] = $match['id'];
                                if ($result['version'] === null) {
                                    $result['version'] = $candidate;
                                    $result['source'] = 'jar';
                                }
                                break;
                            }
                        }
                    } elseif ($type === 'vanilla' && $result['version'] === null) {
                        foreach (array_slice(VersionSources::versions('vanilla'), 0, 3) as $candidate) {
                            if (VersionSources::vanillaSize($candidate['id']) === $jar['size']) {
                                $result['version'] = $candidate['id'];
                                $result['source'] = 'jar';
                                break;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // Dienst nicht erreichbar: dann bleibt es bei dem, was die Dateien sagen.
                }
            }

            return $result;
        });
    }

    private static function cacheKey(Server $server): string
    {
        return 'techtyl:tools:detect:' . $server->id;
    }
}
