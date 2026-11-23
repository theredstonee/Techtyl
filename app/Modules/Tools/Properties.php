<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;

/**
 * server.properties: a fixed schema of known keys, plus any other key already in the file.
 * Saving rewrites only the keys that changed and keeps comments, order and unknown lines.
 */
final class Properties
{
    public const MAX_BYTES = 262144;

    public const KEY = '/^[a-z0-9][a-z0-9.\-]{0,63}$/';

    /**
     * Known keys. type is bool, int, enum or text. hint is a lang key under tools.*.
     *
     * @var array<int, array<string, mixed>>
     */
    public const FIELDS = [
        ['key' => 'motd', 'type' => 'text', 'group' => 'game', 'default' => 'A Minecraft Server', 'max' => 256, 'hint' => 'prop_motd'],
        ['key' => 'max-players', 'type' => 'int', 'group' => 'game', 'default' => '20', 'min' => 0, 'max' => 2147483647, 'hint' => 'prop_max_players'],
        ['key' => 'gamemode', 'type' => 'enum', 'group' => 'game', 'default' => 'survival', 'values' => ['survival', 'creative', 'adventure', 'spectator'], 'hint' => 'prop_gamemode'],
        ['key' => 'force-gamemode', 'type' => 'bool', 'group' => 'game', 'default' => 'false', 'hint' => 'prop_force_gamemode'],
        ['key' => 'difficulty', 'type' => 'enum', 'group' => 'game', 'default' => 'easy', 'values' => ['peaceful', 'easy', 'normal', 'hard'], 'hint' => 'prop_difficulty'],
        ['key' => 'hardcore', 'type' => 'bool', 'group' => 'game', 'default' => 'false', 'hint' => 'prop_hardcore'],
        ['key' => 'pvp', 'type' => 'bool', 'group' => 'game', 'default' => 'true', 'hint' => 'prop_pvp'],
        ['key' => 'allow-flight', 'type' => 'bool', 'group' => 'game', 'default' => 'false', 'hint' => 'prop_allow_flight'],
        ['key' => 'spawn-monsters', 'type' => 'bool', 'group' => 'game', 'default' => 'true', 'hint' => 'prop_spawn_monsters'],
        ['key' => 'spawn-animals', 'type' => 'bool', 'group' => 'game', 'default' => 'true', 'hint' => 'prop_spawn_animals'],
        ['key' => 'spawn-npcs', 'type' => 'bool', 'group' => 'game', 'default' => 'true', 'hint' => 'prop_spawn_npcs'],
        ['key' => 'spawn-protection', 'type' => 'int', 'group' => 'game', 'default' => '16', 'min' => 0, 'max' => 29999984, 'hint' => 'prop_spawn_protection'],
        ['key' => 'white-list', 'type' => 'bool', 'group' => 'game', 'default' => 'false', 'hint' => 'prop_white_list'],
        ['key' => 'enforce-whitelist', 'type' => 'bool', 'group' => 'game', 'default' => 'false', 'hint' => 'prop_enforce_whitelist'],
        ['key' => 'online-mode', 'type' => 'bool', 'group' => 'game', 'default' => 'true', 'hint' => 'prop_online_mode'],
        ['key' => 'prevent-proxy-connections', 'type' => 'bool', 'group' => 'game', 'default' => 'false', 'hint' => 'prop_prevent_proxy'],
        ['key' => 'player-idle-timeout', 'type' => 'int', 'group' => 'game', 'default' => '0', 'min' => 0, 'max' => 100000, 'hint' => 'prop_idle_timeout'],
        ['key' => 'hide-online-players', 'type' => 'bool', 'group' => 'game', 'default' => 'false', 'hint' => 'prop_hide_online'],
        ['key' => 'enforce-secure-profile', 'type' => 'bool', 'group' => 'game', 'default' => 'true', 'hint' => 'prop_secure_profile'],
        ['key' => 'enable-command-block', 'type' => 'bool', 'group' => 'game', 'default' => 'false', 'hint' => 'prop_command_block'],
        ['key' => 'op-permission-level', 'type' => 'int', 'group' => 'game', 'default' => '4', 'min' => 1, 'max' => 4, 'hint' => 'prop_op_level'],
        ['key' => 'function-permission-level', 'type' => 'int', 'group' => 'game', 'default' => '2', 'min' => 1, 'max' => 4, 'hint' => 'prop_function_level'],
        ['key' => 'broadcast-console-to-ops', 'type' => 'bool', 'group' => 'game', 'default' => 'true', 'hint' => 'prop_broadcast_console'],
        ['key' => 'level-name', 'type' => 'text', 'group' => 'world', 'default' => 'world', 'max' => 64, 'pattern' => '/^[A-Za-z0-9._-]{1,64}$/', 'hint' => 'prop_level_name'],
        ['key' => 'level-seed', 'type' => 'text', 'group' => 'world', 'default' => '', 'max' => 128, 'hint' => 'prop_level_seed'],
        ['key' => 'level-type', 'type' => 'enum', 'group' => 'world', 'default' => 'minecraft:normal', 'values' => ['minecraft:normal', 'minecraft:flat', 'minecraft:large_biomes', 'minecraft:amplified', 'minecraft:single_biome_surface', 'default', 'flat', 'largeBiomes', 'amplified', 'buffet'], 'hint' => 'prop_level_type'],
        ['key' => 'generator-settings', 'type' => 'text', 'group' => 'world', 'default' => '{}', 'max' => 8192, 'hint' => 'prop_generator'],
        ['key' => 'generate-structures', 'type' => 'bool', 'group' => 'world', 'default' => 'true', 'hint' => 'prop_structures'],
        ['key' => 'allow-nether', 'type' => 'bool', 'group' => 'world', 'default' => 'true', 'hint' => 'prop_allow_nether'],
        ['key' => 'max-world-size', 'type' => 'int', 'group' => 'world', 'default' => '29999984', 'min' => 1, 'max' => 29999984, 'hint' => 'prop_max_world'],
        ['key' => 'view-distance', 'type' => 'int', 'group' => 'perf', 'default' => '10', 'min' => 2, 'max' => 32, 'hint' => 'prop_view_distance'],
        ['key' => 'simulation-distance', 'type' => 'int', 'group' => 'perf', 'default' => '10', 'min' => 2, 'max' => 32, 'hint' => 'prop_simulation'],
        ['key' => 'entity-broadcast-range-percentage', 'type' => 'int', 'group' => 'perf', 'default' => '100', 'min' => 0, 'max' => 1000, 'hint' => 'prop_entity_range'],
        ['key' => 'max-chained-neighbor-updates', 'type' => 'int', 'group' => 'perf', 'default' => '1000000', 'min' => 0, 'max' => 1000000000, 'hint' => 'prop_neighbor_updates'],
        ['key' => 'region-file-compression', 'type' => 'enum', 'group' => 'perf', 'default' => 'deflate', 'values' => ['deflate', 'lz4', 'none'], 'hint' => 'prop_region_compression'],
        ['key' => 'sync-chunk-writes', 'type' => 'bool', 'group' => 'perf', 'default' => 'true', 'hint' => 'prop_sync_chunks'],
        ['key' => 'network-compression-threshold', 'type' => 'int', 'group' => 'perf', 'default' => '256', 'min' => -1, 'max' => 65535, 'hint' => 'prop_compression'],
        ['key' => 'max-tick-time', 'type' => 'int', 'group' => 'perf', 'default' => '60000', 'min' => -1, 'max' => 2147483647, 'hint' => 'prop_tick_time'],
        ['key' => 'use-native-transport', 'type' => 'bool', 'group' => 'perf', 'default' => 'true', 'hint' => 'prop_native_transport'],
        ['key' => 'pause-when-empty-seconds', 'type' => 'int', 'group' => 'perf', 'default' => '60', 'min' => -1, 'max' => 86400, 'hint' => 'prop_pause_empty'],
        ['key' => 'server-ip', 'type' => 'text', 'group' => 'net', 'default' => '', 'max' => 64, 'pattern' => '/^(?:|[A-Za-z0-9.:-]{1,64})$/', 'hint' => 'prop_server_ip'],
        ['key' => 'server-port', 'type' => 'int', 'group' => 'net', 'default' => '25565', 'min' => 1, 'max' => 65535, 'hint' => 'prop_server_port'],
        ['key' => 'rate-limit', 'type' => 'int', 'group' => 'net', 'default' => '0', 'min' => 0, 'max' => 1000000, 'hint' => 'prop_rate_limit'],
        ['key' => 'accepts-transfers', 'type' => 'bool', 'group' => 'net', 'default' => 'false', 'hint' => 'prop_transfers'],
        ['key' => 'log-ips', 'type' => 'bool', 'group' => 'remote', 'default' => 'true', 'hint' => 'prop_log_ips'],
        ['key' => 'enable-status', 'type' => 'bool', 'group' => 'remote', 'default' => 'true', 'hint' => 'prop_status'],
        ['key' => 'enable-query', 'type' => 'bool', 'group' => 'remote', 'default' => 'false', 'hint' => 'prop_query'],
        ['key' => 'query.port', 'type' => 'int', 'group' => 'remote', 'default' => '25565', 'min' => 1, 'max' => 65535, 'hint' => 'prop_query_port'],
        ['key' => 'enable-rcon', 'type' => 'bool', 'group' => 'remote', 'default' => 'false', 'hint' => 'prop_rcon'],
        ['key' => 'rcon.port', 'type' => 'int', 'group' => 'remote', 'default' => '25575', 'min' => 1, 'max' => 65535, 'hint' => 'prop_rcon_port'],
        ['key' => 'rcon.password', 'type' => 'text', 'group' => 'remote', 'default' => '', 'max' => 256, 'secret' => true, 'hint' => 'prop_rcon_password'],
        ['key' => 'broadcast-rcon-to-ops', 'type' => 'bool', 'group' => 'remote', 'default' => 'true', 'hint' => 'prop_broadcast_rcon'],
        ['key' => 'enable-jmx-monitoring', 'type' => 'bool', 'group' => 'remote', 'default' => 'false', 'hint' => 'prop_jmx'],
        ['key' => 'resource-pack', 'type' => 'text', 'group' => 'packs', 'default' => '', 'max' => 512, 'hint' => 'prop_resource_pack'],
        ['key' => 'resource-pack-sha1', 'type' => 'text', 'group' => 'packs', 'default' => '', 'max' => 64, 'hint' => 'prop_resource_sha1'],
        ['key' => 'resource-pack-prompt', 'type' => 'text', 'group' => 'packs', 'default' => '', 'max' => 256, 'hint' => 'prop_resource_prompt'],
        ['key' => 'resource-pack-id', 'type' => 'text', 'group' => 'packs', 'default' => '', 'max' => 64, 'hint' => 'prop_resource_id'],
        ['key' => 'require-resource-pack', 'type' => 'bool', 'group' => 'packs', 'default' => 'false', 'hint' => 'prop_require_pack'],
        ['key' => 'initial-enabled-packs', 'type' => 'text', 'group' => 'packs', 'default' => 'vanilla', 'max' => 1024, 'hint' => 'prop_enabled_packs'],
        ['key' => 'initial-disabled-packs', 'type' => 'text', 'group' => 'packs', 'default' => '', 'max' => 1024, 'hint' => 'prop_disabled_packs'],
        ['key' => 'bug-report-link', 'type' => 'text', 'group' => 'packs', 'default' => '', 'max' => 256, 'hint' => 'prop_bug_link'],
        ['key' => 'text-filtering-config', 'type' => 'text', 'group' => 'packs', 'default' => '', 'max' => 512, 'hint' => 'prop_text_filter'],
    ];

