<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;

/**
 * Plugins und Mods eines Servers: was installiert ist (eigene Tabelle, abgeglichen mit dem
 * Ordnerinhalt), Installieren, Aktualisieren und Entfernen. Download-Adressen ermittelt immer
 * der Server aus Projekt- und Versions-ID; der Browser liefert nie eine Adresse.
 */
final class Content
{
    public const TABLE = 'techtyl_tools_installed_content';

    /** Praefix und Endung der Zwischendateien waehrend eines Downloads. */
    public const TEMP_PREFIX = '.techtyl-';
    public const TEMP_SUFFIX = '.part';

    /** @return array{kind: string, directory: string, loaders: string[]} */
    public static function capability(Server $server): array
    {
        $type = ServerTypes::forServer($server)['type'];
        $capability = ServerTypes::CONTENT[$type] ?? null;
        if ($capability === null) {
            throw ToolsException::conflict('content_unsupported');
        }

        return $capability;
    }

    /**
     * Installierte Dateien: Abgleich der eigenen Tabelle mit dem Ordner ueber Dateiname und
     * Groesse. Dateien ohne passenden Eintrag gelten als "unbekannt" (von Hand hochgeladen oder
     * veraendert). Eintraege, deren Datei fehlt, werden entfernt.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function installed(Server $server): array
    {
        $capability = self::capability($server);
        $directory = $capability['directory'];
        $rows = DB::table(self::TABLE)->where('server_id', $server->id)->where('directory', $directory)->get()->keyBy('filename');

        $list = [];
        $present = [];
        foreach (Wings::for($server)->list('/' . $directory) as $entry) {
            if (!$entry['file'] || self::isTemp($entry['name']) || !preg_match('/\.jar(\.disabled)?$/i', $entry['name'])) {
                continue;
            }
            $row = $rows->get($entry['name']);
            $tracked = $row !== null && (int) $row->size === $entry['size'];
            if ($row !== null) {
                $present[] = $entry['name'];
            }
            $list[] = [
                'filename' => $entry['name'],
                'size' => $entry['size'],
                'modified_at' => $entry['modified'],
                'state' => $tracked ? 'tracked' : ($row !== null ? 'changed' : 'unknown'),
                'project_id' => $tracked ? $row->project_id : null,
                'title' => $tracked ? $row->title : null,
                'icon_url' => $tracked ? ExternalHttp::imageUrl($row->icon_url) : null,
                'version_id' => $tracked ? $row->version_id : null,
                'version_number' => $tracked ? $row->version_number : null,
                'installed_at' => $tracked ? (string) $row->created_at : null,
            ];
        }

        $missing = $rows->keys()->diff($present)->all();
        if ($missing !== []) {
            DB::table(self::TABLE)->where('server_id', $server->id)->where('directory', $directory)->whereIn('filename', $missing)->delete();
        }

        usort($list, fn ($a, $b) => strcasecmp($a['title'] ?? $a['filename'], $b['title'] ?? $b['filename']));

        return $list;
    }

    /** @return array<string, string> Projekt-ID => installierte Versions-ID (nur Eintraege der eigenen Tabelle). */
    public static function installedProjects(Server $server): array
    {
        return DB::table(self::TABLE)->where('server_id', $server->id)->pluck('version_id', 'project_id')->all();
    }

    /**
     * Verfuegbare Updates fuer alles, was Techtyl installiert hat (ein gebuendelter Aufruf).
     *
     * @return array{checked: bool, reason: ?string, updates: array<string, array{version_id: string, version_number: string, channel: string}>}
     */
    public static function updates(Server $server): array
    {
        $capability = self::capability($server);
        $gameVersion = ServerMeta::gameVersion($server);
        $velocity = ServerTypes::forServer($server)['type'] === 'velocity';
        if ($gameVersion === null && !$velocity) {
            return ['checked' => false, 'reason' => I18n::t('tools.updates_no_version'), 'updates' => []];
        }

        $rows = [];
        foreach (self::installed($server) as $item) {
            if ($item['state'] === 'tracked') {
                $rows[$item['filename']] = $item;
            }
        }
        if ($rows === []) {
            return ['checked' => true, 'reason' => null, 'updates' => []];
        }
        $hashes = DB::table(self::TABLE)->where('server_id', $server->id)->where('directory', $capability['directory'])
            ->whereIn('filename', array_keys($rows))->pluck('sha1', 'filename')->all();

        $latest = $velocity
            ? self::latestWithoutGameVersion($rows, $capability['loaders'])
            : Modrinth::updates(array_values($hashes), $capability['loaders'], $gameVersion);

        $updates = [];
        foreach ($rows as $filename => $item) {
            $candidate = $velocity ? ($latest[$item['project_id']] ?? null) : ($latest[$hashes[$filename] ?? ''] ?? null);
            if ($candidate !== null && $candidate['id'] !== $item['version_id'] && $candidate['project_id'] === $item['project_id']
                && preg_match(Modrinth::FILE_PATTERN, $candidate['file']['filename'])) {
                $updates[$filename] = [
                    'version_id' => $candidate['id'],
                    'version_number' => $candidate['number'],
                    'channel' => $candidate['channel'],
                ];
            }
        }

        return ['checked' => true, 'reason' => null, 'updates' => $updates];
    }

