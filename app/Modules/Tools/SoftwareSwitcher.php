<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Facades\Activity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Pterodactyl\Exceptions\DisplayException;
use Illuminate\Validation\ValidationException;
use Pterodactyl\Services\Backups\InitiateBackupService;
use Pterodactyl\Services\Servers\ReinstallServerService;
use Pterodactyl\Services\Servers\StartupModificationService;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;

/**
 * Software switch (task "software.switch").
 *
 * A user picks a loader type. The server resolves that type to the single allowed egg
 * (lowest id). Nothing is deleted: plugins/ or mods/ are renamed aside, and the old jar
 * is renamed. Egg, nest, startup, image and variables are written through the panel's
 * StartupModificationService inside a transaction. The egg install script then runs.
 * A step never waits. Expected failures call fail(), they do not throw, so the task is
 * not retried into a second rename.
 */
final class SoftwareSwitcher
{
    public const STEPS = [
        '' => 'step_prepare',
        'stop' => 'step_stop',
        'backup' => 'step_backup',
        'move' => 'step_move',
        'configure' => 'step_configure',
        'install' => 'step_reinstall',
    ];

    /** Variable => value key. Every one of these must exist on the target egg and be editable. */
    private const REQUIRED = [
        'paper' => ['MINECRAFT_VERSION' => 'game', 'BUILD_NUMBER' => 'build', 'SERVER_JARFILE' => 'jar'],
        'folia' => ['MINECRAFT_VERSION' => 'game', 'BUILD_NUMBER' => 'build', 'SERVER_JARFILE' => 'jar'],
        'purpur' => ['MINECRAFT_VERSION' => 'game', 'BUILD_NUMBER' => 'build', 'SERVER_JARFILE' => 'jar'],
        'vanilla' => ['VANILLA_VERSION' => 'game', 'SERVER_JARFILE' => 'jar'],
        'fabric' => ['MC_VERSION' => 'game', 'LOADER_VERSION' => 'build', 'FABRIC_VERSION' => 'installer', 'SERVER_JARFILE' => 'jar'],
        'forge' => ['MC_VERSION' => 'game', 'FORGE_VERSION' => 'forge_version', 'BUILD_TYPE' => 'build_type', 'SERVER_JARFILE' => 'jar'],
        'neoforge' => ['NEOFORGE_VERSION' => 'neoforge_version', 'SERVER_JARFILE' => 'jar'],
    ];

    /** Set when the egg has them. A locked or illegal value refuses the switch. */
    private const OPTIONAL = [
        'neoforge' => ['MC_VERSION' => 'game', 'MINECRAFT_VERSION' => 'game'],
    ];

    /** Cleared so a stale download URL cannot override the installer. Not user-editable on stock eggs. */
    private const CLEAR = [
        'paper' => ['DL_PATH'],
        'folia' => ['DL_PATH'],
    ];

    private const INSTALL_TIMEOUT = 1500;

    /** What the versions page needs before it offers a switch. Reading does not change the server. */
    public static function describe(Server $server, User $user): array
    {
        $type = ServerTypes::forServer($server)['type'];
        $offered = in_array($type, ServerTypes::SOFTWARE, true);
        $canRead = $user->can('startup.read', $server);
        $canUpdate = $user->can('startup.update', $server);
        $canReinstall = $user->can('settings.reinstall', $server);
        $canImage = $user->can('startup.docker-image', $server);
        $canStop = $user->can('control.stop', $server);
        $canStart = $user->can('control.start', $server);
        $canBackup = $user->can('backup.create', $server);
        $enabled = SoftwarePolicy::enabled();

        $locked = null;
        if (!$canRead) {
            $locked = 'software_no_read';
        } elseif (!$canUpdate) {
            $locked = 'software_no_update';
        } elseif (!$canReinstall) {
            $locked = 'software_no_reinstall_right';
        } elseif (!$enabled) {
            $locked = 'software_off';
        }

        $blocked = $locked === null ? self::serverBlock($server) : null;
        $online = null;
        if ($locked === null && $blocked === null && $offered) {
            try {
                $online = Wings::for($server)->state() !== 'offline';
            } catch (\Throwable $e) {
                Log::warning('techtyl tools: software state unreadable', ['server' => $server->uuid, 'error' => substr($e->getMessage(), 0, 200)]);
                $blocked = 'software_node';
            }
        }

        $eggs = ($locked === null && $blocked === null) ? SoftwarePolicy::eggsByLoader() : [];
        $loaders = [];
        if ($offered) {
            foreach (ServerTypes::SOFTWARE as $loader) {
                $row = [
                    'id' => $loader,
                    'label' => ServerTypes::label($loader),
                    'blurb' => I18n::t('tools.software_blurb_' . $loader),
                    'available' => false,
                    'reason' => null,
                ];
                if ($loader === $type) {
                    $row['reason'] = I18n::t('tools.software_reason_same');
                } elseif ($locked !== null) {
                    $row['reason'] = I18n::t('tools.' . $locked);
                } elseif ($blocked !== null) {
                    $row['reason'] = I18n::t('tools.' . $blocked);
                } elseif (!isset($eggs[$loader])) {
                    $row['reason'] = I18n::t('tools.software_reason_no_egg');
                } else {
                    $row['available'] = true;
                }
                $loaders[] = $row;
            }
        }

        $reason = $locked ?? $blocked;

        return [
            'offered' => $offered,
            'loader' => $offered ? $type : null,
            'loader_label' => $offered ? ServerTypes::label($type) : null,
            'enabled' => $enabled,
            'locked' => $reason !== null,
            'locked_reason' => $reason !== null ? I18n::t('tools.' . $reason) : null,
            'online' => $online,
            'can_stop' => $canStop,
            'can_start' => $canStart,
            'can_image' => $canImage,
            'can_backup' => $canBackup,
            'backup' => self::backupState($server, $user),
            'loaders' => $loaders,
            'switching' => Tasks::hasActive($server, 'software.switch'),
        ];
    }