    /** @return array<string, array<string, mixed>> */
    public static function index(): array
    {
        $index = [];
        foreach (self::FIELDS as $field) {
            $index[$field['key']] = $field;
        }

        return $index;
    }

    /** One key from server.properties, or null when the file or the key is missing. */
    public static function readKey(Wings $wings, string $key): ?string
    {
        $loaded = self::load($wings);
        if ($loaded === null) {
            return null;
        }

        return $loaded['values'][$key] ?? null;
    }

    /** @return array<string, mixed> */
    public static function describe(Wings $wings): array
    {
        $loaded = self::load($wings);
        $values = $loaded['values'] ?? [];
        $fields = [];
        $seen = [];
        foreach (self::FIELDS as $field) {
            $seen[$field['key']] = true;
            $present = $loaded !== null && array_key_exists($field['key'], $values);
            $fields[] = self::present($field, $present ? (string) $values[$field['key']] : (string) $field['default'], $present, false);
        }
        if ($loaded !== null) {
            foreach ($values as $key => $value) {
                if (isset($seen[$key])) {
                    continue;
                }
                $secret = preg_match('/password|secret|token/', (string) $key) === 1;
                $fields[] = [
                    'key' => $key,
                    'type' => 'text',
                    'group' => 'custom',
                    'value' => (string) $value,
                    'default' => '',
                    'present' => true,
                    'hint' => I18n::t('tools.prop_custom'),
                    'min' => null,
                    'max' => $secret ? 256 : 1024,
                    'options' => null,
                    'secret' => $secret,
                    'custom' => true,
                ];
            }
        }

        return [
            'exists' => $loaded !== null,
            'bytes' => $loaded['bytes'] ?? 0,
            'odd' => $loaded['odd'] ?? 0,
            'fields' => $fields,
        ];
    }

