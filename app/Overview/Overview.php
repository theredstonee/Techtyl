<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Overview;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Helpers\SoftwareVersionService;

/**
 * Daten der Admin-Uebersicht auf /admin. Wird nur vom Admin-Wrapper auf der Route admin.index
 * fuer Root-Admins aufgerufen. Fuenf aggregierte Abfragen, Ergebnis 60 s im Panel-Cache,
 * keine Aufrufe an Wings (die Seite laedt auch, wenn Nodes offline sind).
 */
class Overview
{
    public const CACHE_KEY = 'techtyl:overview:stats:v2';

    public const TTL = 60;

    private const NODE_LIMIT = 8;

    private const LIST_LIMIT = 5;

    public const VIEW = 'blueprint.extensions.{identifier}.admin.overview';

    /**
     * Fertiges HTML der Uebersicht (alle Werte escaped), oder null, wenn sie nicht gezeigt werden
     * soll oder ein Fehler auftrat. Ein Fehler hier darf die Panel-Seite nie beschaedigen.
     */
    public static function render(): ?string
    {
        $data = self::build();
        if ($data === null) {
            return null;
        }

        try {
            return view(self::VIEW, ['ov' => $data])->render();
        } catch (\Throwable $e) {
            Log::error('Techtyl-Uebersicht konnte nicht dargestellt werden', ['exception' => $e]);

            return null;
        }
    }

    /**
     * Alles fuer die Ansicht, oder null, wenn die Uebersicht nicht gezeigt werden soll
     * (dann bleibt die Panel-Seite unveraendert).
     *
     * @return array<string, mixed>|null
     */
    public static function build(): ?array
    {
        $user = auth()->user();
        if ($user === null || $user->root_admin !== true || !request()->routeIs('admin.index')) {
            return null;
        }

        try {
            $stats = Cache::remember(self::CACHE_KEY, self::TTL, static fn () => self::collect());

            return self::present($stats);
        } catch (\Throwable $e) {
            Log::error('Techtyl-Uebersicht konnte nicht geladen werden', ['exception' => $e]);

            return null;
        }
    }

