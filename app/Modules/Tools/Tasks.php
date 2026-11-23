<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;

/**
 * Lange Vorgaenge der Server-Werkzeuge (Tabelle techtyl_tools_tasks).
 *
 * Ein Vorgang ist ein kleiner Zustandsautomat: tick() ruft den Handler seiner Art auf, der
 * genau einen Schritt weitergeht und NIE wartet (kein sleep, kein Warten auf Wings). Getaktet
 * wird vom Queue-Job RunTask; faellt der Worker aus, taktet die Abfrage des Vorgangs weiter
 * (nudge()). Eine Sperre je Vorgang verhindert doppelte Schritte.
 *
 * Neue Art ergaenzen: Klasse mit
 *   public static function advance(TaskContext $task): void   // ein Schritt
 *   public static function abort(TaskContext $task): void     // aufraeumen nach Abbruch
 *   public const STEPS = ['schritt' => 'Anzeigetext', ...]
 * schreiben und unten in HANDLERS eintragen.
 */
final class Tasks
{
    public const TABLE = 'techtyl_tools_tasks';

    public const PENDING = 'pending';
    public const RUNNING = 'running';
    public const DONE = 'done';
    public const FAILED = 'failed';
    public const ACTIVE = [self::PENDING, self::RUNNING];

    /** Art => Handler-Klasse */
    public const HANDLERS = [
        'content.install' => ContentInstaller::class,
        'version.switch' => VersionSwitcher::class,
    ];

    private const MAX_ACTIVE_PER_SERVER = 25;
    private const MAX_ATTEMPTS = 12;
    private const MAX_MINUTES = 45;
    private const LOCK_SECONDS = 45;
    private const STALE_SECONDS = 10;

