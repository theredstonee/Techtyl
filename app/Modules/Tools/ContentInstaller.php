<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Illuminate\Support\Facades\DB;
use Pterodactyl\Facades\Activity;

/**
 * Vorgang "content.install": laedt eine Plugin-/Mod-Datei in eine Zwischendatei, prueft Groesse
 * und sha1 und setzt sie erst dann an ihren Platz. Eine ersetzte Datei (Update) wird erst nach
 * erfolgreicher Pruefung entfernt. Wartet, solange Wings schon 3 Downloads fuer den Server hat.
 */
final class ContentInstaller
{
    public const STEPS = [
        '' => 'step_waiting',
        'queued' => 'step_queued',
        'download' => 'step_download',
        'verify' => 'step_verify',
    ];

    private const HASH_LIMIT = 96 * 1024 * 1024;
    private const DOWNLOAD_SECONDS = 900;

    public static function advance(TaskContext $task): void
    {
        $payload = &$task->payload;
        $wings = $task->wings();
        $directory = '/' . $payload['directory'];

        if (empty($payload['download_id'])) {
            if (count($wings->downloads()) >= Wings::MAX_PULLS) {
                $task->step = 'queued';

                return;
            }
            $wings->ensureDirectory($directory);
            $payload['download_id'] = $wings->pull($payload['url'], $directory, $payload['temp']);
            $payload['started_at'] = time();
            $task->step('download', 2);

            return;
        }

        if ($task->step === 'download') {
            $downloads = $wings->downloads();
            if (isset($downloads[$payload['download_id']])) {
                if (time() - (int) $payload['started_at'] > self::DOWNLOAD_SECONDS) {
                    self::abort($task);
                    $task->fail('dl_timeout');

                    return;
                }
                $task->step('download', 2 + (int) round($downloads[$payload['download_id']] * 0.9));

                return;
            }

            $file = $wings->stat($directory, $payload['temp']);
            if ($file === null || $file['size'] !== (int) $payload['size']) {
                self::abort($task);
                $task->fail('dl_failed');

                return;
            }
            $task->step('verify', 94);

            return;
        }

        if ($task->step === 'verify') {
            if ((int) $payload['size'] <= self::HASH_LIMIT) {
                $hash = $wings->hash($directory . '/' . $payload['temp'], 'sha1', self::HASH_LIMIT);
                if ($hash === null || !hash_equals($payload['sha1'], $hash)) {
                    self::abort($task);
                    $task->fail('checksum');

                    return;
                }
            }

            // Erst jetzt weicht die alte Datei (Update) bzw. eine gleichnamige eigene Datei.
            $names = array_column($wings->list($directory), 'name');
            $remove = array_values(array_unique(array_filter(
                [$payload['replaces'] ?? null, $payload['filename']],
                fn ($name) => is_string($name) && in_array($name, $names, true)
            )));
            $wings->delete($directory, $remove);
            $wings->rename($directory, $payload['temp'], $payload['filename']);

            DB::transaction(function () use ($task, $payload) {
                DB::table(Content::TABLE)->where('server_id', $task->server->id)->where('directory', $payload['directory'])
                    ->where(fn ($query) => $query->where('project_id', $payload['project_id'])->orWhere('filename', $payload['filename']))
                    ->delete();
                DB::table(Content::TABLE)->insert([
                    'server_id' => $task->server->id,
                    'kind' => $payload['kind'],
                    'directory' => $payload['directory'],
                    'project_id' => $payload['project_id'],
                    'title' => mb_substr((string) $payload['title'], 0, 160),
                    'icon_url' => $payload['icon_url'],
                    'version_id' => $payload['version_id'],
                    'version_number' => mb_substr((string) $payload['version_number'], 0, 80),
                    'filename' => $payload['filename'],
                    'sha1' => $payload['sha1'],
                    'size' => (int) $payload['size'],
                    'installed_by' => DB::table('users')->where('id', (int) ($payload['user_id'] ?? 0))->value('id'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            self::log($task, !empty($payload['replaces']));
            $state = 'offline';
            try {
                $state = $wings->state();
            } catch (\Throwable $e) {
                // Zustand unbekannt: dann lieber keinen Neustart-Hinweis erzwingen.
            }
            $task->done([
                'filename' => $payload['filename'],
                'version_number' => $payload['version_number'],
                'updated' => !empty($payload['replaces']),
                'restart_required' => $state !== 'offline',
            ]);

            return;
        }

        // Unbekannter Schritt (sollte nie vorkommen).
        self::abort($task);
        $task->fail('task_state');
    }

    /** Laufenden Download stoppen und die Zwischendatei wegraeumen. */
    public static function abort(TaskContext $task): void
    {
        $payload = $task->payload;
        if ($task->server === null || empty($payload['download_id'])) {
            return;
        }
        $wings = $task->wings();
        $wings->cancelDownload((string) $payload['download_id']);
        $directory = '/' . $payload['directory'];
        if ($wings->stat($directory, $payload['temp']) !== null) {
            $wings->delete($directory, [$payload['temp']]);
        }
    }

    private static function log(TaskContext $task, bool $update): void
    {
        try {
            $activity = Activity::event($update ? 'server:techtyl.content-update' : 'server:techtyl.content-install')
                ->subject($task->server)
                ->property(['file' => $task->payload['filename'], 'project' => $task->payload['title'], 'version' => $task->payload['version_number']]);
            $actor = \Pterodactyl\Models\User::query()->find((int) ($task->payload['user_id'] ?? 0));
            if ($actor !== null) {
                $activity->actor($actor);
            }
            $activity->log();
        } catch (\Throwable $e) {
            // Das Protokoll darf den Vorgang nie scheitern lassen.
        }
    }
}