    /** Rohdaten aus der Datenbank (nur Skalare, damit sie sich cachen lassen). */
    private static function collect(): array
    {
        $count = static fn (string $table) => DB::table($table)->selectRaw('COUNT(*)');

        $totals = DB::query()
            ->selectSub(DB::table('users')->selectRaw('COUNT(*)'), 'users')
            ->selectSub(DB::table('users')->selectRaw('COUNT(*)')->where('root_admin', true), 'admins')
            ->selectSub(DB::table('users')->selectRaw('COUNT(*)')->where('created_at', '>=', Carbon::now()->subDays(30)), 'users_new')
            ->selectSub(DB::table('users')->selectRaw('COUNT(*)')->where('created_at', '>=', Carbon::now()->subDays(7)), 'users_new7')
            ->selectSub(DB::table('backups')->selectRaw('COUNT(*)')->whereNull('deleted_at')->where('is_successful', true), 'backups')
            ->selectSub(DB::table('backups')->selectRaw('COALESCE(SUM(bytes), 0)')->whereNull('deleted_at')->where('is_successful', true), 'backup_bytes')
            ->selectSub($count('allocations'), 'allocations')
            ->selectSub(DB::table('allocations')->selectRaw('COUNT(*)')->whereNotNull('server_id'), 'allocations_used')
            ->selectSub($count('databases'), 'databases')
            ->selectSub($count('database_hosts'), 'database_hosts')
            ->selectSub($count('nests'), 'nests')
            ->selectSub($count('eggs'), 'eggs')
            ->selectSub($count('locations'), 'locations')
            ->selectSub(DB::table('api_keys')->selectRaw('COUNT(*)')->where('key_type', ApiKey::TYPE_APPLICATION), 'api_keys')
            ->first();

        $servers = DB::table('servers')
            ->selectRaw(
                'COUNT(*) AS total, COALESCE(SUM(status = ?), 0) AS suspended, COALESCE(SUM(status = ?), 0) AS installing, '
                . 'COALESCE(SUM(status IN (?, ?)), 0) AS failed, COALESCE(SUM(memory), 0) AS memory, COALESCE(SUM(disk), 0) AS disk',
                [Server::STATUS_SUSPENDED, Server::STATUS_INSTALLING, Server::STATUS_INSTALL_FAILED, Server::STATUS_REINSTALL_FAILED]
            )
            ->first();

        $perNode = DB::table('servers')
            ->selectRaw('node_id, COUNT(*) AS servers, COALESCE(SUM(memory), 0) AS memory, COALESCE(SUM(disk), 0) AS disk')
            ->groupBy('node_id');

        $perNodeAllocations = DB::table('allocations')
            ->selectRaw('node_id, COUNT(*) AS total, COALESCE(SUM(server_id IS NULL), 0) AS free')
            ->groupBy('node_id');

        $nodes = DB::table('nodes')
            ->leftJoin('locations', 'locations.id', '=', 'nodes.location_id')
            ->leftJoinSub($perNode, 'usage', 'usage.node_id', '=', 'nodes.id')
            ->leftJoinSub($perNodeAllocations, 'ports', 'ports.node_id', '=', 'nodes.id')
            ->orderBy('nodes.name')
            ->get([
                'nodes.id', 'nodes.name', 'nodes.memory', 'nodes.disk', 'nodes.maintenance_mode',
                'locations.short AS location',
                'usage.servers', 'usage.memory AS memory_used', 'usage.disk AS disk_used',
                'ports.total AS ports_total', 'ports.free AS ports_free',
            ])
            ->map(static fn ($node) => [
                'id' => (int) $node->id,
                'name' => (string) $node->name,
                'location' => (string) ($node->location ?? ''),
                'maintenance' => (bool) $node->maintenance_mode,
                'servers' => (int) ($node->servers ?? 0),
                'ports_total' => (int) ($node->ports_total ?? 0),
                'ports_free' => (int) ($node->ports_free ?? 0),
                'memory' => (int) $node->memory,
                'memory_used' => (int) ($node->memory_used ?? 0),
                'disk' => (int) $node->disk,
                'disk_used' => (int) ($node->disk_used ?? 0),
            ])
            ->all();

        $recent = DB::table('servers')
            ->join('nodes', 'nodes.id', '=', 'servers.node_id')
            ->join('users', 'users.id', '=', 'servers.owner_id')
            ->orderByDesc('servers.created_at')
            ->orderByDesc('servers.id')
            ->limit(self::LIST_LIMIT)
            ->get([
                'servers.id', 'servers.name', 'servers.uuidShort AS short', 'servers.status', 'servers.created_at',
                'nodes.id AS node_id', 'nodes.name AS node_name', 'users.id AS user_id', 'users.username',
            ])
            ->map(static fn ($server) => [
                'id' => (int) $server->id,
                'name' => (string) $server->name,
                'short' => (string) $server->short,
                'status' => $server->status !== null ? (string) $server->status : null,
                'created_at' => $server->created_at !== null ? (string) $server->created_at : null,
                'node_id' => (int) $server->node_id,
                'node_name' => (string) $server->node_name,
                'user_id' => (int) $server->user_id,
                'username' => (string) $server->username,
            ])
            ->all();

        $eggs = DB::table('servers')
            ->join('eggs', 'eggs.id', '=', 'servers.egg_id')
            ->join('nests', 'nests.id', '=', 'eggs.nest_id')
            ->select(['eggs.id', 'eggs.name', 'nests.name AS nest'])
            ->selectRaw('COUNT(*) AS servers')
            ->groupBy('eggs.id', 'eggs.name', 'nests.name')
            ->orderByDesc('servers')
            ->orderBy('eggs.name')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(static fn ($egg) => [
                'id' => (int) $egg->id,
                'name' => (string) $egg->name,
                'nest' => (string) $egg->nest,
                'servers' => (int) $egg->servers,
            ])
            ->all();

        // Nur Beschreibung und oeffentliche Kennung, nie das Token.
        $keys = DB::table('api_keys')
            ->where('key_type', ApiKey::TYPE_APPLICATION)
            ->orderByRaw('last_used_at IS NULL')
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->limit(self::LIST_LIMIT)
            ->get(['identifier', 'memo', 'last_used_at', 'created_at'])
            ->map(static fn ($key) => [
                'identifier' => (string) $key->identifier,
                'memo' => (string) ($key->memo ?? ''),
                'last_used_at' => $key->last_used_at !== null ? (string) $key->last_used_at : null,
            ])
            ->all();

        return [
            'at' => Carbon::now()->getTimestamp(),
            'totals' => array_map('intval', (array) $totals),
            'servers' => array_map('intval', (array) $servers),
            'nodes' => $nodes,
            'recent' => $recent,
            'keys' => $keys,
            'eggs' => $eggs,
            'system' => [
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'os' => self::operatingSystem(),
            ],
        ];
    }

