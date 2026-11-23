<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Logs;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Wings;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests\ReadRequest;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests\ContentReadRequest;

/** Tail of the latest log, crash reports, and a masked public share. */
class LogsController extends Controller
{
    public function show(ContentReadRequest $request, Server $server): JsonResponse
    {
        $data = Guard::run($server, fn () => Logs::show(Wings::for($server)));

        return $this->respond('techtyl_tools_log', $data);
    }

    public function crashes(ReadRequest $request, Server $server): JsonResponse
    {
        $data = Guard::run($server, fn () => Logs::crashes(Wings::for($server)));

        return $this->respond('techtyl_tools_crashes', $data);
    }

    public function crash(ContentReadRequest $request, Server $server, string $file): JsonResponse
    {
        $data = Guard::run($server, fn () => Logs::crash(Wings::for($server), $file));

        return $this->respond('techtyl_tools_crash', $data);
    }

    public function share(ContentReadRequest $request, Server $server): JsonResponse
    {
        $input = $request->validate([
            'content' => ['required', 'string', 'max:' . Logs::SHARE_BYTES],
        ], [
            'content.required' => I18n::t('tools.log_empty'),
            'content.string' => I18n::t('tools.log_empty'),
            'content.max' => I18n::t('tools.log_too_long'),
        ]);
        $content = $input['content'];
        $data = Guard::run($server, fn () => Logs::share($request->user(), $content));

        return $this->respond('techtyl_tools_share', $data);
    }

    private function respond(string $object, array $attributes, int $status = 200): JsonResponse
    {
        return new JsonResponse(['object' => $object, 'attributes' => $attributes], $status);
    }
}