    /**
     * Read-only plan. The browser never sends an egg id. internal is removed by the controller.
     *
     * @return array<string, mixed>
     */
    public static function plan(Server $server, User $user, string $loader, string $game, string $build): array
    {
        $from = ServerTypes::forServer($server)['type'];
        if (!in_array($from, ServerTypes::SOFTWARE, true)) {
            throw ToolsException::conflict('software_not_offered');
        }
        if (!in_array($loader, ServerTypes::SOFTWARE, true)) {
            throw ToolsException::invalid('software_loader_invalid');
        }
        if ($loader === $from) {
            throw ToolsException::conflict('software_same');
        }
        if (!SoftwarePolicy::enabled()) {
            throw ToolsException::conflict('software_off');
        }
        if (!VersionSources::validId($game) || !VersionSources::validId($build)) {
            throw ToolsException::invalid('software_input');
        }

        $egg = SoftwarePolicy::eggFor($loader);
        if ($egg === null) {
            throw ToolsException::conflict('software_no_egg');
        }
        $resolved = VersionSources::resolve($loader, $game, $build);
        $assess = self::assess($server, $egg, $loader, $game, $build, $resolved['extra']);
        if (!$assess['ok']) {
            throw ToolsException::conflict((string) $assess['error'], $assess['params']);
        }

        $image = self::imageChoice($server, $egg, $user, (int) $resolved['java']);
        $folders = self::folders($from, $loader);
        $left = self::leftBehind($from, $loader);
        $backup = self::backupState($server, $user);
        $blocked = self::serverBlock($server);
        $online = null;
        try {
            $online = Wings::for($server)->state() !== 'offline';
        } catch (\Throwable $e) {
            Log::warning('techtyl tools: software preview state unreadable', ['server' => $server->uuid, 'error' => substr($e->getMessage(), 0, 200)]);
            $blocked = $blocked ?? 'software_node';
        }

        $block = $blocked;
        $blockKind = 'conflict';
        $blockParams = [];
        if ($block === null && $online && !$user->can('control.stop', $server)) {
            $block = 'cant_stop';
            $blockKind = 'forbidden';
        }
        if ($block === null && !$user->can('startup.update', $server)) {
            $block = 'software_no_update';
            $blockKind = 'forbidden';
        }
        if ($block === null && !$user->can('settings.reinstall', $server)) {
            $block = 'software_no_reinstall_right';
            $blockKind = 'forbidden';
        }
        if ($block === null && !$image['ok']) {
            $block = $image['error'];
            $blockParams = $image['params'];
        }

        $warnings = [];
        $warnings[] = ['level' => 'warning', 'text' => I18n::t('tools.software_warn_backup')];
        $warnings[] = ['level' => 'info', 'text' => I18n::t('tools.software_warn_script')];
        if ($folders === ['plugins']) {
            $warnings[] = ['level' => 'warning', 'text' => I18n::t('tools.software_warn_plugins')];
        } elseif ($folders === ['mods'] && in_array($from, ServerTypes::MOD_LOADERS, true) && in_array($loader, ServerTypes::MOD_LOADERS, true)) {
            $warnings[] = ['level' => 'warning', 'text' => I18n::t('tools.software_warn_family')];
        } elseif ($folders === ['mods']) {
            $warnings[] = ['level' => 'warning', 'text' => I18n::t('tools.software_warn_mods')];
        }
        if ($left !== null) {
            $warnings[] = ['level' => 'info', 'text' => I18n::t('tools.software_warn_left', ['folder' => $left])];
        }
        if ((int) $egg->nest_id !== (int) $server->nest_id) {
            $warnings[] = ['level' => 'info', 'text' => I18n::t('tools.software_warn_nest', ['nest' => (string) ($egg->nest->name ?? '')])];
        }
        if (in_array($loader, ['forge', 'neoforge'], true)) {
            $warnings[] = ['level' => 'warning', 'text' => I18n::t('tools.software_warn_installer')];
        }
        if ($loader === 'folia') {
            $warnings[] = ['level' => 'warning', 'text' => I18n::t('tools.software_warn_folia')];
        }
        $compared = VersionSources::compare(ServerMeta::gameVersion($server), $game);
        if ($compared !== null && $compared > 0) {
            $warnings[] = ['level' => 'danger', 'text' => I18n::t('tools.software_warn_old_backup')];
        } else {
            $warnings[] = ['level' => 'info', 'text' => I18n::t('tools.software_warn_old_backup')];
        }
        if ($image['ok'] && !$image['keep'] && $image['label'] !== '') {
            $warnings[] = ['level' => 'info', 'text' => I18n::t('tools.software_warn_java', ['image' => $image['label'], 'java' => (int) $resolved['java']])];
        }
        if (!$image['ok']) {
            $warnings[] = ['level' => 'danger', 'text' => I18n::t('tools.' . $image['error'], $image['params'])];
        }
        if ($block !== null && $block !== ($image['ok'] ? '' : $image['error'])) {
            $warnings[] = ['level' => 'danger', 'text' => I18n::t('tools.' . $block, $blockParams)];
        }

        return [
            'loader' => $loader,
            'loader_label' => ServerTypes::label($loader),
            'confirm_word' => ServerTypes::label($loader),
            'from' => [
                'loader' => $from,
                'loader_label' => ServerTypes::label($from),
                'egg' => (string) $server->egg->name,
                'nest' => (string) ($server->nest->name ?? ''),
            ],
            'to' => [
                'loader' => $loader,
                'loader_label' => ServerTypes::label($loader),
                'game' => $game,
                'build' => $build,
                'egg' => (string) $egg->name,
                'nest' => (string) ($egg->nest->name ?? ''),
            ],
            'startup' => (string) $egg->startup,
            'image' => [
                'from' => $image['from_label'],
                'to' => $image['keep'] ? null : $image['label'],
                'keep' => $image['keep'],
                'java' => (int) $resolved['java'],
            ],
            'variables' => $assess['variables'],
            'moves' => array_map(fn ($name) => ['kind' => 'folder', 'name' => $name], $folders),
            'jar' => $assess['jar'],
            'online' => $online === true,
            'can_stop' => $online !== true || $user->can('control.stop', $server),
            'can_start' => $user->can('control.start', $server),
            'can_switch' => $block === null,
            'backup' => $backup,
            'warnings' => $warnings,
            'internal' => [
                'egg_id' => (int) $egg->id,
                'extra' => $resolved['extra'],
                'folders' => $folders,
                'jar' => $assess['jar'],
                'block' => $block,
                'block_kind' => $blockKind,
                'block_params' => $blockParams,
                'image' => $image['image'],
                'image_keep' => $image['keep'],
                'image_label' => $image['label'],
            ],
        ];
    }