    /** Bereitet die Rohdaten fuer die Ansicht auf (Einheiten, Prozent, relative Zeiten). */
    private static function present(array $stats): array
    {
        $totals = $stats['totals'];
        $servers = $stats['servers'];
        $nodes = $stats['nodes'];

        $memoryCapacity = array_sum(array_column($nodes, 'memory'));
        $diskCapacity = array_sum(array_column($nodes, 'disk'));

        $nodeRows = [];
        foreach (array_slice($nodes, 0, self::NODE_LIMIT) as $node) {
            $nodeRows[] = [
                'id' => $node['id'],
                'name' => $node['name'],
                'location' => $node['location'],
                'maintenance' => $node['maintenance'],
                'servers' => $node['servers'],
                'memory' => self::meter($node['memory_used'], $node['memory']),
                'disk' => self::meter($node['disk_used'], $node['disk']),
            ];
        }

        $total = $servers['total'];
        $failed = $servers['failed'];
        $installing = $servers['installing'];
        $suspended = $servers['suspended'];

        $data = [
            'servers' => [
                'total' => $total,
                'active' => max(0, $total - $suspended - $failed - $installing),
                'suspended' => $suspended,
                'installing' => $installing,
                'failed' => $failed,
            ],
            'memory' => self::meter($servers['memory'], $memoryCapacity),
            'disk' => self::meter($servers['disk'], $diskCapacity),
            'users' => [
                'total' => $totals['users'],
                'admins' => $totals['admins'],
                'new' => $totals['users_new'],
            ],
            'nodes' => $nodeRows,
            'node_count' => count($nodes),
            'node_more' => max(0, count($nodes) - self::NODE_LIMIT),
            'maintenance' => count(array_filter($nodes, static fn ($node) => $node['maintenance'])),
            'allocations' => [
                'total' => $totals['allocations'],
                'used' => $totals['allocations_used'],
                'free' => max(0, $totals['allocations'] - $totals['allocations_used']),
                'percent' => self::percent($totals['allocations_used'], $totals['allocations']),
            ],
            'databases' => $totals['databases'],
            'database_hosts' => $totals['database_hosts'],
            'nests' => $totals['nests'],
            'eggs' => $totals['eggs'],
            'locations' => $totals['locations'],
            'recent' => array_map(static fn ($server) => $server + [
                'ago' => self::ago($server['created_at']),
                'state' => self::serverState($server['status']),
            ], $stats['recent']),
            'keys' => array_map(static fn ($key) => $key + [
                'used_ago' => $key['last_used_at'] !== null ? self::ago($key['last_used_at']) : null,
            ], $stats['keys']),
            'key_count' => $totals['api_keys'],
            'system' => $stats['system'] + ['panel' => (string) config('app.version')],
            'update' => self::update(),
            'age' => Carbon::now()->getTimestamp() - $stats['at'] < 10
                ? I18n::t('overview.just_now')
                : Carbon::createFromTimestamp($stats['at'])->locale(I18n::locale())->diffForHumans(),
            'locale' => I18n::locale(),
            'intl' => I18n::locale() === 'de' ? 'de-DE' : 'en-US',
        ];

        $config = Cards::config();
        $arranged = Cards::arrange(self::availableCards($data, $stats, $config), $config);
        $data['cards'] = array_values(array_filter($arranged, static fn ($card) => $card['visible']));
        $data['catalog'] = $arranged;

        $errors = session('errors');
        $data['customize'] = [
            'enabled' => Route::has('techtyl.overview.cards') && Route::has('techtyl.overview.cards.custom'),
            'open' => session()->has(CardsController::OPEN_FLAG)
                || ($errors instanceof ViewErrorBag && $errors->hasAny(['title', 'text', 'link', 'icon', 'order', 'visible', 'move', 'remove'])),
            'icons' => Cards::ICONS,
            'custom_count' => count($config['custom']),
            'custom_max' => Cards::MAX_CUSTOM,
            'title_max' => Cards::TITLE_MAX,
            'text_max' => Cards::TEXT_MAX,
            'link_max' => Cards::LINK_MAX,
        ];

        return $data;
    }

