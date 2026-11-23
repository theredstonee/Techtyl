<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Cache;

/**
 * Whitelist, operators, bans and the server-list ping.
 * Console commands are sent only while the server is running. File edits happen only
 * while it is offline, and only for names that cannot start a second command.
 */
final class Players
{
    public const NAME = '/^\.?[A-Za-z0-9_]{1,16}$/';

    public const MAX_BYTES = 262144;

    public const CONSOLE_ACTIONS = ['whitelist_add', 'whitelist_remove', 'op', 'deop', 'ban', 'pardon', 'kick'];

    public const FILE_ACTIONS = ['whitelist_add', 'whitelist_remove', 'op', 'deop', 'ban', 'pardon'];

    /** @return array<string, mixed> */
    public static function show(Server $server): array
    {
        $wings = Wings::for($server);
        $lists = [
            'whitelist' => self::readList($wings, 'whitelist.json', 'name'),
            'ops' => self::readList($wings, 'ops.json', 'name'),
            'bans' => self::readList($wings, 'banned-players.json', 'name'),
            'ip_bans' => self::readList($wings, 'banned-ips.json', 'ip'),
        ];
        $state = self::state($wings);
        $query = $state === 'running' ? ServerListPing::query($server) : ServerListPing::unknown();
        $budget = 20;
        $query['sample'] = self::fillUuids($query['sample'], $budget);
        foreach (['whitelist', 'ops', 'bans'] as $key) {
            $lists[$key]['entries'] = self::fillUuids($lists[$key]['entries'], $budget);
        }

        return [
            'state' => $state,
            'online_mode' => self::onlineMode($wings),
            'query' => $query,
            'whitelist' => $lists['whitelist'],
            'ops' => $lists['ops'],
            'bans' => $lists['bans'],
            'ip_bans' => $lists['ip_bans'],
        ];
    }

    /** @return array{ok: bool, via: string} */
    public static function console(Server $server, string $action, string $player, string $reason): array
    {
        if (!in_array($action, self::CONSOLE_ACTIONS, true)) {
            throw ToolsException::invalid('player_action');
        }
        $player = self::assertName($player);
        $reason = self::reason($reason);
        $wings = Wings::for($server);
        $state = self::state($wings);
        if ($state !== 'running') {
            throw ToolsException::conflict($state === 'offline' ? 'player_need_running' : 'player_not_ready');
        }
        $wings->command(self::line($action, $player, $reason));

        return ['ok' => true, 'via' => 'console'];
    }

    /** @return array{ok: bool, via: string} */
    public static function edit(Server $server, string $action, string $player, string $reason): array
    {
        if (!in_array($action, self::FILE_ACTIONS, true)) {
            throw ToolsException::invalid('player_action');
        }
        $player = self::assertName($player);
        $reason = self::reason($reason);
        $wings = Wings::for($server);
        $state = self::state($wings);
        if ($state !== 'offline') {
            throw ToolsException::conflict($state === 'running' ? 'player_use_console' : 'player_need_offline');
        }
        $file = self::fileFor($action);
        $raw = self::rawList($wings, $file);
        $adding = in_array($action, ['whitelist_add', 'op', 'ban'], true);
        $uuid = $adding ? self::resolveUuid($wings, $player) : '';
        $encoded = self::mutate($raw, $action, $player, $reason, $uuid, self::opLevel($wings));
        if (self::state($wings) !== 'offline') {
            throw ToolsException::conflict('player_need_offline');
        }
        $wings->put('/' . $file, $encoded);

        return ['ok' => true, 'via' => 'file'];
    }

    public static function assertName(string $player): string
    {
        $player = trim($player);
        if (preg_match(self::NAME, $player) !== 1 || strlen($player) > 17) {
            throw ToolsException::invalid('player_name');
        }

        return $player;
    }

