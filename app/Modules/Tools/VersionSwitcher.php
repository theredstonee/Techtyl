<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Facades\Activity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Backups\InitiateBackupService;
use Pterodactyl\Services\Servers\ReinstallServerService;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;

/**
 * Versionswechsel (Vorgang "version.switch").
 *
 * Ablauf: optional Backup -> neue Dateien in Zwischendateien laden (der Server laeuft dabei
 * weiter) -> Groesse und Pruefsumme pruefen -> Server stoppen -> alte Jar als
 * "<name>.techtyl-backup" sichern und neue an ihren Platz setzen -> Egg-Variablen und
 * Java-Image anpassen -> auf Wunsch starten. Bis zum Tausch bleibt der Server unveraendert;
 * scheitert etwas davor, werden nur die Zwischendateien entfernt.
 */
final class VersionSwitcher
{
    public const STEPS = [
        '' => 'step_prepare',
        'backup' => 'step_backup',
        'download' => 'step_switch_download',
        'verify' => 'step_switch_verify',
        'stop' => 'step_stop',
        'swap' => 'step_swap',
        'configure' => 'step_configure',
        'install' => 'step_reinstall',
    ];

    private const NEW_SUFFIX = '.techtyl-new';
    private const BACKUP_SUFFIX = '.techtyl-backup';
    private const FABRIC_PROPERTIES = 'fabric-server-launcher.properties';

    /** Egg-Variablen je Typ: Variable => Quelle (version, build oder ein Schluessel aus extra). */
    private const VARIABLES = [
        'paper' => ['MINECRAFT_VERSION' => 'version', 'BUILD_NUMBER' => 'build'],
        'folia' => ['MINECRAFT_VERSION' => 'version', 'BUILD_NUMBER' => 'build'],
        'purpur' => ['MINECRAFT_VERSION' => 'version', 'BUILD_NUMBER' => 'build'],
        'vanilla' => ['VANILLA_VERSION' => 'version'],
        'velocity' => ['VELOCITY_VERSION' => 'version', 'BUILD_NUMBER' => 'build'],
        'fabric' => ['MC_VERSION' => 'version', 'LOADER_VERSION' => 'build', 'FABRIC_VERSION' => 'installer'],
        // Forge and NeoForge do not download a jar here. The egg install script does, after these variables are set.
        'forge' => ['MC_VERSION' => 'version', 'FORGE_VERSION' => 'forge_version', 'BUILD_TYPE' => 'build_type'],
        'neoforge' => ['NEOFORGE_VERSION' => 'neoforge_version'],
    ];

