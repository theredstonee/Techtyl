<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Players;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests\ConsoleRequest;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests\FileUpdateRequest;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests\ContentReadRequest;

/** Whitelist, operators, bans, the server-list ping, and console or file actions. */
class PlayersController extends Controller
{
    public function show(ContentReadRequest $request, Server $server): JsonResponse
    {
        $data = Guard::run($server, fn () => Players::show($server));

        return $this->respond('techtyl_tools_players', $data);
    }

    public function command(ConsoleRequest $request, Server $server): JsonResponse
    {
        $input = $this->input($request, Players::CONSOLE_ACTIONS);
        $data = Guard::run($server, fn () => Players::console($server, $input['action'], $input['player'], $input['reason']));

        return $this->respond('techtyl_tools_player_action', $data);
    }

    public function files(FileUpdateRequest $request, Server $server): JsonResponse
    {
        $input = $this->input($request, Players::FILE_ACTIONS);
        $data = Guard::run($server, fn () => Players::edit($server, $input['action'], $input['player'], $input['reason']));

        return $this->respond('techtyl_tools_player_action', $data);
    }

    /** @param array<int, string> $actions @return array{action: string, player: string, reason: string} */
    private function input(ConsoleRequest | FileUpdateRequest $request, array $actions): array
    {
        $input = $request->validate([
            'action' => ['required', 'string', 'in:' . implode(',', $actions)],
            'player' => ['required', 'string', 'max:17'],
            'reason' => ['nullable', 'string', 'max:400'],
        ], [
            'action.required' => I18n::t('tools.player_action'),
            'action.in' => I18n::t('tools.player_action'),
            'player.required' => I18n::t('tools.player_name'),
            'player.max' => I18n::t('tools.player_name'),
            'reason.max' => I18n::t('tools.player_reason'),
        ]);

        return [
            'action' => $input['action'],
            'player' => $input['player'],
            'reason' => is_string($input['reason'] ?? null) ? $input['reason'] : '',
        ];
    }

    private function respond(string $object, array $attributes, int $status = 200): JsonResponse
    {
        return new JsonResponse(['object' => $object, 'attributes' => $attributes], $status);
    }
}