    public static function queue(Server $server, User $user, string $loader, string $game, string $build, string $confirm, bool $backup, bool $start): object
    {
        if (Tasks::hasActive($server, 'software.switch') || Tasks::hasActive($server, 'version.switch')) {
            throw ToolsException::conflict('software_busy');
        }
        $plan = self::plan($server, $user, $loader, $game, $build);
        if (!self::confirmed($confirm, (string) $plan['confirm_word'])) {
            throw ToolsException::invalid('software_confirm');
        }
        if (!$plan['can_switch']) {
            $key = (string) ($plan['internal']['block'] ?? 'software_input');
            $params = is_array($plan['internal']['block_params'] ?? null) ? $plan['internal']['block_params'] : [];
            if (($plan['internal']['block_kind'] ?? '') === 'forbidden') {
                throw ToolsException::forbidden($key, $params);
            }
            throw ToolsException::conflict($key, $params);
        }
        if ($backup && !$plan['backup']['possible']) {
            throw ToolsException::conflict('software_no_backup');
        }
        if ($start && !$plan['can_start']) {
            throw ToolsException::forbidden('cant_start');
        }
        if ($backup && !$user->can('backup.create', $server)) {
            throw ToolsException::forbidden('software_no_backup');
        }

        $internal = $plan['internal'];
        $label = ServerTypes::label($loader) . ' ' . $game;
        $payload = [
            'loader' => $loader,
            'game' => $game,
            'build' => $build,
            'extra' => $internal['extra'],
            'from' => $plan['from'],
            'egg_id' => $internal['egg_id'],
            'egg_from_name' => $plan['from']['egg'],
            'egg_to_name' => $plan['to']['egg'],
            'nest_to' => $plan['to']['nest'],
            'folders' => $internal['folders'],
            'jar' => $internal['jar'],
            'image_to' => $internal['image'],
            'image_label' => $internal['image_label'],
            'image_from' => (string) $server->image,
            'options' => ['backup' => $backup, 'start' => $start],
            'user_id' => $user->id,
            'state' => [],
            'public' => [
                'loader' => $loader,
                'game' => $game,
                'build' => $build,
                'from' => $plan['from']['loader'],
                'backup' => $backup,
                'start' => $start,
                'mode' => 'reinstall',
            ],
        ];

        return Tasks::create($server, $user, 'software.switch', $label, $payload, function (Collection $active) {
            if ($active->isNotEmpty()) {
                throw ToolsException::conflict('software_busy');
            }
        });
    }