    /**
     * Zusammenfassung vor dem Wechsel: was sich aendert, was der Nutzer wissen muss und was er
     * waehlen kann. Dieselben Daten sind die Grundlage des Vorgangs (queue()).
     */
    public static function plan(Server $server, User $user, string $version, string $build): array
    {
        $type = ServerTypes::forServer($server)['type'];
        if (!VersionSources::supports($type)) {
            throw ToolsException::conflict('no_switch');
        }
        $reinstall = in_array($type, ['forge', 'neoforge'], true);
        if ($reinstall) {
            $blocked = self::installBlock($server);
            if ($blocked !== null) {
                throw ToolsException::conflict($blocked);
            }
        }
        if (!VersionSources::validId($version) || !VersionSources::validId($build)) {
            throw ToolsException::invalid('ids_invalid');
        }

        $resolved = VersionSources::resolve($type, $version, $build);
        $wings = Wings::for($server);
        ServerMeta::flush($server);
        $current = ServerMeta::detect($server, $type);
        $currentBuild = $type === 'fabric' ? $current['loader_version'] : $current['build'];
        $jar = ServerMeta::jarName($server);
        $state = $wings->state();
        $velocity = $type === 'velocity';

        $compared = VersionSources::compare($current['version'], $version);
        $direction = match (true) {
            $compared === null => 'unknown',
            $compared < 0 => 'upgrade',
            $compared > 0 => 'downgrade',
            default => $type === 'vanilla' || ($currentBuild !== null && $currentBuild === $build) ? 'same' : 'build',
        };

        // Dateien mit ihren Namen auf dem Server.
        $files = [];
        $extra = $resolved['extra'];
        foreach ($resolved['files'] as $file) {
            $final = $jar;
            if ($file['role'] === 'vanilla') {
                $final = self::fabricServerJar($wings, $jar);
                $extra['server_jar'] = $final;
            }
            $files[] = $file + ['final' => $final, 'temp' => $final . self::NEW_SUFFIX, 'backup' => $final . self::BACKUP_SUFFIX];
        }

        $image = self::imagePlan($server, $user, $resolved['java']);
        $variables = self::variablePlan($server, $type, $version, $build, $extra);

        $limit = (int) $server->backup_limit;
        $used = $server->backups()->where(fn ($query) => $query->whereNull('completed_at')->orWhere('is_successful', true))->count();
        $backup = ['possible' => false, 'reason' => null, 'used' => $used, 'limit' => $limit];
        if ($limit <= 0) {
            $backup['reason'] = I18n::t('tools.backup_off');
        } elseif (!$user->can('backup.create', $server)) {
            $backup['reason'] = I18n::t('tools.backup_no_right');
        } elseif ($used >= $limit) {
            $backup['reason'] = I18n::t('tools.backup_full', ['limit' => $limit]);
        } else {
            $backup['possible'] = true;
        }

        $warnings = [];
        if ($direction === 'downgrade' && !$velocity) {
            $warnings[] = ['level' => 'danger', 'text' => I18n::t('tools.warn_downgrade', ['from' => (string) $current['version'], 'to' => $version])];
        }
        if ($direction === 'unknown' && !$velocity) {
            $warnings[] = ['level' => 'warning', 'text' => I18n::t('tools.warn_unknown')];
        }
        if ($direction === 'upgrade' && !$velocity) {
            $warnings[] = ['level' => 'info', 'text' => I18n::t('tools.warn_upgrade')];
        }
        if (isset(ServerTypes::CONTENT[$type]) && $direction !== 'same' && $direction !== 'build') {
            $warnings[] = ['level' => 'info', 'text' => I18n::t(ServerTypes::CONTENT[$type]['kind'] === 'mod' ? 'tools.warn_content_mods' : 'tools.warn_content_plugins')];
        }
        if ($image['needed'] && $image['to'] === null) {
            $warnings[] = ['level' => 'danger', 'text' => I18n::t('tools.warn_java_missing', ['java' => $resolved['java']])];
        } elseif ($image['needed'] && !$image['allowed']) {
            $warnings[] = ['level' => 'warning', 'text' => I18n::t('tools.warn_java_blocked', ['java' => $resolved['java'], 'reason' => (string) $image['reason']])];
        }
        if ($state !== 'offline' && !$user->can('control.stop', $server)) {
            $warnings[] = ['level' => 'danger', 'text' => I18n::t('tools.warn_cant_stop')];
        }
        if ($reinstall) {
            $warnings[] = ['level' => 'warning', 'text' => I18n::t('tools.warn_installer')];
            $byKey = [];
            foreach ($variables as $item) {
                $byKey[$item['key']] = $item;
            }
            foreach (array_keys(self::VARIABLES[$type] ?? []) as $key) {
                $item = $byKey[$key] ?? null;
                if ($item === null) {
                    throw ToolsException::conflict('software_var_missing', ['key' => $key]);
                }
                if (!$item['set']) {
                    throw ToolsException::conflict(($item['code'] ?? '') === 'locked' ? 'software_var_locked' : 'software_var_rules', ['key' => $key]);
                }
            }
        }

        $size = 0;
        foreach ($files as $file) {
            $size += (int) ($file['size'] ?? 0);
        }

        return [
            'type' => $type,
            'from' => ['version' => $current['version'], 'build' => $currentBuild],
            'to' => ['version' => $version, 'build' => $build],
            'direction' => $direction,
            'jar' => $jar,
            'jar_backup' => $jar . self::BACKUP_SUFFIX,
            'download_size' => $size > 0 ? $size : null,
            'java' => ['required' => $resolved['java'], 'current_image' => $image['from_label'], 'new_image' => $image['needed'] && $image['allowed'] ? $image['to_label'] : null],
            'variables' => array_map(fn ($item) => ['key' => $item['key'], 'label' => $item['label'], 'from' => $item['from'], 'to' => $item['to'], 'set' => $item['set'], 'reason' => $item['reason']], $variables),
            'server_state' => $state,
            'can_stop' => $state === 'offline' || $user->can('control.stop', $server),
            'can_start' => $user->can('control.start', $server),
            'reinstall' => $reinstall,
            'backup' => $backup,
            'warnings' => $warnings,
            // Nur intern (queue), geht nie an den Browser:
            'internal' => ['files' => $files, 'extra' => $extra, 'image' => $image, 'variables' => $variables, 'java' => $resolved['java']],
        ];
    }