    public static function reason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            return '';
        }
        if (mb_strlen($reason) > 100 || strlen($reason) > 400
            || preg_match('/[\x00-\x1f\x7f]/', $reason)
            || str_contains($reason, "\u{2028}")
            || str_contains($reason, "\u{2029}")) {
            throw ToolsException::invalid('player_reason');
        }

        return $reason;
    }

    public static function canonicalUuid(string $value): ?string
    {
        $hex = strtolower(str_replace('-', '', trim($value)));
        if (preg_match('/^[0-9a-f]{32}$/', $hex) !== 1) {
            return null;
        }

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }

    public static function offlineUuid(string $player): string
    {
        $hash = md5('OfflinePlayer:' . $player, true);
        $hash[6] = chr((ord($hash[6]) & 0x0f) | 0x30);
        $hash[8] = chr((ord($hash[8]) & 0x3f) | 0x80);
        $uuid = self::canonicalUuid(bin2hex($hash));

        return $uuid ?? '00000000-0000-3000-8000-000000000000';
    }

    public static function line(string $action, string $player, string $reason): string
    {
        $withReason = in_array($action, ['ban', 'kick'], true) && $reason !== '';
        $verb = match ($action) {
            'whitelist_add' => 'whitelist add',
            'whitelist_remove' => 'whitelist remove',
            'op' => 'op',
            'deop' => 'deop',
            'ban' => 'ban',
            'pardon' => 'pardon',
            'kick' => 'kick',
            default => throw ToolsException::invalid('player_action'),
        };
        $line = $verb . ' ' . $player . ($withReason ? ' ' . $reason : '');
        if (strlen($line) > 180 || preg_match('/[\x00-\x1f\x7f]/', $line)) {
            throw ToolsException::invalid('command_rejected');
        }

        return $line;
    }

    /** Mojang UUID, or null when the name is free or unknown. Upstream errors are rethrown. */
    public static function lookup(string $player): ?string
    {
        if (preg_match('/^[A-Za-z0-9_]{1,16}$/', $player) !== 1) {
            return null;
        }
        $key = 'techtyl:tools:uuid:' . strtolower($player);
        $hit = Cache::get($key);
        if ($hit === '') {
            return null;
        }
        if (is_string($hit) && self::canonicalUuid($hit) === $hit) {
            return $hit;
        }
        try {
            $data = ExternalHttp::get('mojang', '/minecraft/profile/lookup/name/' . rawurlencode($player), [], 86400);
        } catch (ToolsException $e) {
            if ($e->getStatusCode() === 404) {
                Cache::put($key, '', 86400);

                return null;
            }
            throw $e;
        }
        $uuid = is_string($data['id'] ?? null) ? self::canonicalUuid($data['id']) : null;
        Cache::put($key, $uuid ?? '', 86400);

        return $uuid;
    }

    private static function resolveUuid(Wings $wings, string $player): string
    {
        if (!self::onlineMode($wings)) {
            return self::offlineUuid($player);
        }
        $uuid = self::lookup($player);
        if ($uuid === null) {
            throw ToolsException::invalid('player_uuid');
        }

        return $uuid;
    }

    private static function onlineMode(Wings $wings): bool
    {
        try {
            $value = Properties::readKey($wings, 'online-mode');
        } catch (ToolsException $e) {
            return true;
        }

        return $value === null || strtolower($value) !== 'false';
    }

    private static function opLevel(Wings $wings): int
    {
        try {
            $value = Properties::readKey($wings, 'op-permission-level');
        } catch (ToolsException $e) {
            return 4;
        }

        return is_string($value) && preg_match('/^[1-4]$/', $value) ? (int) $value : 4;
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

    private static function fileFor(string $action): string
    {
        return match ($action) {
            'whitelist_add', 'whitelist_remove' => 'whitelist.json',
            'op', 'deop' => 'ops.json',
            'ban', 'pardon' => 'banned-players.json',
            default => throw ToolsException::invalid('player_action'),
        };
    }

    private static function rawList(Wings $wings, string $file): string
    {
        $stat = $wings->stat('/', $file);
        if ($stat === null) {
            return '[]';
        }
        if (!$stat['file']) {
            throw ToolsException::conflict('player_file_bad');
        }
        if ($stat['size'] > self::MAX_BYTES) {
            throw ToolsException::invalid('player_file_large', ['file' => $file]);
        }
        $raw = $wings->read('/' . $file, self::MAX_BYTES);
        if ($raw === null) {
            throw ToolsException::invalid('player_file_large', ['file' => $file]);
        }

        return trim($raw) === '' ? '[]' : $raw;
    }

    /** @return array{missing: bool, broken: bool, too_large: bool, entries: array<int, array<string, mixed>>} */
    private static function readList(Wings $wings, string $file, string $kind): array
    {
        $empty = ['missing' => true, 'broken' => false, 'too_large' => false, 'entries' => []];
        try {
            $stat = $wings->stat('/', $file);
        } catch (ToolsException $e) {
            throw $e;
        }
        if ($stat === null) {
            return $empty;
        }
        if (!$stat['file'] || $stat['size'] > self::MAX_BYTES) {
            return ['missing' => false, 'broken' => false, 'too_large' => true, 'entries' => []];
        }
        $raw = $wings->read('/' . $file, self::MAX_BYTES);
        if ($raw === null) {
            return ['missing' => false, 'broken' => false, 'too_large' => true, 'entries' => []];
        }
        if (trim($raw) === '') {
            return ['missing' => false, 'broken' => false, 'too_large' => false, 'entries' => []];
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !array_is_list($data)) {
            return ['missing' => false, 'broken' => true, 'too_large' => false, 'entries' => []];
        }

        return ['missing' => false, 'broken' => false, 'too_large' => false, 'entries' => self::rows($data, $kind)];
    }

    /**
     * @param array<int, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    private static function rows(array $data, string $kind): array
    {
        $rows = [];
        foreach ($data as $entry) {
            if (count($rows) >= 500) {
                break;
            }
            if ($kind === 'ip') {
                $row = self::ipRow($entry);
            } else {
                $row = self::playerRow($entry);
            }
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @return array<string, mixed>|null */
    private static function playerRow(mixed $entry): ?array
    {
        if (is_string($entry)) {
            $label = $entry;
            $uuid = null;
            $reason = '';
            $level = null;
        } elseif (is_array($entry) && is_string($entry['name'] ?? null)) {
            $label = $entry['name'];
            $uuid = is_string($entry['uuid'] ?? null) ? self::canonicalUuid($entry['uuid']) : null;
            $reason = is_string($entry['reason'] ?? null) ? $entry['reason'] : '';
            $level = is_int($entry['level'] ?? null) ? $entry['level'] : null;
        } else {
            return null;
        }
        if ($label === '' || strlen($label) > 32 || preg_match('/[\x00-\x1f\x7f]/', $label)) {
            return null;
        }
        $reason = preg_replace('/[\x00-\x1f\x7f]/', '', $reason) ?? '';

        return [
            'name' => $label,
            'uuid' => $uuid,
            'reason' => mb_substr($reason, 0, 200),
            'level' => $level,
            'actionable' => preg_match(self::NAME, $label) === 1,
        ];
    }

    /** @return array<string, mixed>|null */
    private static function ipRow(mixed $entry): ?array
    {
        if (!is_array($entry) || !is_string($entry['ip'] ?? null) || filter_var($entry['ip'], FILTER_VALIDATE_IP) === false) {
            return null;
        }
        $reason = is_string($entry['reason'] ?? null) ? $entry['reason'] : '';
        $reason = preg_replace('/[\x00-\x1f\x7f]/', '', $reason) ?? '';

        return ['ip' => $entry['ip'], 'reason' => mb_substr($reason, 0, 200)];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private static function fillUuids(array $rows, int &$budget): array
    {
        foreach ($rows as $index => $row) {
            if ($budget <= 0 || ($row['uuid'] ?? null) !== null || !is_string($row['name'] ?? null)) {
                continue;
            }
            if (preg_match('/^[A-Za-z0-9_]{1,16}$/', $row['name']) !== 1) {
                continue;
            }
            $budget--;
            try {
                $rows[$index]['uuid'] = self::lookup($row['name']);
            } catch (ToolsException $e) {
                $rows[$index]['uuid'] = null;
            }
        }

        return $rows;
    }

    public static function mutate(string $raw, string $action, string $player, string $reason, string $uuid, int $opLevel): string
    {
        try {
            $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw ToolsException::conflict('player_file_bad');
        }
        if (!is_array($data) || !array_is_list($data)) {
            throw ToolsException::conflict('player_file_bad');
        }
        $index = self::find($data, $player);
        $adding = in_array($action, ['whitelist_add', 'op', 'ban'], true);
        if ($adding && $index !== null) {
            throw ToolsException::conflict('player_exists');
        }
        if (!$adding && $index === null) {
            throw ToolsException::notFound('player_missing');
        }
        if ($adding) {
            $data[] = self::entry($action, $player, $reason, $uuid, $opLevel);
        } else {
            array_splice($data, $index, 1);
        }
        try {
            $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw ToolsException::conflict('player_file_bad');
        }
        if (!is_string($encoded) || strlen($encoded) + 1 > self::MAX_BYTES) {
            throw ToolsException::invalid('player_file_large', ['file' => self::fileFor($action)]);
        }

        return $encoded . "\n";
    }

    /** @param array<int, mixed> $data */
    private static function find(array $data, string $player): ?int
    {
        foreach ($data as $index => $entry) {
            $label = is_string($entry) ? $entry : (is_array($entry) ? ($entry['name'] ?? '') : '');
            if (is_string($label) && strcasecmp($label, $player) === 0) {
                return $index;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private static function entry(string $action, string $player, string $reason, string $uuid, int $opLevel): array
    {
        if ($action === 'op') {
            return [
                'uuid' => $uuid,
                'name' => $player,
                'level' => $opLevel,
                'bypassesPlayerLimit' => false,
            ];
        }
        if ($action === 'ban') {
            return [
                'uuid' => $uuid,
                'name' => $player,
                'created' => gmdate('Y-m-d H:i:s') . ' +0000',
                'source' => 'Server',
                'expires' => 'forever',
                'reason' => $reason !== '' ? $reason : 'Banned by an operator',
            ];
        }

        return ['uuid' => $uuid, 'name' => $player];
    }
}