    /**
     * Every card that could be shown, in default order, as id => tile. A tile is a flat array the
     * view renders the same way for built-in, module and custom cards (see tile()).
     */
    private static function availableCards(array $data, array $stats, array $config): array
    {
        $t = static fn (string $key, array $replace = []): string => I18n::t('overview.' . $key, $replace);
        $plural = static fn (int $count, string $one, string $many): string => $t($count === 1 ? $one : $many, ['count' => self::number($count)]);
        $totals = $stats['totals'];
        $servers = $data['servers'];
        $cards = [];

        $cards['servers'] = self::tile([
            'title' => $t('kpi_servers'), 'icon' => 'server', 'link' => route('admin.servers'),
            'value' => self::number($servers['total']), 'count' => $servers['total'],
            'stack' => $servers['total'] > 0 ? array_values(array_filter([
                ['share' => $servers['active'], 'tone' => 'text'],
                ['share' => $servers['installing'], 'tone' => 'warn'],
                ['share' => $servers['suspended'], 'tone' => 'bad'],
                ['share' => $servers['failed'], 'tone' => 'bad'],
            ], static fn ($part) => $part['share'] > 0)) : null,
            'stack_aria' => $t('servers_aria', [
                'active' => $servers['active'], 'suspended' => $servers['suspended'],
                'installing' => $servers['installing'], 'failed' => $servers['failed'],
            ]),
            'legend' => array_values(array_filter([
                ['tone' => 'text', 'text' => $t('servers_active', ['count' => self::number($servers['active'])])],
                ['tone' => $servers['suspended'] > 0 ? 'bad' : 'muted', 'text' => $t('servers_suspended', ['count' => self::number($servers['suspended'])])],
                $servers['installing'] > 0 ? ['tone' => 'warn', 'text' => $t('servers_installing', ['count' => self::number($servers['installing'])])] : null,
                $servers['failed'] > 0 ? ['tone' => 'bad', 'text' => $t('servers_failed', ['count' => self::number($servers['failed'])])] : null,
            ])),
        ]);

        foreach (['memory' => ['kpi_memory', 'microchip'], 'disk' => ['kpi_disk', 'hdd-o']] as $key => [$label, $icon]) {
            $meter = $data[$key];
            $cards[$key] = self::tile([
                'title' => $t($label), 'icon' => $icon, 'link' => route('admin.nodes'),
                'value' => $meter['used']['text'], 'unit' => $meter['used']['unit'],
                'count' => $meter['used']['number'], 'decimals' => $meter['used']['decimals'],
                'meter' => $meter['percent'] !== null
                    ? ['percent' => $meter['percent'], 'tone' => $meter['tone'], 'aria' => $t('capacity_aria', ['percent' => $meter['percent']])]
                    : null,
                'sub_strong' => $meter['percent_text'],
                'sub' => $meter['percent'] !== null
                    ? $t('of_capacity', ['capacity' => $meter['capacity_text']]) . ($meter['percent'] > 100 ? ' · ' . $t('overallocated') : '')
                    : $t('no_capacity'),
            ]);
        }

        $users = $data['users'];
        $cards['users'] = self::tile([
            'title' => $t('kpi_users'), 'icon' => 'users', 'link' => route('admin.users'),
            'value' => self::number($users['total']), 'count' => $users['total'],
            'sub' => $plural($users['admins'], 'admins_one', 'admins_many') . ' · '
                . ($users['new'] > 0 ? $t('users_new', ['count' => self::number($users['new'])]) : $t('users_new_none')),
        ]);

        // Module cards right after the main figures (only enabled modules that ship a widget).
        foreach (Widgets::collect() as $widget) {
            $cards['module:' . $widget['module']] = self::tile([
                'kind' => 'module', 'title' => $widget['title'], 'icon' => $widget['icon'], 'link' => $widget['link'],
                'value' => $widget['value'], 'sub' => $widget['hint'],
                'sub_tone' => $widget['tone'] !== 'neutral' ? $widget['tone'] : null,
            ]);
        }

        $cards['users_new'] = self::tile([
            'title' => $t('kpi_users_new'), 'icon' => 'user-plus', 'link' => route('admin.users'),
            'value' => self::number($totals['users_new7']), 'count' => $totals['users_new7'],
            'sub' => $t('users_new', ['count' => self::number($totals['users_new'])]),
        ]);

        $cards['suspended'] = self::tile([
            'title' => $t('kpi_suspended'), 'icon' => 'ban', 'link' => route('admin.servers'),
            'value' => self::number($servers['suspended']), 'count' => $servers['suspended'],
            'sub_tone' => $servers['suspended'] > 0 ? 'bad' : 'ok',
            'sub' => $servers['suspended'] > 0
                ? $t('suspended_share', ['percent' => self::percentText((int) self::percent($servers['suspended'], max(1, $servers['total'])))])
                : $t('suspended_none'),
        ]);

        $backupSize = self::size(intdiv(max(0, $totals['backup_bytes']), 1048576));
        $cards['backups'] = self::tile([
            'title' => $t('kpi_backups'), 'icon' => 'archive',
            'value' => self::number($totals['backups']), 'count' => $totals['backups'],
            'sub' => $totals['backups'] > 0
                ? $t('backups_size', ['size' => $backupSize['text'] . "\u{00A0}" . $backupSize['unit']])
                : $t('backups_none'),
        ]);

        $eggRows = [];
        foreach ($stats['eggs'] as $egg) {
            $eggRows[] = [
                'name' => $egg['name'], 'meta' => $egg['nest'], 'value' => self::number($egg['servers']),
                'percent' => (int) self::percent($egg['servers'], max(1, $servers['total'])),
            ];
        }
        $cards['eggs'] = self::tile([
            'title' => $t('kpi_eggs'), 'icon' => 'th-large', 'link' => route('admin.nests'),
            'list' => $eggRows, 'empty' => $t('eggs_empty'),
        ]);

        $cards['databases'] = self::tile([
            'title' => $t('sys_databases'), 'icon' => 'database', 'link' => route('admin.databases'),
            'value' => self::number($data['databases']), 'count' => $data['databases'],
            'sub' => $plural($data['database_hosts'], 'db_hosts_one', 'db_hosts_many'),
        ]);

        $cards['maintenance'] = self::tile([
            'title' => $t('kpi_maintenance'), 'icon' => 'wrench', 'link' => route('admin.nodes'),
            'value' => self::number($data['maintenance']), 'count' => $data['maintenance'],
            'sub_tone' => $data['maintenance'] > 0 ? 'warn' : 'ok',
            'sub' => $data['maintenance'] > 0
                ? $t('maintenance_of', ['total' => self::number($data['node_count'])])
                : $t('maintenance_none'),
        ]);

        $noReserve = count(array_filter($stats['nodes'], static fn ($node) => $node['ports_free'] === 0));
        $allocations = $data['allocations'];
        $cards['allocations'] = self::tile([
            'title' => $t('kpi_allocations'), 'icon' => 'plug', 'link' => route('admin.nodes'),
            'value' => self::number($allocations['free']), 'count' => $allocations['free'],
            'meter' => $allocations['percent'] !== null
                ? ['percent' => $allocations['percent'], 'tone' => $allocations['percent'] >= 90 ? 'warn' : 'ok', 'aria' => $t('alloc_aria', ['percent' => $allocations['percent']])]
                : null,
            'sub_tone' => $noReserve > 0 ? 'warn' : 'ok',
            'sub' => $noReserve > 0
                ? $plural($noReserve, 'alloc_no_reserve_one', 'alloc_no_reserve_many')
                : $t('alloc_reserve_ok'),
        ]);

        foreach ($config['custom'] as $id => $custom) {
            $cards['custom:' . $id] = self::tile([
                'kind' => 'custom', 'title' => $custom['title'], 'icon' => $custom['icon'],
                'link' => $custom['link'], 'external' => $custom['link'] !== null, 'text' => $custom['text'],
            ]);
        }

        return $cards;
    }

