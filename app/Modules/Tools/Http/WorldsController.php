<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http;

use Pterodactyl\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Worlds;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests\ReadRequest;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests\RemoveRequest;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests\ArchiveRequest;

/**
 * Detected worlds only. server.properties is read for the level folder solely when the
 * user may read file contents. Otherwise every world asks for the extra delete phrase.
 */
class WorldsController extends Controller
{
    public function show(ReadRequest $request, Server $server): JsonResponse
    {
        $readLevel = $this->readLevel($request, $server);
        $data = Guard::run($server, fn () => Worlds::show($server, $readLevel));

        return $this->respond('techtyl_tools_worlds', $data);
    }

    public function backup(ArchiveRequest $request, Server $server): JsonResponse
    {
        $input = $request->validate([
            'world' => ['required', 'string', 'max:64'],
            'confirm_running' => ['sometimes', 'boolean'],
        ], [
            'world.required' => I18n::t('tools.world_unknown'),
            'world.max' => I18n::t('tools.world_unknown'),
        ]);
        $readLevel = $this->readLevel($request, $server);
        $confirm = $request->boolean('confirm_running');
        $folder = $input['world'];
        $data = Guard::run($server, fn () => Worlds::backup($server, $folder, $confirm, $readLevel));

        return $this->respond('techtyl_tools_world_backup', $data);
    }

    public function remove(RemoveRequest $request, Server $server): JsonResponse
    {
        $input = $request->validate([
            'world' => ['required', 'string', 'max:64'],
            'confirm' => ['required', 'string', 'max:64'],
            'confirm_level' => ['nullable', 'string', 'max:32'],
        ], [
            'world.required' => I18n::t('tools.world_unknown'),
            'world.max' => I18n::t('tools.world_unknown'),
            'confirm.required' => I18n::t('tools.world_confirm'),
            'confirm.max' => I18n::t('tools.world_confirm'),
            'confirm_level.max' => I18n::t('tools.world_level_confirm'),
        ]);
        $readLevel = $this->readLevel($request, $server);
        $folder = $input['world'];
        $confirm = $input['confirm'];
        $phrase = is_string($input['confirm_level'] ?? null) ? $input['confirm_level'] : '';
        $data = Guard::run($server, fn () => Worlds::remove($server, $folder, $confirm, $phrase, $readLevel));

        return $this->respond('techtyl_tools_world_delete', $data);
    }

    private function readLevel(Request $request, Server $server): bool
    {
        $user = $request->user();

        return $user !== null && $user->can('file.read-content', $server);
    }

    private function respond(string $object, array $attributes, int $status = 200): JsonResponse
    {
        return new JsonResponse(['object' => $object, 'attributes' => $attributes], $status);
    }
}
