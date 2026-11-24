<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Tasks;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\ServerTypes;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\ToolsException;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\VersionSources;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\SoftwareSwitcher;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests\SoftwareReadRequest;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests\SoftwareSwitchRequest;

/** Loader switch: list, preview, and start. The server resolves the egg. The browser sends a loader type. */
class SoftwareController extends Controller
{
    public function show(SoftwareReadRequest $request, Server $server): JsonResponse
    {
        $this->reads($request->user(), $server);

        return $this->respond('techtyl_tools_software', SoftwareSwitcher::describe($server, $request->user()));
    }

    public function versions(SoftwareReadRequest $request, Server $server): JsonResponse
    {
        $this->reads($request->user(), $server);
        $input = $request->validate([
            'loader' => ['required', 'string', 'in:' . implode(',', ServerTypes::SOFTWARE)],
            'game' => ['nullable', 'string', 'regex:' . VersionSources::ID_PATTERN],
        ], $this->messages());
        $loader = $input['loader'];
        if (is_string($input['game'] ?? null) && $input['game'] !== '') {
            return $this->respond('techtyl_tools_software_builds', [
                'loader' => $loader,
                'game' => $input['game'],
                'builds' => VersionSources::builds($loader, $input['game']),
            ]);
        }

        return $this->respond('techtyl_tools_software_versions', [
            'loader' => $loader,
            'versions' => VersionSources::versions($loader),
        ]);
    }

    public function preview(SoftwareReadRequest $request, Server $server): JsonResponse
    {
        $this->reads($request->user(), $server);
        $input = $request->validate([
            'loader' => ['required', 'string', 'in:' . implode(',', ServerTypes::SOFTWARE)],
            'game' => ['required', 'string', 'regex:' . VersionSources::ID_PATTERN],
            'build' => ['required', 'string', 'regex:' . VersionSources::ID_PATTERN],
        ], $this->messages());
        $plan = SoftwareSwitcher::plan($server, $request->user(), $input['loader'], $input['game'], $input['build']);
        unset($plan['internal']);

        return $this->respond('techtyl_tools_software_preview', $plan);
    }

    public function switch(SoftwareSwitchRequest $request, Server $server): JsonResponse
    {
        $user = $request->user();
        if (!$user->can('startup.update', $server)) {
            throw ToolsException::forbidden('software_no_update');
        }
        if (!$user->can('settings.reinstall', $server)) {
            throw ToolsException::forbidden('software_no_reinstall_right');
        }
        $input = $request->validate([
            'loader' => ['required', 'string', 'in:' . implode(',', ServerTypes::SOFTWARE)],
            'game' => ['required', 'string', 'regex:' . VersionSources::ID_PATTERN],
            'build' => ['required', 'string', 'regex:' . VersionSources::ID_PATTERN],
            'confirm' => ['required', 'string', 'max:32'],
            'backup' => ['nullable', 'boolean'],
            'start' => ['nullable', 'boolean'],
        ], $this->messages());
        $task = SoftwareSwitcher::queue(
            $server,
            $user,
            $input['loader'],
            $input['game'],
            $input['build'],
            $input['confirm'],
            filter_var($input['backup'] ?? false, FILTER_VALIDATE_BOOLEAN),
            filter_var($input['start'] ?? false, FILTER_VALIDATE_BOOLEAN)
        );

        return $this->respond('techtyl_tools_task', Tasks::present($task), 202);
    }

    private function reads(User $user, Server $server): void
    {
        if (!$user->can('startup.read', $server)) {
            throw ToolsException::forbidden('software_no_read');
        }
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'loader.required' => I18n::t('tools.software_input'),
            'loader.string' => I18n::t('tools.software_input'),
            'loader.in' => I18n::t('tools.software_loader_invalid'),
            'game.required' => I18n::t('tools.software_input'),
            'game.string' => I18n::t('tools.software_input'),
            'game.regex' => I18n::t('tools.software_input'),
            'build.required' => I18n::t('tools.software_input'),
            'build.string' => I18n::t('tools.software_input'),
            'build.regex' => I18n::t('tools.software_input'),
            'confirm.required' => I18n::t('tools.software_confirm'),
            'confirm.string' => I18n::t('tools.software_confirm'),
            'confirm.max' => I18n::t('tools.software_confirm'),
            'backup.boolean' => I18n::t('tools.software_input'),
            'start.boolean' => I18n::t('tools.software_input'),
        ];
    }

    private function respond(string $object, array $attributes, int $status = 200): JsonResponse
    {
        return new JsonResponse(['object' => $object, 'attributes' => $attributes], $status);
    }
}
