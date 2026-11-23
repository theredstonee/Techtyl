<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Pterodactyl\Models\Server;

/**
 * Arbeitskopie eines Vorgangs waehrend eines Schritts. Der Handler aendert Schritt, Fortschritt
 * und payload; Tasks::tick() schreibt alles zurueck. payload['public'] sieht der Browser.
 */
final class TaskContext
{
    public string $status;
    public string $step;
    public int $progress;
    public ?array $result = null;
    public ?string $error = null;

    public function __construct(public object $row, public ?Server $server, public array $payload)
    {
        $this->status = $row->status === Tasks::PENDING ? Tasks::PENDING : Tasks::RUNNING;
        $this->step = (string) $row->step;
        $this->progress = (int) $row->progress;
    }

    /** Wechselt in einen Schritt (und damit von "wartet" zu "laeuft"). */
    public function step(string $step, int $progress): void
    {
        $this->status = Tasks::RUNNING;
        $this->step = $step;
        $this->progress = $progress;
    }

    public function done(array $result): void
    {
        $this->status = Tasks::DONE;
        $this->progress = 100;
        $this->result = $result;
    }

    /**
     * $key is a text from lang/tools.php (without "tools."); it is stored with its placeholders
     * and translated for whoever looks at the task. Say what happened and what the user can do.
     */
    public function fail(string $key, array $params = []): void
    {
        $this->status = Tasks::FAILED;
        $this->error = mb_substr((string) json_encode(['k' => $key, 'p' => $params]), 0, 255);
    }

    public function wings(): Wings
    {
        return Wings::for($this->server);
    }
}