    /** Startet den Wechsel als Vorgang. Auf einem Server laeuft immer nur ein Wechsel. */
    public static function queue(Server $server, User $user, string $version, string $build, bool $backup, bool $start): object
    {
        if (Tasks::hasActive($server, 'version.switch')) {
            throw ToolsException::conflict('switch_running');
        }
        if (Tasks::hasActive($server, 'software.switch')) {
            throw ToolsException::conflict('software_busy');
        }
        $plan = self::plan($server, $user, $version, $build);
        if ($plan['direction'] === 'same') {
            throw ToolsException::conflict('same_version');
        }
        if (!$plan['can_stop']) {
            throw ToolsException::forbidden('cant_stop');
        }
        if ($backup && !$plan['backup']['possible']) {
            throw ToolsException::conflict('backup_impossible', ['reason' => (string) ($plan['backup']['reason'] ?? '')]);
        }
        if ($start && !$plan['can_start']) {
            throw ToolsException::forbidden('cant_start');
        }

        $internal = $plan['internal'];
        $label = ServerTypes::TYPES[$plan['type']] . ' ' . $version . ($plan['type'] === 'vanilla' ? '' : ' · ' . ($plan['type'] === 'fabric' ? 'Loader ' : 'Build ') . $build);
        $payload = [
            'type' => $plan['type'],
            'version' => $version,
            'build' => $build,
            'from' => $plan['from'],
            'jar' => $plan['jar'],
            'files' => $internal['files'],
            'extra' => $internal['extra'],
            'image' => $internal['image']['needed'] && $internal['image']['allowed'] ? $internal['image']['to'] : null,
            'variables' => array_values(array_filter($internal['variables'], fn ($item) => $item['set'])),
            'options' => ['backup' => $backup, 'start' => $start],
            'user_id' => $user->id,
            'reinstall' => !empty($plan['reinstall']),
            'state' => [],
            'public' => [
                'type' => $plan['type'],
                'from' => $plan['from'],
                'to' => $plan['to'],
                'backup' => $backup,
                'start' => $start,
                'mode' => !empty($plan['reinstall']) ? 'reinstall' : 'files',
            ],
        ];

        return Tasks::create($server, $user, 'version.switch', $label, $payload, function (Collection $active) {
            if ($active->contains('kind', 'software.switch')) {
                throw ToolsException::conflict('software_busy');
            }
            if ($active->contains('kind', 'version.switch')) {
                throw ToolsException::conflict('switch_running');
            }
        });
    }

