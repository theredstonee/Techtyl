<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Tasks;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Content;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Modrinth;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\ServerMeta;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\ServerTypes;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\ToolsException;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\VersionSources;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests\ReadRequest;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests\RemoveRequest;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests\InstallRequest;

/**
 * Plugins und Mods aus Modrinth. Der Browser schickt nur Projekt- und Versions-IDs; welche
 * Datei von wo geladen wird, entscheidet allein der Server.
 */
class ContentController extends Controller
{
    private const ID_RULE = ['required', 'string', 'regex:' . Modrinth::ID_PATTERN];

    /** Filter fuer die Suche: Art, Loader, Spielversion des Servers, Kategorien, Sortierungen. */
    public function filters(ReadRequest $request, Server $server): JsonResponse
    {
        $capability = Content::capability($server);

        return $this->respond('techtyl_tools_content_filters', [
            'kind' => $capability['kind'],
            'directory' => $capability['directory'],
            'loaders' => $capability['loaders'],
            'type_label' => ServerTypes::label(ServerTypes::forServer($server)['type']),
            'game_version' => ServerMeta::gameVersion($server),
            'categories' => Modrinth::categories($capability['kind']),
            'sorts' => array_map(fn ($id) => ['id' => $id, 'label' => I18n::t('tools.sort_' . $id)], Modrinth::SORTS),
        ]);
    }

    public function search(ReadRequest $request, Server $server): JsonResponse
    {
        $input = $request->validate([
            'query' => ['nullable', 'string', 'max:80'],
            'sort' => ['nullable', 'string', 'in:' . implode(',', Modrinth::SORTS)],
            'page' => ['nullable', 'integer', 'min:0', 'max:' . Modrinth::MAX_PAGE],
            'categories' => ['nullable', 'array', 'max:5'],
            'categories.*' => ['string', 'regex:/^[a-z0-9-]{2,30}$/'],
            'game_version' => ['nullable', 'string', 'max:40'],
            'any_version' => ['nullable', 'boolean'],
        ]);
        $capability = Content::capability($server);

        // Kategorien nur aus der Liste von Modrinth.
        $known = array_column(Modrinth::categories($capability['kind']), 'id');
        $categories = array_values(array_intersect($input['categories'] ?? [], $known));

        // Spielversion: die des Servers; eine andere nur, wenn sie wie eine Version aussieht.
        $gameVersion = null;
        if (!filter_var($input['any_version'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $requested = $input['game_version'] ?? null;
            $gameVersion = is_string($requested) && $requested !== '' && VersionSources::validId($requested) && preg_match('/^\d/', $requested)
                ? $requested
                : ServerMeta::gameVersion($server);
        }

        $query = trim(preg_replace('/[\x00-\x1f\x7f]/', ' ', (string) ($input['query'] ?? '')) ?? '');
        $result = Modrinth::search($capability['kind'], $capability['loaders'], $gameVersion, $query, $categories, $input['sort'] ?? ($query === '' ? 'downloads' : 'relevance'), (int) ($input['page'] ?? 0));

        $installed = Content::installedProjects($server);
        foreach ($result['hits'] as &$hit) {
            $hit['installed'] = isset($installed[$hit['project_id']]);
        }
        unset($hit);

        return $this->respond('techtyl_tools_content_search', $result + ['game_version' => $gameVersion]);
    }

    public function project(ReadRequest $request, Server $server, string $project): JsonResponse
    {
        $this->assertId($project);
        $data = Modrinth::project($project);
        $data['installed_version_id'] = Content::installedProjects($server)[$project] ?? null;

        return $this->respond('techtyl_tools_content_project', $data);
    }

    /** Versionen eines Projekts, passend zu Loader und Spielversion (mit all=1 nur zum Loader). */
    public function versions(ReadRequest $request, Server $server, string $project): JsonResponse
    {
        $this->assertId($project);
        $all = filter_var($request->validate(['all' => ['nullable', 'boolean']])['all'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $capability = Content::capability($server);
        $gameVersion = ServerMeta::gameVersion($server);

        $versions = Modrinth::versions($project, $capability['loaders'], $all ? null : $gameVersion);

        return $this->respond('techtyl_tools_content_versions', [
            'game_version' => $gameVersion,
            'filtered' => !$all && $gameVersion !== null,
            'installed_version_id' => Content::installedProjects($server)[$project] ?? null,
            'versions' => array_map(fn ($version) => Content::presentVersion($version, $gameVersion), $versions),
        ]);
    }

    public function dependencies(ReadRequest $request, Server $server, string $versionId): JsonResponse
    {
        $this->assertId($versionId);

        return $this->respond('techtyl_tools_content_dependencies', ['dependencies' => Content::dependencies($server, $versionId)]);
    }

    public function installed(ReadRequest $request, Server $server): JsonResponse
    {
        $capability = Content::capability($server);

        return $this->respond('techtyl_tools_content_installed', [
            'kind' => $capability['kind'],
            'directory' => $capability['directory'],
            'items' => Content::installed($server),
        ]);
    }

    public function updates(ReadRequest $request, Server $server): JsonResponse
    {
        return $this->respond('techtyl_tools_content_updates', Content::updates($server));
    }

    public function install(InstallRequest $request, Server $server): JsonResponse
    {
        $input = $request->validate(['project_id' => self::ID_RULE, 'version_id' => self::ID_RULE]);
        $task = Content::queueInstall($server, $request->user(), $input['project_id'], $input['version_id']);

        return $this->respond('techtyl_tools_task', Tasks::present($task), 202);
    }

    public function update(InstallRequest $request, Server $server): JsonResponse
    {
        $input = $request->validate(['file' => ['required', 'string', 'max:191']]);
        // Aktualisieren ersetzt eine Datei: dafuer braucht es auch das Recht zum Loeschen.
        if (!$request->user()->can('file.delete', $server)) {
            throw ToolsException::forbidden('update_needs_delete');
        }
        $task = Content::queueUpdate($server, $request->user(), $input['file']);

        return $this->respond('techtyl_tools_task', Tasks::present($task), 202);
    }

    public function remove(RemoveRequest $request, Server $server): JsonResponse
    {
        $input = $request->validate(['file' => ['required', 'string', 'max:191']]);
        Content::remove($server, $input['file']);
        Activity::event('server:techtyl.content-remove')->property('file', $input['file'])->log();

        return new JsonResponse([], 204);
    }

    private function assertId(string $id): void
    {
        if (!Modrinth::validId($id)) {
            throw ToolsException::notFound('unknown_id');
        }
    }

    private function respond(string $object, array $attributes, int $status = 200): JsonResponse
    {
        return new JsonResponse(['object' => $object, 'attributes' => $attributes], $status);
    }
}