    /**
     * @param array<string, string> $changes
     * @return array{saved: bool, restart: bool, changed: int, created: bool}
     */
    public static function save(Wings $wings, array $changes, bool $create): array
    {
        if (count($changes) > 80) {
            throw ToolsException::invalid('props_too_many');
        }
        $stat = self::statFile($wings);
        $exists = $stat !== null;
        if (!$exists) {
            if (!$create) {
                throw ToolsException::invalid('props_missing');
            }
            $content = self::buildNew($changes);
            $changed = count(self::FIELDS);
        } else {
            if ($changes === []) {
                throw ToolsException::invalid('props_no_changes');
            }
            $raw = $wings->read('/server.properties', self::MAX_BYTES);
            if ($raw === null) {
                throw ToolsException::invalid('props_too_large');
            }
            $content = self::patch(self::parse($raw), $changes);
            $changed = count($changes);
        }
        if (strlen($content) > self::MAX_BYTES) {
            throw ToolsException::invalid('props_too_large');
        }
        $wings->put('/server.properties', $content);

        return ['saved' => true, 'restart' => true, 'changed' => $changed, 'created' => !$exists];
    }

    /**
     * @return array{eol: string, lines: array<int, array<string, string>>, values: array<string, string>, odd: int}|null
     */
    private static function load(Wings $wings): ?array
    {
        $stat = self::statFile($wings);
        if ($stat === null) {
            return null;
        }
        $raw = $wings->read('/server.properties', self::MAX_BYTES);
        if ($raw === null) {
            throw ToolsException::invalid('props_too_large');
        }
        $parsed = self::parse($raw);
        $parsed['bytes'] = $stat['size'];

        return $parsed;
    }

