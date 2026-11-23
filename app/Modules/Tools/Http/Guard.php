<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\ToolsException;

/** Runs a tool action. Unexpected failures become a generic error; details stay in the log. */
final class Guard
{
    public static function run(Server $server, \Closure $work): mixed
    {
        try {
            return $work();
        } catch (ToolsException | ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('techtyl tools: unerwarteter Fehler', [
                'server' => $server->uuid,
                'error' => substr($e->getMessage(), 0, 200),
            ]);
            throw ToolsException::failed();
        }
    }
}