    /**
     * Prueft Projekt und Version bei Modrinth und legt den Vorgang an. Ist das Projekt schon
     * installiert, ersetzt die neue Datei die alte (erst nach erfolgreichem Download).
     */
    public static function queueInstall(Server $server, User $user, string $projectId, string $versionId): object
    {
        $capability = self::capability($server);
        $directory = $capability['directory'];

        $version = Modrinth::version($versionId);
        if ($version['project_id'] !== $projectId) {
            throw ToolsException::invalid('version_wrong_project');
        }
        if (array_intersect($version['loaders'], $capability['loaders']) === []) {
            throw ToolsException::invalid('version_wrong_loader', ['type' => ServerTypes::label(ServerTypes::forServer($server)['type'])]);
        }
        $file = $version['file'];
        if (!preg_match(Modrinth::FILE_PATTERN, $file['filename']) || str_starts_with($file['filename'], self::TEMP_PREFIX)) {
            throw ToolsException::invalid('file_name');
        }
        if ($file['size'] > 256 * 1024 * 1024) {
            throw ToolsException::invalid('file_too_large');
        }
        $url = ExternalHttp::downloadUrl($file['url']);
        $project = Modrinth::project($projectId);

        $existing = DB::table(self::TABLE)->where('server_id', $server->id)->where('directory', $directory)->where('project_id', $projectId)->first();
        if ($existing !== null && $existing->version_id === $versionId
            && Wings::for($server)->stat('/' . $directory, $existing->filename) !== null) {
            throw ToolsException::conflict('same_version');
        }
        // Eine fremde Datei gleichen Namens wird nicht ueberschrieben.
        $sameName = DB::table(self::TABLE)->where('server_id', $server->id)->where('directory', $directory)->where('filename', $file['filename'])->first();
        if ($sameName === null && Wings::for($server)->stat('/' . $directory, $file['filename']) !== null) {
            throw ToolsException::conflict('foreign_file', ['directory' => $directory, 'file' => $file['filename']]);
        }

        $payload = [
            'directory' => $directory,
            'kind' => $capability['kind'],
            'project_id' => $projectId,
            'version_id' => $versionId,
            'version_number' => $version['number'],
            'title' => $project['title'] !== '' ? $project['title'] : $file['filename'],
            'icon_url' => $project['icon_url'],
            'filename' => $file['filename'],
            'temp' => self::TEMP_PREFIX . bin2hex(random_bytes(6)) . self::TEMP_SUFFIX,
            'url' => $url,
            'size' => $file['size'],
            'sha1' => $file['sha1'],
            'replaces' => $existing?->filename,
            'user_id' => $user->id,
            'public' => [
                'project_id' => $projectId,
                'version_id' => $versionId,
                'version_number' => $version['number'],
                'filename' => $file['filename'],
                'update' => $existing !== null,
            ],
        ];

        return Tasks::create($server, $user, 'content.install', $payload['title'], $payload, function (Collection $active) use ($projectId) {
            foreach ($active as $row) {
                $other = json_decode((string) $row->payload, true);
                if ($row->kind === 'content.install' && ($other['project_id'] ?? null) === $projectId) {
                    throw ToolsException::conflict('already_installing');
                }
            }
        });
    }

    /** Aktualisiert eine installierte Datei auf die neueste passende Version. */
    public static function queueUpdate(Server $server, User $user, string $filename): object
    {
        $update = self::updates($server)['updates'][$filename] ?? null;
        $row = DB::table(self::TABLE)->where('server_id', $server->id)->where('directory', self::capability($server)['directory'])->where('filename', $filename)->first();
        if ($update === null || $row === null) {
            throw ToolsException::notFound('no_update');
        }

        return self::queueInstall($server, $user, $row->project_id, $update['version_id']);
    }

