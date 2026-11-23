<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Tasks;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\ServerMeta;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\ToolsException;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests\AccessRequest;

/**
 * Allgemeines zu einem Server: Typ, Version, verfuegbare Werkzeuge, Rechte (GET /meta) und die
 * langen Vorgaenge (GET /tasks). Beides darf jeder sehen, der Zugriff auf den Server hat.
 */
class MetaController extends Controller
{
    public function show(AccessRequest $request, Server $server): JsonResponse
    {
        return new JsonResponse([
            'object' => 'techtyl_tools_meta',
            'attributes' => ServerMeta::describe($server, $request->user()),
        ]);
    }

    public function tasks(AccessRequest $request, Server $server): JsonResponse
    {
        return new JsonResponse(['object' => 'techtyl_tools_tasks', 'attributes' => Tasks::overview($server)]);
    }

    public function task(AccessRequest $request, Server $server, string $task): JsonResponse
    {
        $row = Tasks::find($server, $task);
        if ($row === null) {
            throw ToolsException::notFound('task_missing');
        }
        Tasks::nudge($row);

        return new JsonResponse(['object' => 'techtyl_tools_task', 'attributes' => Tasks::present(Tasks::find($server, $task))]);
    }
}