    public static function advance(TaskContext $task): void
    {
        if (!empty($task->payload['reinstall'])) {
            self::advanceReinstall($task);

            return;
        }
        $payload = &$task->payload;
        $state = &$payload['state'];
        $wings = $task->wings();
        $server = $task->server;

        switch ($task->step) {
            case '':
                if (!empty($payload['options']['backup'])) {
                    try {
                        $backup = app(InitiateBackupService::class)->handle($server, I18n::t('tools.backup_name', ['version' => $payload['version']]));
                    } catch (\Throwable $e) {
                        Log::warning('techtyl tools: Backup vor Versionswechsel nicht moeglich', ['server' => $server->uuid, 'error' => substr($e->getMessage(), 0, 300)]);
                        $task->fail('backup_start_failed');

                        return;
                    }
                    $state['backup_uuid'] = $backup->uuid;
                    $state['backup_started'] = time();
                    $task->step('backup', 3);

                    return;
                }
                self::startDownloads($task, $wings);

                return;

            case 'backup':
                $backup = Backup::query()->where('server_id', $server->id)->where('uuid', $state['backup_uuid'] ?? '')->first();
                if ($backup === null || ($backup->completed_at !== null && !$backup->is_successful)) {
                    $task->fail('backup_failed');

                    return;
                }
                if ($backup->completed_at === null) {
                    if (time() - (int) $state['backup_started'] > 1500) {
                        $task->fail('backup_timeout');
                    }

                    return;
                }
                self::startDownloads($task, $wings);

                return;

            case 'download':
                if (empty($state['downloads_started'])) {
                    self::startDownloads($task, $wings);

                    return;
                }
                $running = $wings->downloads();
                $sum = 0;
                $count = 0;
                $busy = false;
                foreach ($payload['files'] as $file) {
                    if ($file['via'] !== 'pull') {
                        continue;
                    }
                    $count++;
                    if (isset($running[$file['download_id'] ?? ''])) {
                        $busy = true;
                        $sum += $running[$file['download_id']];
                    } else {
                        $sum += 100;
                    }
                }
                if ($busy) {
                    if (time() - (int) $state['downloads_started'] > 1500) {
                        self::abort($task);
                        $task->fail('switch_dl_timeout');

                        return;
                    }
                    $task->step('download', 10 + (int) round(($sum / max(1, $count)) * 0.5));

                    return;
                }
                $task->step('verify', 62);

                return;

            case 'verify':
                $listing = [];
                foreach ($wings->list('/') as $entry) {
                    $listing[$entry['name']] = $entry;
                }
                foreach ($payload['files'] as $index => $file) {
                    if (!empty($file['verified'])) {
                        continue;
                    }
                    $found = $listing[$file['temp']] ?? null;
                    $problem = null;
                    if ($found === null || !$found['file'] || $found['size'] < 1024) {
                        $problem = 'switch_dl_failed';
                    } elseif ($file['size'] !== null && $found['size'] !== (int) $file['size']) {
                        $problem = 'switch_incomplete';
                    } elseif ($file['checksum'] !== null) {
                        $hash = $wings->hash('/' . $file['temp'], $file['checksum'][0]);
                        if ($hash === null || !hash_equals($file['checksum'][1], $hash)) {
                            $problem = 'switch_checksum';
                        }
                    }
                    if ($problem !== null) {
                        self::abort($task);
                        $task->fail($problem);

                        return;
                    }
                    $payload['files'][$index]['verified'] = true;
                    $payload['files'][$index]['actual_size'] = $found['size'];
                    $task->step('verify', 66);

                    return; // eine Datei je Schritt, damit kein Schritt lange dauert
                }
                $state['was_running'] = $wings->state() !== 'offline';
                $task->step('stop', 72);

                return;

            case 'stop':
                if ($wings->state() === 'offline') {
                    $task->step('swap', 82);

                    return;
                }
                if (empty($state['stop_sent'])) {
                    $wings->power('stop');
                    $state['stop_sent'] = time();
                } elseif (time() - (int) $state['stop_sent'] > 150) {
                    self::abort($task);
                    $task->fail('stop_failed');
                } elseif (time() - (int) $state['stop_sent'] > 75 && empty($state['kill_sent'])) {
                    $wings->power('kill');
                    $state['kill_sent'] = time();
                }

                return;

            case 'swap':
                $state['swap_started'] = true;
                foreach ($payload['files'] as $file) {
                    $names = array_column($wings->list('/'), 'name');
                    if (!in_array($file['temp'], $names, true)) {
                        continue; // schon getauscht (Schritt wurde wiederholt)
                    }
                    if (in_array($file['final'], $names, true)) {
                        if (in_array($file['backup'], $names, true)) {
                            $wings->delete('/', [$file['backup']]);
                        }
                        $wings->rename('/', $file['final'], $file['backup']);
                    }
                    $wings->rename('/', $file['temp'], $file['final']);
                }
                if ($payload['type'] === 'fabric' && isset($payload['extra']['server_jar'])) {
                    $wings->put('/' . self::FABRIC_PROPERTIES, 'serverJar=' . $payload['extra']['server_jar'] . "\n");
                }
                $task->step('configure', 92);

                return;

            case 'configure':
                $changed = self::applySettings($task);
                ServerMeta::flush($server);
                $started = false;
                if (!empty($payload['options']['start'])) {
                    try {
                        $wings->power('start');
                        $started = true;
                    } catch (\Throwable $e) {
                        Log::warning('techtyl tools: Start nach Versionswechsel fehlgeschlagen', ['server' => $server->uuid]);
                    }
                }
                $size = $wings->stat('/', $payload['jar'])['size'] ?? 0;
                self::log($task);
                $task->done([
                    'type' => $payload['type'],
                    'version' => $payload['version'],
                    'build' => $payload['build'],
                    'from' => $payload['from'],
                    'jar' => $payload['jar'],
                    'jar_size' => $size,
                    'jar_backup' => $payload['jar'] . self::BACKUP_SUFFIX,
                    'image_changed' => $changed['image'],
                    'variables_set' => $changed['variables'],
                    'started' => $started,
                    'was_running' => !empty($state['was_running']),
                ]);

                return;
        }

        self::abort($task);
        $task->fail('task_state');
    }

