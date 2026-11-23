<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Tasks;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\ServerMeta;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\ServerTypes;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\ToolsException;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\VersionSources;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\VersionSwitcher;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests\VersionRequest;

/** Versionswechsler: Versionen und Builds auflisten, Zusammenfassung zeigen, Wechsel starten. */
class VersionController extends Controller
{
    private const ID_RULE = ['required', 'string', 'regex:' . VersionSources::ID_PATTERN];

    public function index(VersionRequest $request, Server $server): JsonResponse
    {
        $type = ServerTypes::forServer($server)['type'];
        $planned = in_array($type, ServerTypes::VERSION_PLANNED, true);
        $current = ['version' => null, 'build' => null, 'source' => null, 'loader_version' => null];
        try {
            $current = ServerMeta::detect($server, $type);
        } catch (\Throwable $e) {
            // Node nicht erreichbar: Liste trotzdem zeigen.
        }

        return $this->respond('techtyl_tools_versions', [
            'type' => $type,
            'type_label' => ServerTypes::label($type),
            'planned' => $planned,
            'build_label' => $type === 'fabric' ? 'Loader' : 'Build',
            'has_builds' => $type !== 'vanilla',
            'proxy' => $type === 'velocity',
            'current' => [
                'version' => $current['version'],
                'build' => $type === 'fabric' ? $current['loader_version'] : $current['build'],
                'source' => $current['source'],
            ],
            'jar' => ServerMeta::jarName($server),
            'versions' => $planned ? [] : VersionSources::versions($type),
            'switching' => Tasks::hasActive($server, 'version.switch'),
        ]);
    }

    public function builds(VersionRequest $request, Server $server): JsonResponse
    {
        $input = $request->validate(['version' => self::ID_RULE]);
        $type = $this->switchable($server);

        return $this->respond('techtyl_tools_builds', [
            'version' => $input['version'],
            'builds' => VersionSources::builds($type, $input['version']),
        ]);
    }

    public function preview(VersionRequest $request, Server $server): JsonResponse
    {
        $input = $request->validate(['version' => self::ID_RULE, 'build' => self::ID_RULE]);
        $this->switchable($server);
        $plan = VersionSwitcher::plan($server, $request->user(), $input['version'], $input['build']);
        unset($plan['internal']);

        return $this->respond('techtyl_tools_version_preview', $plan);
    }

    public function switch(VersionRequest $request, Server $server): JsonResponse
    {
        $input = $request->validate([
            'version' => self::ID_RULE,
            'build' => self::ID_RULE,
            'backup' => ['nullable', 'boolean'],
            'start' => ['nullable', 'boolean'],
        ]);
        $this->switchable($server);
        $task = VersionSwitcher::queue(
            $server,
            $request->user(),
            $input['version'],
            $input['build'],
            filter_var($input['backup'] ?? false, FILTER_VALIDATE_BOOLEAN),
            filter_var($input['start'] ?? false, FILTER_VALIDATE_BOOLEAN)
        );

        return $this->respond('techtyl_tools_task', Tasks::present($task), 202);
    }

    private function switchable(Server $server): string
    {
        $type = ServerTypes::forServer($server)['type'];
        if (!VersionSources::supports($type)) {
            throw ToolsException::conflict('switch_planned', ['type' => ServerTypes::label($type)]);
        }

        return $type;
    }

    private function respond(string $object, array $attributes, int $status = 200): JsonResponse
    {
        return new JsonResponse(['object' => $object, 'attributes' => $attributes], $status);
    }
}
