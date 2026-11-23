<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Cache;

/**
 * Worlds are root folders that contain level.dat. Actions accept only a folder that
 * detection just found. Wings writes the backup as archive-….tar.gz in the server root.
 */
final class Worlds
{
    public const LEVEL_PHRASE = 'DELETE LEVEL';

    private const SCAN_LIMIT = 40;

    /** @return array<string, mixed> */
    public static function show(Server $server, bool $readLevel): array
    {
        $wings = Wings::for($server);
        $found = self::detect($wings, $readLevel);

        return [
            'state' => self::state($wings),
            'level_name' => $found['level'],
            'truncated' => $found['truncated'],
            'worlds' => $found['worlds'],
        ];
    }

    /** @return array{folder: string, archive: string, size: int} */
    public static function backup(Server $server, string $folder, bool $confirmRunning, bool $readLevel): array
    {
        return self::locked($server, function (Wings $wings) use ($folder, $confirmRunning, $readLevel) {
            $world = self::known($wings, $folder, $readLevel);
            $state = self::state($wings);
            $busy = $state !== 'offline';
            if ($busy && !$confirmRunning) {
                throw ToolsException::conflict('world_running');
            }
            $archive = $wings->compress('/', [$world['folder']]);

            return ['folder' => $world['folder'], 'archive' => $archive['name'], 'size' => $archive['size']];
        });
    }

    /** @return array{folder: string, deleted: bool} */
    public static function remove(Server $server, string $folder, string $confirm, string $confirmLevel, bool $readLevel): array
    {
        return self::locked($server, function (Wings $wings) use ($folder, $confirm, $confirmLevel, $readLevel) {
            $world = self::known($wings, $folder, $readLevel);
            if (self::state($wings) !== 'offline') {
                throw ToolsException::conflict('world_not_offline');
            }
            if ($confirm !== $world['folder']) {
                throw ToolsException::invalid('world_confirm');
            }
            if ($world['primary'] && $confirmLevel !== self::LEVEL_PHRASE) {
                throw ToolsException::invalid('world_level_confirm');
            }
            if (self::state($wings) !== 'offline') {
                throw ToolsException::conflict('world_not_offline');
            }
            $wings->delete('/', [$world['folder']]);

            return ['folder' => $world['folder'], 'deleted' => true];
        });
    }

    public static function folderOk(string $folder): bool
    {
        if ($folder === '' || strlen($folder) > 64 || $folder === '.' || $folder === '..' || str_contains($folder, '..')) {
            return false;
        }
        if (preg_match('/[\x00-\x1f\x7f\\\\\/]/', $folder) || str_starts_with($folder, ' ') || str_ends_with($folder, ' ') || str_ends_with($folder, '.')) {
            return false;
        }

        return true;
    }

    /**
     * @template T
     * @param \Closure(Wings): T $work
     * @return T
     */
    private static function locked(Server $server, \Closure $work): mixed
    {
        $lock = Cache::lock('techtyl:tools:world:' . $server->id, 150);
        try {
            $lock->block(3);
        } catch (\Throwable $e) {
            throw ToolsException::conflict('server_busy');
        }
        try {
            return $work(Wings::for($server));
        } finally {
            $lock->release();
        }
    }

    /** @return array{folder: string, role: string, primary: bool, size: ?int, modified: string} */
    private static function known(Wings $wings, string $folder, bool $readLevel): array
    {
        if (!self::folderOk($folder)) {
            throw ToolsException::invalid('world_unknown');
        }
        foreach (self::detect($wings, $readLevel)['worlds'] as $world) {
            if ($world['folder'] === $folder) {
                return $world;
            }
        }
        throw ToolsException::invalid('world_unknown');
    }

    /**
     * @return array{level: ?string, truncated: bool, worlds: array<int, array<string, mixed>>}
     */
    private static function detect(Wings $wings, bool $readLevel): array
    {
        $level = $readLevel ? self::levelName($wings) : null;
        $dirs = [];
        foreach ($wings->list('/') as $entry) {
            if (!$entry['file'] && self::folderOk($entry['name'])) {
                $dirs[$entry['name']] = $entry;
            }
        }
        $ordered = [];
        if (is_string($level)) {
            foreach ([$level, $level . '_nether', $level . '_the_end'] as $folder) {
                if (isset($dirs[$folder])) {
                    $ordered[] = $folder;
                }
            }
        }
        foreach (array_keys($dirs) as $folder) {
            if (!in_array($folder, $ordered, true)) {
                $ordered[] = $folder;
            }
        }
        $truncated = count($ordered) > self::SCAN_LIMIT;
        $worlds = [];
        foreach (array_slice($ordered, 0, self::SCAN_LIMIT) as $folder) {
            $hasLevel = false;
            foreach ($wings->list('/' . $folder) as $entry) {
                if ($entry['file'] && $entry['name'] === 'level.dat') {
                    $hasLevel = true;
                    break;
                }
            }
            if (!$hasLevel) {
                continue;
            }
            $size = (int) ($dirs[$folder]['size'] ?? 0);
            $worlds[] = [
                'folder' => $folder,
                'role' => is_string($level) ? self::role($folder, $level) : 'other',
                // Without the level name every folder is treated as the main world, so deleting
                // still asks for the extra confirmation.
                'primary' => !is_string($level) || $folder === $level,
                'size' => $size > 0 ? $size : null,
                'modified' => (string) ($dirs[$folder]['modified'] ?? ''),
            ];
        }

        return ['level' => $level, 'truncated' => $truncated, 'worlds' => $worlds];
    }

    private static function levelName(Wings $wings): ?string
    {
        try {
            $value = Properties::readKey($wings, 'level-name');
        } catch (ToolsException $e) {
            // Unreadable properties: do not guess "world", or the real level folder could be
            // deleted without the extra confirmation.
            return null;
        }
        if (is_string($value)) {
            $value = trim($value);
            if (self::folderOk($value)) {
                return $value;
            }
        }

        return 'world';
    }

    private static function role(string $folder, string $level): string
    {
        if ($folder === $level) {
            return 'overworld';
        }
        if ($folder === $level . '_nether') {
            return 'nether';
        }
        if ($folder === $level . '_the_end') {
            return 'end';
        }

        return 'other';
    }

    private static function state(Wings $wings): string
    {
        try {
            $state = $wings->state();
        } catch (ToolsException $e) {
            return 'unknown';
        }

        return in_array($state, ['offline', 'starting', 'running', 'stopping'], true) ? $state : 'unknown';
    }
}