    /** Fills a tile with defaults so the view never has to guess which keys exist. */
    private static function tile(array $tile): array
    {
        return $tile + [
            'kind' => 'builtin', 'title' => '', 'icon' => 'square-o', 'link' => null, 'external' => false,
            'value' => null, 'unit' => null, 'count' => null, 'decimals' => 0,
            'meter' => null, 'stack' => null, 'stack_aria' => '', 'legend' => [],
            'sub' => null, 'sub_strong' => null, 'sub_tone' => null,
            'list' => null, 'empty' => null, 'text' => null, 'wide' => false,
        ];
    }

    /**
     * Zugewiesen gegenueber Kapazitaet (beides in MiB).
     *
     * @return array{used: array, capacity: array, capacity_text: string, percent: ?int, percent_text: ?string, tone: string}
     */
    private static function meter(int $used, int $capacity): array
    {
        $percent = self::percent($used, $capacity);
        $capacitySize = self::size($capacity);

        return [
            'used' => self::size($used),
            'capacity' => $capacitySize,
            'capacity_text' => $capacitySize['text'] . "\u{00A0}" . $capacitySize['unit'],
            'percent' => $percent,
            'percent_text' => $percent !== null ? self::percentText($percent) : null,
            'tone' => $percent === null ? 'none' : ($percent > 100 ? 'bad' : ($percent >= 85 ? 'warn' : 'ok')),
        ];
    }