    /** @return array{name: string, size: int, file: bool, modified: string}|null */
    private static function statFile(Wings $wings): ?array
    {
        $stat = $wings->stat('/', 'server.properties');
        if ($stat === null) {
            return null;
        }
        if (!$stat['file']) {
            throw ToolsException::conflict('props_not_file');
        }
        if ($stat['size'] > self::MAX_BYTES) {
            throw ToolsException::invalid('props_too_large');
        }

        return $stat;
    }

    /**
     * @return array{eol: string, lines: array<int, array<string, string>>, values: array<string, string>, odd: int}
     */
    public static function parse(string $raw): array
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $eol = str_contains($raw, "\r\n") ? "\r\n" : "\n";
        $body = rtrim(str_replace("\r\n", "\n", $raw), "\n");
        $rows = $body === '' ? [] : explode("\n", $body);
        $lines = [];
        $values = [];
        $odd = 0;
        foreach ($rows as $row) {
            $row = rtrim($row, "\r");
            if (trim($row) === '') {
                $lines[] = ['kind' => 'blank', 'text' => $row];
                continue;
            }
            if (preg_match('/^\s*#/', $row)) {
                $lines[] = ['kind' => 'comment', 'text' => $row];
                continue;
            }
            $eq = strpos($row, '=');
            if ($eq === false) {
                $lines[] = ['kind' => 'raw', 'text' => $row];
                $odd++;
                continue;
            }
            $key = trim(substr($row, 0, $eq));
            if (!preg_match(self::KEY, $key)) {
                $lines[] = ['kind' => 'raw', 'text' => $row];
                $odd++;
                continue;
            }
            $lines[] = ['kind' => 'pair', 'text' => $row, 'key' => $key];
            $values[$key] = substr($row, $eq + 1);
        }