    public static function advance(TaskContext $task): void
    {
        $payload = &$task->payload;
        $state = &$payload['state'];
        $server = $task->server;
        if ($server === null) {
            $task->fail('task_stuck');

            return;
        }

        switch ($task->step) {
            case '':
                $problem = self::validate($task);
                if ($problem !== null) {
                    self::stop($task, $problem[0], $problem[1]);

                    return;
                }
                $state['validated'] = true;
                try {
                    $online = $task->wings()->state() !== 'offline';
                } catch (\Throwable $e) {
                    Log::warning('techtyl tools: software switch cannot read state', ['server' => $server->uuid, 'error' => substr($e->getMessage(), 0, 200)]);
                    self::stop($task, 'software_node');

                    return;
                }
                $state['was_running'] = $online;
                if ($online) {
                    $task->step('stop', 15);

                    return;
                }
                $task->step(!empty($payload['options']['backup']) ? 'backup' : 'move', !empty($payload['options']['backup']) ? 30 : 45);

                return;

            case 'stop':
                $offline = $task->wings()->state() === 'offline';
                if ($offline) {
                    $task->step(!empty($payload['options']['backup']) ? 'backup' : 'move', !empty($payload['options']['backup']) ? 30 : 45);

                    return;
                }
                if (empty($state['stop_sent'])) {
                    $task->wings()->power('stop');
                    $state['stop_sent'] = time();
                } elseif (time() - (int) $state['stop_sent'] > 150) {
                    self::stop($task, 'stop_failed');
                } elseif (time() - (int) $state['stop_sent'] > 75 && empty($state['kill_sent'])) {
                    $task->wings()->power('kill');
                    $state['kill_sent'] = time();
                }

                return;

            case 'backup':
                if (empty($state['backup_uuid'])) {
                    try {
                        $name = I18n::t('tools.software_backup_name', ['loader' => ServerTypes::label((string) $payload['loader']), 'game' => (string) $payload['game']]);
                        $backup = app(InitiateBackupService::class)->handle($server, $name);
                    } catch (\Throwable $e) {
                        Log::warning('techtyl tools: software backup failed to start', ['server' => $server->uuid, 'error' => substr($e->getMessage(), 0, 300)]);
                        self::stop($task, 'software_backup_failed');

                        return;
                    }
                    $state['backup_uuid'] = $backup->uuid;
                    $state['backup_started'] = time();
                    $task->step('backup', 30);

                    return;
                }
                $backup = Backup::query()->where('server_id', $server->id)->where('uuid', (string) $state['backup_uuid'])->first();
                if ($backup === null || ($backup->completed_at !== null && !$backup->is_successful)) {
                    self::stop($task, 'software_backup_failed');

                    return;
                }
                if ($backup->completed_at === null) {
                    if (time() - (int) $state['backup_started'] > self::INSTALL_TIMEOUT) {
                        self::stop($task, 'software_backup_failed');
                    }

                    return;
                }
                $task->step('move', 45);

                return;

            case 'move':
                $problem = self::moveAside($task);
                if ($problem !== null) {
                    self::stop($task, $problem);

                    return;
                }
                $task->step('configure', 60);

                return;

            case 'configure':
                if (empty($state['egg_changed'])) {
                    $problem = self::validate($task, true);
                    if ($problem !== null) {
                        self::stop($task, $problem[0], $problem[1]);

                        return;
                    }
                    try {
                        self::configure($task);
                    } catch (ValidationException $e) {
                        Log::warning('techtyl tools: software settings rejected', ['server' => $server->uuid, 'fields' => array_keys($e->errors())]);
                        self::stop($task, 'software_var_rejected');

                        return;
                    } catch (\RuntimeException $e) {
                        Log::warning('techtyl tools: software settings rolled back', ['server' => $server->uuid, 'error' => substr($e->getMessage(), 0, 200)]);
                        self::stop($task, 'software_settings_failed');

                        return;
                    }
                    $state['egg_changed'] = true;
                    // The egg (and so the server type) changed: forget the cached detection right away.
                    ServerMeta::flush($server);
                }
                if (empty($state['install_triggered'])) {
                    try {
                        app(ReinstallServerService::class)->handle($server->fresh());
                    } catch (DisplayException $e) {
                        Log::warning('techtyl tools: software reinstall refused', ['server' => $server->uuid, 'error' => substr($e->getMessage(), 0, 300)]);
                        self::stop($task, 'software_install_failed');

                        return;
                    }
                    $state['install_triggered'] = true;
                    $state['install_started'] = time();
                }
                $task->step('install', 75);

                return;

            case 'install':
                $fresh = $server->fresh();
                if ($fresh === null) {
                    self::stop($task, 'software_install_failed');

                    return;
                }
                $status = $fresh->status;
                if ($status === null || $status === '') {
                    $started = false;
                    if (!empty($payload['options']['start'])) {
                        $actor = User::query()->find((int) ($payload['user_id'] ?? 0));
                        if ($actor !== null && $actor->can('control.start', $fresh)) {
                            try {
                                $task->wings()->power('start');
                                $started = true;
                            } catch (\Throwable $e) {
                                Log::warning('techtyl tools: start after software switch failed', ['server' => $server->uuid]);
                            }
                        }
                    }
                    $state['jar_size'] = 0;
                    try {
                        $stat = $task->wings()->stat('/', (string) $payload['jar']);
                        $state['jar_size'] = is_array($stat) ? (int) ($stat['size'] ?? 0) : 0;
                    } catch (\Throwable $e) {
                        $state['jar_size'] = 0;
                    }
                    self::activity($task);
                    $result = self::outcome($task, true);
                    $result['started'] = $started;
                    // The jar was replaced by the install script: detect the version again on the next read.
                    ServerMeta::flush($fresh);
                    $task->done($result);

                    return;
                }
                if ($status === Server::STATUS_INSTALL_FAILED || $status === Server::STATUS_REINSTALL_FAILED) {
                    self::stop($task, 'software_install_failed');

                    return;
                }
                $elapsed = time() - (int) ($state['install_started'] ?? time());
                if ($elapsed > self::INSTALL_TIMEOUT) {
                    self::stop($task, 'software_install_timeout');

                    return;
                }
                $task->step('install', min(92, 75 + (int) floor($elapsed / 90)));

                return;
        }

        self::stop($task, 'task_state');
    }

    public static function abort(TaskContext $task): void
    {
        self::maybeRestart($task);
        $task->result = self::outcome($task, false);
    }