    private static function percent(int $part, int $whole): ?int
    {
        return $whole > 0 ? (int) round($part * 100 / $whole) : null;
    }

    /**
     * MiB als lesbare Groesse. "number" und "decimals" braucht das Hochzaehlen im Skript.
     *
     * @return array{text: string, unit: string, number: float, decimals: int}
     */
    public static function size(int $mib): array
    {
        $units = ['MiB', 'GiB', 'TiB'];
        $value = (float) max(0, $mib);
        $unit = 0;
        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }
        $decimals = $unit === 0 || abs($value - round($value)) < 0.05 ? 0 : 1;
        $value = round($value, $decimals);

        return [
            'text' => self::number($value, $decimals),
            'unit' => $units[$unit],
            'number' => $value,
            'decimals' => $decimals,
        ];
    }

    /** Number in the overview language: 1,240.5 (en) or 1.240,5 (de). */
    public static function number(float|int $value, int $decimals = 0): string
    {
        return I18n::locale() === 'de'
            ? number_format($value, $decimals, ',', '.')
            : number_format($value, $decimals, '.', ',');
    }

    /** Percent in the overview language: 39% (en) or 39 % (de, non-breaking space). */
    public static function percentText(int $percent): string
    {
        return self::number($percent) . (I18n::locale() === 'de' ? "\u{00A0}%" : '%');
    }

    private static function ago(?string $timestamp): ?string
    {
        if ($timestamp === null) {
            return null;
        }
        try {
            return Carbon::parse($timestamp)->locale(I18n::locale())->diffForHumans();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{label: string, tone: string}|null Nur fuer Zustaende, die Aufmerksamkeit brauchen. */
    private static function serverState(?string $status): ?array
    {
        return match ($status) {
            Server::STATUS_SUSPENDED => ['label' => I18n::t('overview.state_suspended'), 'tone' => 'bad'],
            Server::STATUS_INSTALLING => ['label' => I18n::t('overview.state_installing'), 'tone' => 'warn'],
            Server::STATUS_INSTALL_FAILED, Server::STATUS_REINSTALL_FAILED => ['label' => I18n::t('overview.state_failed'), 'tone' => 'bad'],
            Server::STATUS_RESTORING_BACKUP => ['label' => I18n::t('overview.state_restoring'), 'tone' => 'warn'],
            default => null,
        };
    }

    /**
     * Update-Stand aus dem Versionsdienst des Panels (der Panel-Controller hat ihn fuer diese Seite
     * bereits geladen, der Wert liegt im Panel-Cache).
     *
     * @return array{state: string, current: string, latest: ?string, release: ?string, guide: string, discord: string, donate: string}
     */
    private static function update(): array
    {
        $current = (string) config('app.version');
        $result = [
            'state' => 'unknown',
            'current' => $current,
            'latest' => null,
            'release' => null,
            'guide' => 'https://pterodactyl.io/panel/1.0/updating.html',
            'discord' => 'https://pterodactyl.io/discord',
            'donate' => 'https://github.com/sponsors/matthewpi',
        ];

        try {
            $service = app(SoftwareVersionService::class);
            $latest = $service->getPanel();
            $result['discord'] = self::httpsOr($service->getDiscord(), $result['discord']);
            $result['donate'] = self::httpsOr($service->getDonations(), $result['donate']);
            if (preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.]+)?$/', $latest) === 1) {
                $result['latest'] = $latest;
                $result['release'] = 'https://github.com/pterodactyl/panel/releases/v' . $latest;
                $result['state'] = $service->isLatestPanel() ? 'current' : 'available';
            }
        } catch (\Throwable $e) {
            Log::info('Techtyl-Uebersicht: Versionsstand nicht verfuegbar', ['exception' => $e->getMessage()]);
        }

        return $result;
    }

    private static function httpsOr(string $url, string $fallback): string
    {
        return str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : $fallback;
    }

    private static function operatingSystem(): string
    {
        $name = '';
        $release = @is_readable('/etc/os-release') ? @file_get_contents('/etc/os-release', false, null, 0, 4096) : false;
        if (is_string($release) && preg_match('/^PRETTY_NAME=["\']?([^"\'\r\n]{1,60})/m', $release, $match) === 1) {
            $name = $match[1];
        }
        if ($name === '') {
            $name = PHP_OS_FAMILY;
        }
        $kernel = function_exists('php_uname') ? (string) @php_uname('r') : '';

        return mb_substr(trim($name . ($kernel !== '' ? ' · Kernel ' . $kernel : '')), 0, 80);
    }
}