    /**
     * Legt einen Vorgang an und startet ihn. $guard bekommt die aktiven Vorgaenge des Servers
     * und wirft eine ToolsException, wenn der neue nicht dazu passt (z. B. kein zweiter
     * Versionswechsel) - geprueft unter einer Sperre, damit zwei gleichzeitige Anfragen nicht
     * beide durchkommen.
     *
     * @param (\Closure(\Illuminate\Support\Collection): void)|null $guard
     */
    public static function create(Server $server, ?User $user, string $kind, string $title, array $payload, ?\Closure $guard = null): object
    {
        if (!isset(self::HANDLERS[$kind])) {
            throw new \InvalidArgumentException('Unbekannte Art von Vorgang.');
        }

        $lock = Cache::lock('techtyl:tools:create:' . $server->id, 10);
        try {
            $lock->block(5);
        } catch (\Throwable $e) {
            throw ToolsException::conflict('server_busy');
        }
        try {
            $active = DB::table(self::TABLE)->where('server_id', $server->id)->whereIn('status', self::ACTIVE);
            if ($guard !== null) {
                $guard((clone $active)->get());
            }
            if ((clone $active)->count() >= self::MAX_ACTIVE_PER_SERVER) {
                throw ToolsException::conflict('too_many_tasks');
            }

            $id = DB::table(self::TABLE)->insertGetId([
                'uuid' => Uuid::uuid4()->toString(),
                'server_id' => $server->id,
                'user_id' => $user?->id,
                'kind' => $kind,
                'status' => self::PENDING,
                'step' => '',
                'progress' => 0,
                'title' => mb_substr($title, 0, 191),
                'payload' => json_encode($payload),
                'runner' => null,
                'attempts' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            $lock->release();
        }

        $row = DB::table(self::TABLE)->where('id', $id)->first();
        self::start($row);
        if (random_int(1, 20) === 1) {
            self::prune();
        }

        return $row;
    }

    public static function find(Server $server, string $uuid): ?object
    {
        if (!Uuid::isValid($uuid)) {
            return null;
        }

        return DB::table(self::TABLE)->where('server_id', $server->id)->where('uuid', $uuid)->first();
    }

    public static function hasActive(Server $server, string $kind): bool
    {
        return DB::table(self::TABLE)->where('server_id', $server->id)->where('kind', $kind)->whereIn('status', self::ACTIVE)->exists();
    }

    /** Aktive Vorgaenge und die zuletzt beendeten eines Servers. */
    public static function overview(Server $server, int $finished = 8): array
    {
        $active = DB::table(self::TABLE)->where('server_id', $server->id)->whereIn('status', self::ACTIVE)->orderBy('id')->get();
        foreach ($active as $row) {
            self::nudge($row);
        }
        $active = DB::table(self::TABLE)->where('server_id', $server->id)->whereIn('status', self::ACTIVE)->orderBy('id')->get();
        $recent = DB::table(self::TABLE)->where('server_id', $server->id)->whereNotIn('status', self::ACTIVE)
            ->where('finished_at', '>', now()->subDay())->orderByDesc('id')->limit($finished)->get();

        return [
            'active' => $active->map(fn ($row) => self::present($row))->all(),
            'recent' => $recent->map(fn ($row) => self::present($row))->all(),
        ];
    }

    /** Fuer die API: nur, was der Browser sehen darf. */
    public static function present(object $row): array
    {
        $handler = self::HANDLERS[$row->kind] ?? null;
        $payload = json_decode((string) $row->payload, true);
        $result = $row->result !== null ? json_decode((string) $row->result, true) : null;

        return [
            'uuid' => $row->uuid,
            'kind' => $row->kind,
            'title' => $row->title,
            'status' => $row->status,
            'step' => $row->step,
            'step_label' => $handler !== null && isset($handler::STEPS[$row->step]) ? I18n::t('tools.' . $handler::STEPS[$row->step]) : '',
            'progress' => (int) $row->progress,
            'error' => self::errorText($row->error),
            'subject' => is_array($payload['public'] ?? null) ? $payload['public'] : [],
            'result' => is_array($result) ? $result : null,
            'created_at' => (string) $row->created_at,
            'finished_at' => $row->finished_at !== null ? (string) $row->finished_at : null,
        ];
    }

    /** Errors are stored as {"k": key, "p": params} and translated for whoever reads them. */
    public static function errorText(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }
        $decoded = json_decode($stored, true);
        if (is_array($decoded) && is_string($decoded['k'] ?? null)) {
            return I18n::t('tools.' . $decoded['k'], is_array($decoded['p'] ?? null) ? $decoded['p'] : []);
        }

        return $stored;
    }

    /** Uebergibt den Vorgang an den Queue-Worker (neue Laeufer-Kennung, alte Ketten enden damit). */
    public static function start(object $row): void
    {
        if (config('queue.default') === 'sync') {
            return; // ohne Worker taktet die Abfrage (nudge)
        }
        $runner = bin2hex(random_bytes(8));
        DB::table(self::TABLE)->where('id', $row->id)->update(['runner' => $runner]);
        try {
            RunTask::dispatch((int) $row->id, $runner);
        } catch (\Throwable $e) {
            Log::warning('techtyl tools: Vorgang konnte nicht in die Queue', ['task' => $row->uuid, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Wird bei jeder Abfrage aufgerufen: hat sich ein aktiver Vorgang laenger nicht gemeldet
     * (Worker neu gestartet oder nicht vorhanden), geht er hier einen Schritt weiter.
     */
    public static function nudge(object $row): void
    {
        if (!in_array($row->status, self::ACTIVE, true)) {
            return;
        }
        $sync = config('queue.default') === 'sync';
        $age = now()->getTimestamp() - strtotime((string) $row->updated_at);
        if ($age < ($sync ? 2 : self::STALE_SECONDS)) {
            return;
        }
        if (!$sync) {
            self::start($row);
        }
        self::tick((int) $row->id);
    }

    /** Ein Schritt. Liefert true, solange der Vorgang danach noch aktiv ist. */
    public static function tick(int $id): bool
    {
        $now = now();
        $claimed = DB::table(self::TABLE)->where('id', $id)->whereIn('status', self::ACTIVE)
            ->where(fn ($query) => $query->whereNull('locked_until')->orWhere('locked_until', '<', $now))
            ->update(['locked_until' => $now->copy()->addSeconds(self::LOCK_SECONDS)]);
        if ($claimed === 0) {
            return DB::table(self::TABLE)->where('id', $id)->whereIn('status', self::ACTIVE)->exists();
        }

        $row = DB::table(self::TABLE)->where('id', $id)->first();
        $handler = self::HANDLERS[$row->kind] ?? null;
        $server = Server::query()->find($row->server_id);
        $payload = json_decode((string) $row->payload, true);
        $task = new TaskContext($row, $server, is_array($payload) ? $payload : []);
        $attempts = (int) $row->attempts;

        if ($handler === null || $server === null) {
            $task->fail('task_stuck');
        } elseif (strtotime((string) $row->created_at) < $now->getTimestamp() - self::MAX_MINUTES * 60) {
            self::abort($handler, $task, 'task_timeout');
        } else {
            try {
                $handler::advance($task);
                $attempts = 0;
            } catch (\Throwable $e) {
                // Voruebergehende Fehler (Node kurz nicht erreichbar) werden einige Male wiederholt.
                $attempts++;
                Log::warning('techtyl tools: Schritt fehlgeschlagen', [
                    'task' => $row->uuid, 'kind' => $row->kind, 'step' => $task->step, 'attempt' => $attempts,
                    'error' => substr($e->getMessage(), 0, 500),
                ]);
                if ($attempts >= self::MAX_ATTEMPTS) {
                    self::abort($handler, $task, $e instanceof ToolsException && $e->key !== null ? $e->key : 'task_failed', $e instanceof ToolsException ? $e->params : []);
                }
            }
        }

        $finished = in_array($task->status, [self::DONE, self::FAILED], true);
        DB::table(self::TABLE)->where('id', $id)->update([
            'status' => $task->status,
            'step' => $task->step,
            'progress' => max(0, min(100, $task->progress)),
            'payload' => json_encode($task->payload),
            'result' => $task->result !== null ? json_encode($task->result) : null,
            'error' => $task->error,
            'attempts' => $attempts,
            'locked_until' => null,
            'finished_at' => $finished ? now() : null,
            'updated_at' => now(),
        ]);

        return !$finished;
    }

    /** Alte, beendete Vorgaenge entfernen (wird beim Anlegen neuer Vorgaenge gelegentlich aufgerufen). */
    public static function prune(): void
    {
        try {
            DB::table(self::TABLE)->whereNotIn('status', self::ACTIVE)->where('finished_at', '<', now()->subDays(30))
                ->whereNot(fn ($query) => $query->where('kind', 'version.switch')->where('status', self::DONE))
                ->delete();
        } catch (\Throwable $e) {
            // Aufraeumen darf nie stoeren.
        }
    }

    private static function abort(string $handler, TaskContext $task, string $key, array $params = []): void
    {
        try {
            $handler::abort($task);
        } catch (\Throwable $e) {
            Log::warning('techtyl tools: Aufraeumen nach Abbruch fehlgeschlagen', ['task' => $task->row->uuid, 'error' => substr($e->getMessage(), 0, 500)]);
        }
        $task->fail($key, $params);
    }
}