    /** Entfernt eine Datei aus plugins/ bzw. mods/ (auch unbekannte) und den eigenen Eintrag dazu. */
    public static function remove(Server $server, string $filename): void
    {
        $directory = self::capability($server)['directory'];
        $filename = Wings::filename($filename);
        $wings = Wings::for($server);
        $entry = $wings->stat('/' . $directory, $filename);
        if ($entry === null || !$entry['file'] || self::isTemp($filename)) {
            throw ToolsException::notFound('file_missing', ['directory' => $directory]);
        }
        $wings->delete('/' . $directory, [$filename]);
        DB::table(self::TABLE)->where('server_id', $server->id)->where('directory', $directory)->where('filename', $filename)->delete();
    }

    /**
     * Pflicht-Abhaengigkeiten einer Version (auch die der Abhaengigkeiten), jeweils mit der
     * Version, die zum Server passt.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function dependencies(Server $server, string $versionId): array
    {
        $capability = self::capability($server);
        $gameVersion = ServerMeta::gameVersion($server);
        $installed = self::installedProjects($server);
        $root = Modrinth::version($versionId);

        $queue = $root['required'];
        $seen = [$root['project_id'] => true];
        $found = [];
        $guard = 0;
        while ($queue !== [] && count($found) < 12 && $guard++ < 40) {
            $dependency = array_shift($queue);
            $version = null;
            if ($dependency['version_id'] !== null) {
                try {
                    $version = Modrinth::version($dependency['version_id']);
                } catch (ToolsException $e) {
                    $version = null;
                }
            }
            $projectId = $dependency['project_id'] ?? ($version['project_id'] ?? null);
            if ($projectId === null || isset($seen[$projectId])) {
                continue;
            }
            $seen[$projectId] = true;

            if ($version === null || array_intersect($version['loaders'], $capability['loaders']) === []) {
                $version = null;
                try {
                    $candidates = Modrinth::versions($projectId, $capability['loaders'], $gameVersion);
                    if ($candidates === [] && $gameVersion !== null) {
                        $candidates = Modrinth::versions($projectId, $capability['loaders'], null);
                    }
                    foreach ($candidates as $candidate) {
                        if (preg_match(Modrinth::FILE_PATTERN, $candidate['file']['filename'])) {
                            $version = $candidate;
                            break;
                        }
                    }
                } catch (ToolsException $e) {
                    if ($e->getStatusCode() !== 404) {
                        throw $e;
                    }
                }
            }

            $found[$projectId] = [
                'project_id' => $projectId,
                'title' => $projectId,
                'icon_url' => null,
                'description' => '',
                'installed' => isset($installed[$projectId]),
                'version' => $version !== null ? self::presentVersion($version, $gameVersion) : null,
            ];
            foreach ($version['required'] ?? [] as $next) {
                $queue[] = $next;
            }
        }

        foreach (Modrinth::projects(array_keys($found)) as $projectId => $project) {
            $found[$projectId]['title'] = $project['title'] !== '' ? $project['title'] : $projectId;
            $found[$projectId]['icon_url'] = $project['icon_url'];
            $found[$projectId]['description'] = $project['description'];
        }

        return array_values($found);
    }

    /** Version fuer den Browser: ohne Download-Adresse. */
    public static function presentVersion(array $version, ?string $gameVersion): array
    {
        $games = $version['game_versions'];

        return [
            'id' => $version['id'],
            'title' => $version['title'],
            'number' => $version['number'],
            'channel' => $version['channel'],
            'loaders' => $version['loaders'],
            'game_versions' => count($games) > 8 ? array_merge(array_slice($games, 0, 1), array_slice($games, -6)) : $games,
            'game_version_count' => count($games),
            'matches_server' => $gameVersion === null ? null : in_array($gameVersion, $games, true),
            'published_at' => $version['published_at'],
            'downloads' => $version['downloads'],
            'filename' => $version['file']['filename'],
            'size' => $version['file']['size'],
            'installable' => preg_match(Modrinth::FILE_PATTERN, $version['file']['filename']) === 1,
            'required_count' => count($version['required']),
        ];
    }

    public static function isTemp(string $filename): bool
    {
        return str_starts_with($filename, self::TEMP_PREFIX) && str_ends_with($filename, self::TEMP_SUFFIX);
    }

    /**
     * Velocity kennt keine Minecraft-Version: neueste Version je Projekt nur nach Loader.
     *
     * @param array<string, array<string, mixed>> $rows
     * @param string[] $loaders
     * @return array<string, array> Projekt-ID => Version
     */
    private static function latestWithoutGameVersion(array $rows, array $loaders): array
    {
        $latest = [];
        foreach (array_slice($rows, 0, 40) as $item) {
            try {
                $versions = Modrinth::versions($item['project_id'], $loaders, null);
            } catch (ToolsException $e) {
                continue;
            }
            foreach ($versions as $version) {
                if ($version['channel'] === 'release') {
                    $latest[$item['project_id']] = $version;
                    break;
                }
            }
        }

        return $latest;
    }
}