    /**
     * Aufraeumen nach einem Abbruch: Downloads stoppen, Zwischendateien entfernen. Wurde schon
     * getauscht, kommt die gesicherte Jar zurueck. Lief der Server vorher, startet er wieder.
     */
    public static function abort(TaskContext $task): void
    {
        if (!empty($task->payload['reinstall'])) {
            self::abortReinstall($task);

            return;
        }
        if ($task->server === null) {
            return;
        }
        $payload = $task->payload;
        $state = $payload['state'] ?? [];
        $wings = $task->wings();

        foreach ($payload['files'] ?? [] as $file) {
            if (!empty($file['download_id'])) {
                $wings->cancelDownload((string) $file['download_id']);
            }
        }
        $names = array_column($wings->list('/'), 'name');
        $temps = [];
        foreach ($payload['files'] ?? [] as $file) {
            if (in_array($file['temp'], $names, true)) {
                $temps[] = $file['temp'];
            }
            if (!empty($state['swap_started']) && !in_array($file['final'], $names, true) && in_array($file['backup'], $names, true)) {
                $wings->rename('/', $file['backup'], $file['final']);
            }
        }
        $wings->delete('/', $temps);
        ServerMeta::flush($task->server);

        if (!empty($state['stop_sent']) && !empty($state['was_running']) && $wings->state() === 'offline') {
            $wings->power('start');
        }
    }

    private static function startDownloads(TaskContext $task, Wings $wings): void
    {
        $payload = &$task->payload;
        $pulls = count(array_filter($payload['files'], fn ($file) => $file['via'] === 'pull'));
        if (count($wings->downloads()) + $pulls > Wings::MAX_PULLS) {
            // Wings erlaubt 3 Downloads je Server: warten, bis genug Plaetze frei sind.
            $task->step('download', 5);

            return;
        }

        // Reste eines frueheren, abgebrochenen Versuchs entfernen.
        $names = array_column($wings->list('/'), 'name');
        $stale = array_values(array_filter(array_column($payload['files'], 'temp'), fn ($name) => in_array($name, $names, true)));
        $wings->delete('/', $stale);

        foreach ($payload['files'] as $index => $file) {
            if ($file['via'] === 'panel') {
                // Kleine Datei ohne Content-Length (Fabric-Starter): das Panel laedt und schreibt sie.
                $bytes = ExternalHttp::bytes($file['url'], 8 * 1024 * 1024);
                if (strlen($bytes) < 1024 || !str_starts_with($bytes, "PK\x03\x04")) {
                    throw ToolsException::upstream('meta.fabricmc.net');
                }
                $wings->put('/' . $file['temp'], $bytes);
                $payload['files'][$index]['size'] = strlen($bytes);
                $payload['files'][$index]['checksum'] = ['sha256', hash('sha256', $bytes)];
            } else {
                $payload['files'][$index]['download_id'] = $wings->pull($file['url'], '/', $file['temp']);
            }
        }
        $payload['state']['downloads_started'] = time();
        $task->step('download', 10);
    }

    /** @return array{image: bool, variables: string[]} */
    private static function applySettings(TaskContext $task): array
    {
        $server = $task->server;
        $payload = $task->payload;
        $changed = ['image' => false, 'variables' => []];

        foreach ($payload['variables'] as $item) {
            $variable = $server->variables->firstWhere('env_variable', $item['key']);
            if ($variable === null || !$variable->user_editable) {
                continue;
            }
            DB::table('server_variables')->updateOrInsert(
                ['server_id' => $server->id, 'variable_id' => $variable->id],
                ['variable_value' => (string) $item['to'], 'updated_at' => now()]
            );
            $changed['variables'][] = $item['key'];
        }

        // Nur Images, die das Egg selbst anbietet, und nur, wenn das aktuelle auch eines davon ist.
        $images = array_values(is_array($server->egg->docker_images) ? $server->egg->docker_images : []);
        if (is_string($payload['image']) && in_array($payload['image'], $images, true) && in_array($server->image, $images, true)
            && $server->image !== $payload['image']) {
            $server->forceFill(['image' => $payload['image']])->saveOrFail();
            $changed['image'] = true;
        }

        return $changed;
    }