    /** @return array{0: string, 1: array<string, string|int>}|null */
    private static function validate(TaskContext $task, bool $afterMove = false): ?array
    {
        $server = $task->server;
        $payload = &$task->payload;
        if ($server === null) {
            return ['task_stuck', []];
        }
        $user = User::query()->find((int) ($payload['user_id'] ?? 0));
        if ($user === null) {
            return ['software_user_gone', []];
        }
        if (!$user->can('startup.read', $server)) {
            return ['software_no_read', []];
        }
        if (!$user->can('startup.update', $server)) {
            return ['software_no_update', []];
        }
        if (!$user->can('settings.reinstall', $server)) {
            return ['software_no_reinstall_right', []];
        }
        if (!SoftwarePolicy::enabled()) {
            return ['software_off', []];
        }
        $from = ServerTypes::forServer($server)['type'];
        $loader = (string) ($payload['loader'] ?? '');
        if (!in_array($loader, ServerTypes::SOFTWARE, true) || !in_array($from, ServerTypes::SOFTWARE, true)) {
            return ['software_not_offered', []];
        }
        // After the egg write, the server already is the target loader. Don't treat that as "same".
        if (!$afterMove && $from === $loader) {
            return ['software_same', []];
        }
        $egg = SoftwarePolicy::eggFor($loader);
        if ($egg === null || (int) $egg->id !== (int) ($payload['egg_id'] ?? 0)) {
            return ['software_changed', []];
        }
        $blocked = self::serverBlock($server);
        if ($blocked !== null && !($afterMove && $blocked === 'server_installing' && !empty($payload['state']['install_triggered']))) {
            // A server we already set to installing is past this check. Any other block still stops us.
            if (!$afterMove || empty($payload['state']['egg_changed'])) {
                return [$blocked, []];
            }
        }
        $others = DB::table(Tasks::TABLE)->where('server_id', $server->id)->whereIn('status', Tasks::ACTIVE)->where('id', '!=', $task->row->id);
        if ($others->exists()) {
            return ['software_busy', []];
        }
        if (!$afterMove && $server->backups()->whereNull('completed_at')->exists()) {
            return ['software_backup_running', []];
        }
        if (!empty($payload['options']['backup']) && !$user->can('backup.create', $server) && empty($payload['state']['backup_uuid'])) {
            return ['software_no_backup', []];
        }
        try {
            $resolved = VersionSources::resolve($loader, (string) $payload['game'], (string) $payload['build']);
        } catch (ToolsException $e) {
            return [$e->key ?? 'software_input', $e->params];
        } catch (\Throwable $e) {
            Log::warning('techtyl tools: software version check failed', ['server' => $server->uuid, 'error' => substr($e->getMessage(), 0, 200)]);

            return ['software_input', []];
        }
        $payload['extra'] = $resolved['extra'];
        $assess = self::assess($server, $egg, $loader, (string) $payload['game'], (string) $payload['build'], $resolved['extra']);
        if (!$assess['ok']) {
            return [(string) $assess['error'], $assess['params']];
        }
        $payload['jar'] = $assess['jar'];
        $image = self::imageChoice($server, $egg, $user, (int) $resolved['java']);
        if (!$image['ok']) {
            return [(string) $image['error'], $image['params']];
        }
        $payload['image_to'] = $image['image'];
        $payload['image_label'] = $image['label'];
        if (!$afterMove) {
            try {
                if ($task->wings()->state() !== 'offline' && !$user->can('control.stop', $server)) {
                    return ['cant_stop', []];
                }
            } catch (\Throwable $e) {
                Log::warning('techtyl tools: software switch cannot read state', ['server' => $server->uuid, 'error' => substr($e->getMessage(), 0, 200)]);

                return ['software_node', []];
            }
        }

        return null;
    }

    /**
     * Rename, never delete. One stamp per task. An existing .techtyl-backup is left in place.
     * Returns an error key, or null when every planned rename is done.
     */
    private static function moveAside(TaskContext $task): ?string
    {
        $state = &$task->payload['state'];
        if (!is_string($state['stamp'] ?? null) || !preg_match('/^\d{14}$/', $state['stamp'])) {
            $state['stamp'] = date('YmdHis');
        }
        $stamp = $state['stamp'];
        $wings = $task->wings();
        $moved = is_array($state['moved'] ?? null) ? $state['moved'] : [];

        foreach ($task->payload['folders'] ?? [] as $name) {
            if ($name !== 'plugins' && $name !== 'mods') {
                return 'software_move_failed';
            }
            $dest = $name . '.techtyl-' . $stamp;
            Wings::filename($dest);
            $listing = self::listing($wings);
            $source = $listing[$name] ?? null;
            $target = $listing[$dest] ?? null;
            if ($source === null && $target !== null) {
                $moved = self::remember($moved, $dest);
                $state['moved'] = $moved;
                continue;
            }
            if ($source === null) {
                continue;
            }
            if (!empty($source['file'])) {
                $state['moved'] = $moved;

                return 'software_move_not_folder';
            }
            if ($target !== null) {
                $state['moved'] = $moved;

                return 'software_move_exists';
            }
            $wings->rename('/', $name, $dest);
            $moved = self::remember($moved, $dest);
            $state['moved'] = $moved;
        }

        $jar = (string) ($task->payload['jar'] ?? '');
        if ($jar === '') {
            return 'software_move_failed';
        }
        $listing = self::listing($wings);
        $plain = $jar . '.techtyl-backup';
        $chosen = is_string($state['jar_backup'] ?? null) ? $state['jar_backup'] : null;
        if ($chosen === null) {
            $chosen = isset($listing[$plain]) ? $jar . '.techtyl-backup-' . $stamp : $plain;
            $state['jar_backup'] = $chosen;
        }
        Wings::filename($chosen);
        $source = $listing[$jar] ?? null;
        $target = $listing[$chosen] ?? null;
        if ($source === null && $target !== null) {
            $moved = self::remember($moved, $chosen);
        } elseif ($source !== null) {
            if (empty($source['file']) || $target !== null) {
                $state['moved'] = $moved;

                return $target !== null ? 'software_move_exists' : 'software_move_failed';
            }
            $wings->rename('/', $jar, $chosen);
            $moved = self::remember($moved, $chosen);
        }
        $state['moved'] = array_values($moved);

        return null;
    }

