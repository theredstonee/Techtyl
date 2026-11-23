<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Wings;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Properties;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\ToolsException;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests\FileUpdateRequest;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests\ContentReadRequest;

/** Read and patch server.properties. Only keys the user changed are written. */
class PropertiesController extends Controller
{
    public function show(ContentReadRequest $request, Server $server): JsonResponse
    {
        $data = Guard::run($server, fn () => Properties::describe(Wings::for($server)));

        return $this->respond('techtyl_tools_properties', $data);
    }

    public function save(FileUpdateRequest $request, Server $server): JsonResponse
    {
        $input = $request->validate([
            'changes' => ['present', 'array', 'max:80'],
            'changes.*' => ['string', 'max:8192'],
            'create' => ['sometimes', 'boolean'],
        ], [
            'changes.present' => I18n::t('tools.props_no_changes'),
            'changes.array' => I18n::t('tools.props_value'),
            'changes.max' => I18n::t('tools.props_too_many'),
            'changes.*.string' => I18n::t('tools.props_value'),
            'changes.*.max' => I18n::t('tools.props_length', ['key' => 'value', 'max' => 8192]),
        ]);
        $changes = [];
        foreach ($input['changes'] as $key => $value) {
            $key = (string) $key;
            if (preg_match(Properties::KEY, $key) !== 1) {
                throw ToolsException::invalid('props_key');
            }
            if (!is_string($value)) {
                throw ToolsException::invalid('props_value');
            }
            $changes[$key] = $value;
        }
        $create = $request->boolean('create');
        $data = Guard::run($server, fn () => Properties::save(Wings::for($server), $changes, $create));

        return $this->respond('techtyl_tools_properties_save', $data);
    }

    private function respond(string $object, array $attributes, int $status = 200): JsonResponse
    {
        return new JsonResponse(['object' => $object, 'attributes' => $attributes], $status);
    }
}