        return ['eol' => $eol, 'lines' => $lines, 'values' => $values, 'odd' => $odd];
    }

    /**
     * @param array{eol: string, lines: array<int, array<string, string>>, values: array<string, string>, odd: int} $parsed
     * @param array<string, string> $changes
     */
    public static function patch(array $parsed, array $changes): string
    {
        $schema = self::index();
        $lines = $parsed['lines'];
        $where = [];
        foreach ($lines as $index => $line) {
            if (($line['kind'] ?? '') === 'pair' && isset($line['key'])) {
                $where[$line['key']][] = $index;
            }
        }
        $append = [];
        foreach ($changes as $key => $value) {
            if (!is_string($key) || !is_string($value) || !preg_match(self::KEY, $key)) {
                throw ToolsException::invalid('props_key');
            }
            $field = $schema[$key] ?? null;
            if ($field === null && !isset($where[$key])) {
                throw ToolsException::invalid('props_unknown', ['key' => $key]);
            }
            $clean = self::check($field, $key, $value);
            if (isset($where[$key])) {
                foreach ($where[$key] as $index) {
                    $lines[$index]['text'] = $key . '=' . $clean;
                }
            } else {
                $append[] = $key . '=' . $clean;
            }
        }
        $texts = [];
        foreach ($lines as $line) {
            $texts[] = $line['text'];
        }
        foreach ($append as $row) {
            $texts[] = $row;
        }
        $out = implode($parsed['eol'], $texts);
        if ($out !== '') {
            $out .= $parsed['eol'];
        }

        return $out;
    }

    /** @param array<string, string> $changes */
    public static function buildNew(array $changes): string
    {
        $schema = self::index();
        $values = [];
        foreach (self::FIELDS as $field) {
            $values[$field['key']] = (string) $field['default'];
        }
        foreach ($changes as $key => $value) {
            if (!is_string($key) || !isset($schema[$key]) || !is_string($value)) {
                throw ToolsException::invalid('props_unknown', ['key' => is_string($key) ? $key : '']);
            }
            $values[$key] = self::check($schema[$key], $key, $value);
        }
        $lines = ['# Minecraft server properties'];
        foreach (self::FIELDS as $field) {
            $lines[] = $field['key'] . '=' . $values[$field['key']];
        }

        return implode("\n", $lines) . "\n";
    }

    /** @param array<string, mixed> $field */
    private static function present(array $field, string $value, bool $present, bool $custom): array
    {
        return [
            'key' => $field['key'],
            'type' => $field['type'],
            'group' => $field['group'],
            'value' => $value,
            'default' => (string) $field['default'],
            'present' => $present,
            'hint' => I18n::t('tools.' . $field['hint']),
            'min' => isset($field['min']) ? (int) $field['min'] : null,
            'max' => isset($field['max']) ? (int) $field['max'] : null,
            'options' => $field['values'] ?? null,
            'secret' => ($field['secret'] ?? false) === true,
            'custom' => $custom,
        ];
    }

    /** @param array<string, mixed>|null $field */
    private static function check(?array $field, string $key, string $value): string
    {
        if (preg_match('/[\x00-\x1f\x7f]/', $value) || str_contains($value, "\u{2028}") || str_contains($value, "\u{2029}")) {
            throw ToolsException::invalid('props_linebreak', ['key' => $key]);
        }
        if (strlen($value) > 16384) {
            throw ToolsException::invalid('props_length', ['key' => $key, 'max' => 16384]);
        }
        if ($field === null) {
            $max = preg_match('/password|secret|token/', $key) === 1 ? 256 : 1024;
            if (mb_strlen($value) > $max) {
                throw ToolsException::invalid('props_length', ['key' => $key, 'max' => $max]);
            }

            return $value;
        }
        $max = (int) ($field['max'] ?? 1024);
        if (($field['type'] ?? '') !== 'bool' && mb_strlen($value) > $max) {
            throw ToolsException::invalid('props_length', ['key' => $key, 'max' => $max]);
        }

        return match ($field['type']) {
            'bool' => self::checkBool($key, $value),
            'int' => self::checkInt($key, $value, (int) $field['min'], (int) $field['max']),
            'enum' => self::checkEnum($key, $value, is_array($field['values'] ?? null) ? $field['values'] : []),
            default => self::checkText($key, $value, isset($field['pattern']) ? (string) $field['pattern'] : null),
        };
    }

    private static function checkBool(string $key, string $value): string
    {
        if ($value !== 'true' && $value !== 'false') {
            throw ToolsException::invalid('props_type', ['key' => $key]);
        }

        return $value;
    }

    private static function checkInt(string $key, string $value, int $min, int $max): string
    {
        if (!preg_match('/^-?(?:0|[1-9]\d{0,18})$/', $value)) {
            throw ToolsException::invalid('props_type', ['key' => $key]);
        }
        $number = (int) $value;
        if ((string) $number !== $value) {
            throw ToolsException::invalid('props_type', ['key' => $key]);
        }
        if ($number < $min || $number > $max) {
            throw ToolsException::invalid('props_range', ['key' => $key, 'min' => $min, 'max' => $max]);
        }

        return $value;
    }

    /** @param array<int, string> $values */
    private static function checkEnum(string $key, string $value, array $values): string
    {
        if (!in_array($value, $values, true)) {
            throw ToolsException::invalid('props_enum', ['key' => $key]);
        }

        return $value;
    }

    private static function checkText(string $key, string $value, ?string $pattern): string
    {
        if ($pattern !== null && preg_match($pattern, $value) !== 1) {
            throw ToolsException::invalid('props_format', ['key' => $key]);
        }

        return $value;
    }
}