    /** Writes egg, nest, startup, image and variables. Throws to roll the transaction back. */
    private static function configure(TaskContext $task): void
    {
        $server = $task->server;
        $payload = $task->payload;
        $egg = Egg::query()->with(['variables', 'nest'])->find((int) $payload['egg_id']);
        if ($egg === null) {
            throw new \RuntimeException('egg missing');
        }
        $assess = self::assess($server, $egg, (string) $payload['loader'], (string) $payload['game'], (string) $payload['build'], is_array($payload['extra'] ?? null) ? $payload['extra'] : []);
        if (!$assess['ok']) {
            throw new \RuntimeException('assess');
        }
        if ($server->skip_scripts || !$server->canBeReinstalled()) {
            throw new \RuntimeException('scripts');
        }
        $image = is_string($payload['image_to'] ?? null) && $payload['image_to'] !== '' ? $payload['image_to'] : null;
        $beforeImage = (string) $server->image;
        $data = [
            'egg_id' => (int) $egg->id,
            'startup' => (string) $egg->startup,
            'skip_scripts' => false,
            'environment' => $assess['environment'],
        ];
        if ($image !== null && $image !== (string) $server->image) {
            $data['docker_image'] = $image;
        }
        $required = $assess['required'];

        DB::transaction(function () use ($server, $data, $egg, $required, $image, $beforeImage) {
            $updated = app(StartupModificationService::class)
                ->setUserLevel(User::USER_LEVEL_ADMIN)
                ->handle($server, $data);
            $updated->refresh();
            $updated->unsetRelation('variables');
            if ((int) $updated->egg_id !== (int) $egg->id || (int) $updated->nest_id !== (int) $egg->nest_id) {
                throw new \RuntimeException('egg');
            }
            if ((string) $updated->startup !== (string) $egg->startup || $updated->skip_scripts) {
                throw new \RuntimeException('startup');
            }
            if ($image === null && (string) $updated->image !== $beforeImage) {
                throw new \RuntimeException('image');
            }
            if ($image !== null && (string) $updated->image !== $image) {
                throw new \RuntimeException('image');
            }
            $have = [];
            foreach ($updated->variables as $variable) {
                $have[(string) $variable->env_variable] = (string) $variable->server_value;
            }
            foreach ($required as $key => $value) {
                if (!array_key_exists($key, $have) || $have[$key] !== (string) $value) {
                    throw new \RuntimeException('var');
                }
            }
        });
        $task->payload['state']['image_changed'] = $image !== null && $image !== $beforeImage;
    }

    /**
     * @param array<string, string> $extra
     * @return array{ok: bool, error: ?string, params: array<string, string>, environment: array<string, string>, variables: array<int, array<string, mixed>>, jar: string, required: array<string, string>}
     */
    private static function assess(Server $server, Egg $egg, string $loader, string $game, string $build, array $extra): array
    {
        $fail = function (string $key, array $params = []) {
            return ['ok' => false, 'error' => $key, 'params' => $params, 'environment' => [], 'variables' => [], 'jar' => '', 'required' => []];
        };
        $jar = self::chooseJar($server, $egg);
        if ($jar === null) {
            return $fail('software_var_rules', ['key' => 'SERVER_JARFILE']);
        }
        $values = ['game' => $game, 'build' => $build, 'jar' => $jar] + $extra;
        $overrides = [];
        $public = [];
        $required = [];
        $specs = (self::REQUIRED[$loader] ?? []) + (self::OPTIONAL[$loader] ?? []);
        $optional = self::OPTIONAL[$loader] ?? [];
        foreach ($specs as $key => $source) {
            $variable = $egg->variables->firstWhere('env_variable', $key);
            $isOptional = array_key_exists($key, $optional);
            if ($variable === null) {
                if ($isOptional) {
                    continue;
                }

                return $fail('software_var_missing', ['key' => $key]);
            }
            $to = $values[$source] ?? null;
            if (!is_string($to) || $to === '') {
                return $fail('software_var_missing', ['key' => $key]);
            }
            if (!$variable->user_editable) {
                return $fail('software_var_locked', ['key' => $key]);
            }
            if (Validator::make(['value' => $to], ['value' => $variable->rules])->fails()) {
                return $fail('software_var_rules', ['key' => $key]);
            }
            $overrides[$key] = $to;
            $required[$key] = $to;
            $public[] = [
                'key' => $key,
                'label' => (string) $variable->name,
                'from' => ServerMeta::variable($server, $key),
                'to' => $to,
            ];
        }
        foreach (self::CLEAR[$loader] ?? [] as $key) {
            $variable = $egg->variables->firstWhere('env_variable', $key);
            if ($variable === null) {
                continue;
            }
            if (Validator::make(['value' => ''], ['value' => $variable->rules])->fails()) {
                return $fail('software_var_rules', ['key' => $key]);
            }
            $overrides[$key] = '';
        }

        $environment = [];
        foreach ($egg->variables as $variable) {
            $key = (string) $variable->env_variable;
            $value = array_key_exists($key, $overrides) ? $overrides[$key] : (string) ($variable->default_value ?? '');
            if (Validator::make(['value' => $value], ['value' => $variable->rules])->fails()) {
                return $fail('software_var_rules', ['key' => $key]);
            }
            $environment[$key] = $value;
        }

        return [
            'ok' => true,
            'error' => null,
            'params' => [],
            'environment' => $environment,
            'variables' => $public,
            'jar' => $jar,
            'required' => $required,
        ];
    }

