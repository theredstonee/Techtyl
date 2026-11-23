<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Taktet einen Vorgang im Queue-Worker. Ein Lauf dauert hoechstens rund 25 Sekunden und reiht
 * sich danach selbst neu ein; so bleibt er weit unter den Zeitgrenzen des Workers, egal wie
 * lange der Vorgang insgesamt braucht.
 */
class RunTask implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;
    public int $timeout = 55;

    public function __construct(public int $taskId, public string $runner)
    {
    }

    public function handle(): void
    {
        $deadline = microtime(true) + 25;
        while (true) {
            $row = DB::table(Tasks::TABLE)->where('id', $this->taskId)->first(['runner', 'status']);
            // Eine neuere Kette hat uebernommen oder der Vorgang ist beendet.
            if ($row === null || $row->runner !== $this->runner || !in_array($row->status, Tasks::ACTIVE, true)) {
                return;
            }
            if (!Tasks::tick($this->taskId)) {
                return;
            }
            if (microtime(true) > $deadline) {
                break;
            }
            usleep(1200000);
        }

        self::dispatch($this->taskId, $this->runner)->delay(1);
    }
}