    /**
     * Welches Java-Image passt? Bei alten Versionen (Java 16 und aelter) genau das benoetigte,
     * sonst bleibt ein Image, das neu genug ist; andernfalls das kleinste, das reicht.
     */
    private static function imagePlan(Server $server, User $user, int $required): array
    {
        $images = is_array($server->egg->docker_images) ? $server->egg->docker_images : [];
        $javaOf = function (string $image, string $label = ''): ?int {
            return preg_match('/java[_:\- ]?(\d{1,2})/i', $image, $m) || preg_match('/java[_:\- ]?(\d{1,2})/i', $label, $m) ? (int) $m[1] : null;
        };
        $currentLabel = array_search($server->image, $images, true);
        $current = $javaOf((string) $server->image, is_string($currentLabel) ? $currentLabel : '');

        $best = null;
        foreach ($images as $label => $image) {
            $java = $javaOf((string) $image, (string) $label);
            if ($java === null || $java < $required) {
                continue;
            }
            if ($best === null || $java < $best['java']) {
                $best = ['java' => $java, 'image' => (string) $image, 'label' => (string) $label];
            }
        }

        $needed = $required <= 16
            ? ($current === null || $current !== ($best['java'] ?? $required))
            : ($current === null || $current < $required);
        if ($best !== null && $best['image'] === $server->image) {
            $needed = false;
        }

        $allowed = true;
        $reason = null;
        if (!in_array($server->image, array_values($images), true)) {
            $allowed = false;
            $reason = I18n::t('tools.image_fixed');
        } elseif (!$user->can('startup.docker-image', $server)) {
            $allowed = false;
            $reason = I18n::t('tools.image_no_right');
        }

        return [
            'needed' => $needed,
            'allowed' => $allowed && $best !== null,
            'reason' => $reason,
            'to' => $best['image'] ?? null,
            'to_label' => $best['label'] ?? null,
            'from_label' => is_string($currentLabel) ? $currentLabel : (string) $server->image,
        ];
    }

    /** @return array<int, array{key: string, label: string, from: ?string, to: string, set: bool, code: ?string, reason: ?string}> */
    private static function variablePlan(Server $server, string $type, string $version, string $build, array $extra): array
    {
        $values = ['version' => $version, 'build' => $build] + $extra;
        $plan = [];
        foreach (self::VARIABLES[$type] ?? [] as $key => $source) {
            $variable = $server->variables->firstWhere('env_variable', $key);
            $to = $values[$source] ?? null;
            if ($variable === null || !is_string($to)) {
                continue;
            }
            $set = true;
            $reason = null;
            $code = null;
            if (!$variable->user_editable) {
                $set = false;
                $code = 'locked';
                $reason = I18n::t('tools.var_locked');
            } elseif (Validator::make(['value' => $to], ['value' => $variable->rules])->fails()) {
                $set = false;
                $code = 'rules';
                $reason = I18n::t('tools.var_rules');
            }
            $plan[] = [
                'key' => $key,
                'label' => (string) $variable->name,
                'from' => is_string($variable->server_value) ? $variable->server_value : null,
                'to' => $to,
                'set' => $set,
                'code' => $code,
                'reason' => $reason,
            ];
        }

        return $plan;
    }

    /** Name der Vanilla-Jar eines Fabric-Servers laut fabric-server-launcher.properties. */
    private static function fabricServerJar(Wings $wings, string $jar): string
    {
        $content = $wings->read('/' . self::FABRIC_PROPERTIES, 4096);
        if (is_string($content) && preg_match('/^\s*serverJar\s*=\s*([\w.-]{1,60}\.jar)\s*$/m', $content, $m) && $m[1] !== $jar) {
            return $m[1];
        }

        return $jar === 'minecraft-server.jar' ? 'vanilla-server.jar' : 'minecraft-server.jar';
    }