    private static function chooseJar(Server $server, Egg $egg): ?string
    {
        $variable = $egg->variables->firstWhere('env_variable', 'SERVER_JARFILE');
        if ($variable === null) {
            return null;
        }
        foreach ([ServerMeta::jarName($server), 'server.jar'] as $candidate) {
            if (Validator::make(['value' => $candidate], ['value' => $variable->rules])->passes()) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Desired image is the smallest Java on the target egg that is new enough.
     * The current image is kept when it is already that image. Without the image
     * permission, only the egg default is acceptable, and only when it is the desired one.
     *
     * @return array{ok: bool, error: string, params: array<string, int|string>, image: ?string, keep: bool, label: string, from_label: string}
     */
    private static function imageChoice(Server $server, Egg $egg, User $user, int $required): array
    {
        $images = is_array($egg->docker_images) ? $egg->docker_images : [];
        $javaOf = function (string $image, string $label = ''): ?int {
            return preg_match('/java[_:\- ]?(\d{1,2})/i', $image, $match) || preg_match('/java[_:\- ]?(\d{1,2})/i', $label, $match) ? (int) $match[1] : null;
        };
        $values = [];
        $best = null;
        foreach ($images as $label => $image) {
            if (!is_string($image) || $image === '') {
                continue;
            }
            $values[] = $image;
            $java = $javaOf($image, is_string($label) ? $label : '');
            if ($java === null || $java < $required) {
                continue;
            }
            if ($best === null || $java < $best['java']) {
                $best = ['java' => $java, 'image' => $image, 'label' => is_string($label) ? $label : $image];
            }
        }
        $empty = ['ok' => false, 'error' => 'software_image_refused', 'params' => ['java' => $required], 'image' => null, 'keep' => false, 'label' => '', 'from_label' => (string) $server->image];
        if ($values === []) {
            return $empty;
        }
        $default = (string) $values[0];
        $desired = $best['image'] ?? null;
        $currentLabel = array_search($server->image, $images, true);
        $fromLabel = is_string($currentLabel) ? $currentLabel : (string) $server->image;
        if ($desired !== null && (string) $server->image === $desired && in_array((string) $server->image, $values, true)) {
            return ['ok' => true, 'error' => '', 'params' => [], 'image' => null, 'keep' => true, 'label' => $best['label'], 'from_label' => $fromLabel];
        }
        if ($desired === null) {
            return ['ok' => false, 'error' => 'software_image_refused', 'params' => ['java' => $required], 'image' => null, 'keep' => false, 'label' => '', 'from_label' => $fromLabel];
        }
        if ($user->can('startup.docker-image', $server)) {
            return ['ok' => true, 'error' => '', 'params' => [], 'image' => $desired, 'keep' => false, 'label' => $best['label'], 'from_label' => $fromLabel];
        }
        if ($desired === $default && in_array($default, $values, true)) {
            $keep = (string) $server->image === $default;

            return ['ok' => true, 'error' => '', 'params' => [], 'image' => $keep ? null : $default, 'keep' => $keep, 'label' => $best['label'], 'from_label' => $fromLabel];
        }

        return ['ok' => false, 'error' => 'software_image_default', 'params' => ['java' => $required], 'image' => null, 'keep' => false, 'label' => '', 'from_label' => $fromLabel];
    }

    /** @return array<int, string> */
    private static function folders(string $from, string $to): array
    {
        $fromPlugin = in_array($from, ServerTypes::PLUGIN_LOADERS, true);
        $fromMod = in_array($from, ServerTypes::MOD_LOADERS, true);
        $toPlugin = in_array($to, ServerTypes::PLUGIN_LOADERS, true);
        $toMod = in_array($to, ServerTypes::MOD_LOADERS, true);
        if ($fromPlugin && $toMod) {
            return ['plugins'];
        }
        if ($fromMod && $toPlugin) {
            return ['mods'];
        }
        if ($fromMod && $toMod && $from !== $to) {
            return ['mods'];
        }

        return [];
    }

    private static function leftBehind(string $from, string $to): ?string
    {
        if ($to !== 'vanilla') {
            return null;
        }
        if (in_array($from, ServerTypes::PLUGIN_LOADERS, true)) {
            return 'plugins';
        }
        if (in_array($from, ServerTypes::MOD_LOADERS, true)) {
            return 'mods';
        }

        return null;
    }

    private static function serverBlock(Server $server): ?string
    {
        if ($server->status === Server::STATUS_SUSPENDED) {
            return 'server_suspended';
        }
        if ($server->status === Server::STATUS_INSTALLING) {
            return 'server_installing';
        }
        if ($server->status === Server::STATUS_RESTORING_BACKUP) {
            return 'server_restore';
        }
        if ($server->transfer !== null) {
            return 'server_transfer';
        }
        try {
            if ($server->node !== null && $server->node->isUnderMaintenance()) {
                return 'server_maintenance';
            }
        } catch (\Throwable $e) {
            return 'software_node';
        }
        if (!$server->canBeReinstalled()) {
            return $server->skip_scripts ? 'server_no_script' : 'server_no_reinstall';
        }

        return null;
    }

    /** @return array{possible: bool, reason: ?string, used: int, limit: int} */
    private static function backupState(Server $server, User $user): array
    {
        $limit = (int) $server->backup_limit;
        $used = $server->backups()->where(fn ($query) => $query->whereNull('completed_at')->orWhere('is_successful', true))->count();
        $running = $server->backups()->whereNull('completed_at')->exists();
        $state = ['possible' => false, 'reason' => null, 'used' => $used, 'limit' => $limit];
        if ($running) {
            $state['reason'] = I18n::t('tools.software_backup_running');
        } elseif ($limit <= 0) {
            $state['reason'] = I18n::t('tools.backup_off');
        } elseif (!$user->can('backup.create', $server)) {
            $state['reason'] = I18n::t('tools.backup_no_right');
        } elseif ($used >= $limit) {
            $state['reason'] = I18n::t('tools.backup_full', ['limit' => $limit]);
        } else {
            $state['possible'] = true;
        }

        return $state;
    }

    private static function confirmed(string $typed, string $word): bool
    {
        $typed = trim($typed);
        if ($typed === '' || strlen($typed) > 32 || $word === '') {
            return false;
        }

        return mb_strtolower($typed) === mb_strtolower($word);
    }

    /** @param array<int, string> $moved */
    private static function remember(array $moved, string $name): array
    {
        if (!in_array($name, $moved, true)) {
            $moved[] = $name;
        }

        return $moved;
    }

    /** @return array<string, array{name: string, file: bool}> */
    private static function listing(Wings $wings): array
    {
        $map = [];
        foreach ($wings->list('/') as $entry) {
            $map[$entry['name']] = $entry;
        }

        return $map;
    }

    /** @param array<string, string|int> $params */
    private static function stop(TaskContext $task, string $key, array $params = []): void
    {
        self::maybeRestart($task);
        $task->result = self::outcome($task, false);
        $task->fail($key, $params);
    }

    private static function maybeRestart(TaskContext $task): void
    {
        $state = $task->payload['state'] ?? [];
        if (!empty($state['moved']) || !empty($state['egg_changed']) || !empty($state['install_triggered'])) {
            return;
        }
        if (empty($state['stop_sent']) || empty($state['was_running']) || $task->server === null) {
            return;
        }
        try {
            if ($task->wings()->state() === 'offline') {
                $task->wings()->power('start');
                $task->payload['state']['restarted'] = true;
            }
        } catch (\Throwable $e) {
            Log::warning('techtyl tools: restart after stopped software switch failed', ['server' => $task->server->uuid, 'error' => substr($e->getMessage(), 0, 200)]);
        }
    }

    /** @return array<string, mixed> */
    private static function outcome(TaskContext $task, bool $ok): array
    {
        $payload = $task->payload;
        $state = is_array($payload['state'] ?? null) ? $payload['state'] : [];

        return [
            'partial' => !$ok,
            'moved' => array_values(is_array($state['moved'] ?? null) ? $state['moved'] : []),
            'jar_backup' => is_string($state['jar_backup'] ?? null) ? $state['jar_backup'] : null,
            'egg_from' => (string) ($payload['egg_from_name'] ?? ''),
            'egg_to' => (string) ($payload['egg_to_name'] ?? ''),
            'egg_changed' => !empty($state['egg_changed']),
            'image_from' => (string) ($payload['image_from'] ?? ''),
            'image_to' => (string) ($payload['image_label'] ?? ''),
            'image_changed' => !empty($state['image_changed']),
            'install_started' => !empty($state['install_triggered']),
            'loader' => (string) ($payload['loader'] ?? ''),
            'game' => (string) ($payload['game'] ?? ''),
            'build' => (string) ($payload['build'] ?? ''),
            'type' => (string) ($payload['loader'] ?? ''),
            'version' => (string) ($payload['game'] ?? ''),
            'jar' => (string) ($payload['jar'] ?? ''),
            'jar_size' => (int) ($state['jar_size'] ?? 0),
            'restarted' => !empty($state['restarted']),
            'from' => $payload['from'] ?? null,
        ];
    }

    private static function activity(TaskContext $task): void
    {
        try {
            $activity = Activity::event('server:techtyl.software-switch')
                ->subject($task->server)
                ->property([
                    'from' => (string) ($task->payload['from']['loader'] ?? ''),
                    'to' => (string) ($task->payload['loader'] ?? ''),
                    'game' => (string) ($task->payload['game'] ?? ''),
                    'build' => (string) ($task->payload['build'] ?? ''),
                ]);
            $actor = User::query()->find((int) ($task->payload['user_id'] ?? 0));
            if ($actor !== null) {
                $activity->actor($actor);
            }
            $activity->log();
        } catch (\Throwable $e) {
            Log::warning('techtyl tools: software switch activity skipped', ['error' => substr($e->getMessage(), 0, 200)]);
        }
    }
}