    /** Reasons that make a Forge or NeoForge reinstall unsafe before anything is touched. */
    private static function installBlock(Server $server): ?string
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

    /**
     * Same-egg Forge or NeoForge install. Backup, then stop, then set the egg variables and
     * run the panel reinstall. No jar is renamed. If the installer never starts, the variables
     * are put back so a later attempt is not refused as "already this version".
     */
    private static function advanceReinstall(TaskContext $task): void
    {
        $payload = &$task->payload;
        $state = &$payload['state'];
        $wings = $task->wings();
        $server = $task->server;

        switch ($task->step) {
            case '':
                if (!empty($payload['options']['backup'])) {
                    try {
                        $backup = app(InitiateBackupService::class)->handle($server, I18n::t('tools.backup_name', ['version' => $payload['version']]));
                    } catch (\Throwable $e) {
                        Log::warning('techtyl tools: backup before reinstall failed', ['server' => $server->uuid, 'error' => substr($e->getMessage(), 0, 300)]);
                        $task->fail('backup_start_failed');

                        return;
                    }
                    $state['backup_uuid'] = $backup->uuid;
                    $state['backup_started'] = time();
                    $task->step('backup', 8);

                    return;
                }
                $state['was_running'] = $wings->state() !== 'offline';
                $task->step('stop', 20);

                return;

            case 'backup':
                $backup = Backup::query()->where('server_id', $server->id)->where('uuid', $state['backup_uuid'] ?? '')->first();
                if ($backup === null || ($backup->completed_at !== null && !$backup->is_successful)) {
                    $task->fail('backup_failed');

                    return;
                }
                if ($backup->completed_at === null) {
                    if (time() - (int) ($state['backup_started'] ?? time()) > 1500) {
                        $task->fail('backup_timeout');
                    }

                    return;
                }
                $state['was_running'] = $wings->state() !== 'offline';
                $task->step('stop', 20);

                return;

            case 'stop':
                if ($wings->state() === 'offline') {
                    $task->step('configure', 55);

                    return;
                }
                if (empty($state['stop_sent'])) {
                    $wings->power('stop');
                    $state['stop_sent'] = time();
                } elseif (time() - (int) $state['stop_sent'] > 150) {
                    self::abortReinstall($task);
                    $task->fail('stop_failed');
                } elseif (time() - (int) $state['stop_sent'] > 75 && empty($state['kill_sent'])) {
                    $wings->power('kill');
                    $state['kill_sent'] = time();
                }

                return;

            case 'configure':
                if (empty($state['settings_changed'])) {
                    $state['snapshot'] = self::snapshot($server, $payload['variables'] ?? []);
                    $changed = ['image' => false];
                    DB::transaction(function () use ($task, &$changed) {
                        $changed = self::applySettings($task);
                    });
                    $state['settings_changed'] = true;
                    $state['image_changed'] = !empty($changed['image']);
                }
                if (empty($state['install_triggered'])) {
                    try {
                        app(ReinstallServerService::class)->handle($server->fresh());
                    } catch (DisplayException $e) {
                        Log::warning('techtyl tools: reinstall refused', ['server' => $server->uuid, 'error' => substr($e->getMessage(), 0, 300)]);
                        if (self::restoreSnapshot($task)) {
                            $state['settings_changed'] = false;
                            $task->fail('reinstall_failed');
                        } else {
                            $task->fail('reinstall_failed_keep');
                        }

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
                    $task->fail('task_stuck');

                    return;
                }
                $status = $fresh->status;
                if ($status === null || $status === '') {
                    $started = false;
                    if (!empty($payload['options']['start'])) {
                        try {
                            $wings->power('start');
                            $started = true;
                        } catch (\Throwable $e) {
                            Log::warning('techtyl tools: start after reinstall failed', ['server' => $server->uuid]);
                        }
                    }
                    self::log($task);
                    $task->done([
                        'type' => $payload['type'],
                        'version' => $payload['version'],
                        'build' => $payload['build'],
                        'from' => $payload['from'],
                        'jar' => $payload['jar'],
                        'jar_size' => 0,
                        'jar_backup' => null,
                        'mode' => 'reinstall',
                        'image_changed' => !empty($state['image_changed']),
                        'variables_set' => array_column($payload['variables'] ?? [], 'key'),
                        'started' => $started,
                        'was_running' => !empty($state['was_running']),
                    ]);

                    return;
                }
                if ($status === Server::STATUS_INSTALL_FAILED || $status === Server::STATUS_REINSTALL_FAILED) {
                    $task->fail('reinstall_status_failed');

                    return;
                }
                $elapsed = time() - (int) ($state['install_started'] ?? time());
                if ($elapsed > 1500) {
                    $task->fail('reinstall_timeout');

                    return;
                }
                $task->step('install', min(92, 75 + (int) floor($elapsed / 90)));

                return;
        }

        $task->fail('task_state');
    }

    /** Put the previous variable values back. Used only when the installer never started. */
    private static function restoreSnapshot(TaskContext $task): bool
    {
        $snapshot = $task->payload['state']['snapshot'] ?? null;
        $server = $task->server;
        if (!is_array($snapshot) || $server === null) {
            return false;
        }
        try {
            DB::transaction(function () use ($server, $snapshot) {
                foreach ($snapshot['variables'] ?? [] as $item) {
                    if (!is_array($item) || !is_string($item['key'] ?? null)) {
                        continue;
                    }
                    $variable = $server->variables->firstWhere('env_variable', $item['key']);
                    if ($variable === null || !$variable->user_editable) {
                        continue;
                    }
                    DB::table('server_variables')->updateOrInsert(
                        ['server_id' => $server->id, 'variable_id' => $variable->id],
                        ['variable_value' => (string) ($item['from'] ?? ''), 'updated_at' => now()]
                    );
                }
                $image = $snapshot['image'] ?? null;
                if (is_string($image) && $image !== '' && $image !== $server->image) {
                    $images = array_values(is_array($server->egg->docker_images) ? $server->egg->docker_images : []);
                    if (in_array($image, $images, true)) {
                        $server->forceFill(['image' => $image])->saveOrFail();
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::warning('techtyl tools: could not restore startup settings', ['server' => $server->uuid, 'error' => substr($e->getMessage(), 0, 300)]);

            return false;
        }

        return true;
    }

    /** @param array<int, array<string, mixed>> $variables */
    private static function snapshot(Server $server, array $variables): array
    {
        $rows = [];
        foreach ($variables as $item) {
            if (!is_string($item['key'] ?? null)) {
                continue;
            }
            $variable = $server->variables->firstWhere('env_variable', $item['key']);
            $rows[] = [
                'key' => $item['key'],
                'from' => $variable !== null && is_string($variable->server_value) ? $variable->server_value : '',
            ];
        }

        return ['image' => (string) $server->image, 'variables' => $rows];
    }

    private static function abortReinstall(TaskContext $task): void
    {
        if ($task->server === null) {
            return;
        }
        $state = $task->payload['state'] ?? [];
        if (!empty($state['settings_changed']) && empty($state['install_triggered'])) {
            if (self::restoreSnapshot($task)) {
                $task->payload['state']['settings_changed'] = false;
            }
        }
        if (empty($state['moved']) && empty($task->payload['state']['settings_changed']) && empty($state['install_triggered'])
            && !empty($state['stop_sent']) && !empty($state['was_running'])) {
            try {
                if ($task->wings()->state() === 'offline') {
                    $task->wings()->power('start');
                }
            } catch (\Throwable $e) {
                Log::warning('techtyl tools: restart after reinstall abort failed', ['server' => $task->server->uuid]);
            }
        }
    }

    private static function log(TaskContext $task): void
    {
        try {
            $activity = Activity::event('server:techtyl.version-switch')
                ->subject($task->server)
                ->property([
                    'type' => $task->payload['type'],
                    'old' => trim(($task->payload['from']['version'] ?? '?') . ' ' . ($task->payload['from']['build'] ?? '')),
                    'new' => trim($task->payload['version'] . ' ' . $task->payload['build']),
                ]);
            $actor = User::query()->find((int) ($task->payload['user_id'] ?? 0));
            if ($actor !== null) {
                $activity->actor($actor);
            }
            $activity->log();
        } catch (\Throwable $e) {
            // Das Protokoll darf den Vorgang nie scheitern lassen.
        }
    }
}
