<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Http;

use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Media;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Design;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Limits;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Settings;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\ImageProcessor;

/**
 * Client-API von Techtyl unter /api/client/extensions/techtyl.
 * Jede Abfrage ist an den angemeldeten Nutzer gebunden; fremde Ordner und Server ergeben 404.
 */
class ClientController extends Controller
{
    private const MAX_FOLDERS = 50;
    private const MAX_SERVERS_PER_REQUEST = 500;
    private const FOLDER_NAME_RULES = ['required', 'string', 'min:1', 'max:40', 'regex:/^[^\p{Cc}\p{Cf}<>]+$/u'];

    /** Alles, was das Dashboard beim Start braucht. */
    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();
        $state = Settings::effectiveFor($user->id);
        $servers = $this->accessibleServers($user);

        return new JsonResponse([
            'object' => 'techtyl_bootstrap',
            'attributes' => $this->settingsAttributes($state) + [
                'limits' => [
                    'max_upload_bytes' => $state['limits']['max_upload_kb'] * 1024,
                    'mime_types' => array_keys(array_filter(ImageProcessor::readable())),
                    'background_max' => ImageProcessor::KINDS['background'],
                    'banner_max' => ImageProcessor::KINDS['banner'],
                    'max_folders' => self::MAX_FOLDERS,
                ],
                'banners' => $this->banners($user, $servers, $state['permissions']['banners']),
                'folders' => $this->folders($user, $servers),
            ],
        ]);
    }

    public function settings(Request $request): JsonResponse
    {
        return $this->settingsResponse($request->user());
    }

    /**
     * Speichert eigene Design-Werte. Uebergebene Schluessel werden uebernommen, null setzt einen
     * Schluessel auf den Admin-Standard zurueck, nicht genannte bleiben unveraendert.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $rules = ['design' => ['required', 'array', 'max:' . count(Design::userKeys())]];
        foreach (Design::rules() as $key => $keyRules) {
            if (Design::groupOf($key) !== null) {
                $rules['design.' . $key] = array_merge(['sometimes', 'nullable'], $keyRules);
            }
        }
        $request->validate($rules);

        // Nur bekannte Schluessel; die Werte sind durch die Regeln oben geprueft und laufen vor dem
        // Speichern noch einmal durch Design::partial().
        $input = (array) $request->input('design');
        if (array_diff(array_keys($input), Design::userKeys()) !== []) {
            throw ValidationException::withMessages(['design' => ['Unbekannte Einstellung.']]);
        }

        $user = $request->user();
        $permissions = Limits::permissions(Settings::limits());
        foreach (array_keys($input) as $key) {
            if (empty($permissions[Design::groupOf($key)])) {
                throw new AccessDeniedHttpException('Diese Einstellung darf nicht geaendert werden.');
            }
        }

        DB::transaction(function () use ($user, $input) {
            $row = DB::table(Settings::USER_TABLE)->where('user_id', $user->id)->lockForUpdate()->first(['design']);
            $stored = $row !== null ? json_decode((string) $row->design, true) : [];
            $merged = array_merge(is_array($stored) ? $stored : [], $input);
            $this->saveUserRow($user->id, ['design' => json_encode(Design::partial($merged))]);
        });

        return $this->settingsResponse($user);
    }

    public function uploadBackground(Request $request): JsonResponse
    {
        $user = $request->user();
        $limits = Settings::limits();
        if (!$limits['user_background']) {
            throw new AccessDeniedHttpException('Eigene Hintergrundbilder sind abgeschaltet.');
        }

        $name = Media::receive($request, 'background', $limits['max_upload_kb']);
        try {
            $previous = DB::table(Settings::USER_TABLE)->where('user_id', $user->id)->value('background_file');
            $this->saveUserRow($user->id, ['background_file' => $name]);
        } catch (\Throwable $e) {
            Media::delete($name);
            throw $e;
        }
        Media::delete($previous);

        return $this->settingsResponse($user);
    }

    public function deleteBackground(Request $request): JsonResponse
    {
        $user = $request->user();
        $previous = DB::table(Settings::USER_TABLE)->where('user_id', $user->id)->value('background_file');
        if ($previous !== null) {
            DB::table(Settings::USER_TABLE)->where('user_id', $user->id)->update(['background_file' => null, 'updated_at' => now()]);
            Media::delete($previous);
        }

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    public function uploadBanner(Request $request, Server $server): JsonResponse
    {
        $user = $request->user();
        $limits = Settings::limits();
        $this->authorizeBanner($user, $server, $limits);

        $name = Media::receive($request, 'banner', $limits['max_upload_kb']);
        try {
            $previous = DB::table('techtyl_server_banners')->where('server_id', $server->id)->value('file');
            DB::table('techtyl_server_banners')->updateOrInsert(
                ['server_id' => $server->id],
                ['file' => $name, 'uploaded_by' => $user->id, 'updated_at' => now()],
            );
        } catch (\Throwable $e) {
            Media::delete($name);
            throw $e;
        }
        Media::delete($previous);

        return new JsonResponse([
            'object' => 'techtyl_banner',
            'attributes' => [
                'server' => $server->uuid,
                'identifier' => $server->uuidShort,
                'url' => Media::url($name),
                'source' => 'server',
                'can_edit' => true,
            ],
        ]);
    }

    public function deleteBanner(Request $request, Server $server): JsonResponse
    {
        $this->authorizeBanner($request->user(), $server, Settings::limits());

        $previous = DB::table('techtyl_server_banners')->where('server_id', $server->id)->value('file');
        if ($previous !== null) {
            DB::table('techtyl_server_banners')->where('server_id', $server->id)->delete();
            Media::delete($previous);
        }

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    public function folderIndex(Request $request): JsonResponse
    {
        $user = $request->user();

        return new JsonResponse(['object' => 'list', 'data' => $this->folders($user, $this->accessibleServers($user))]);
    }

    public function folderStore(Request $request): JsonResponse
    {
        $name = trim($request->validate(['name' => self::FOLDER_NAME_RULES])['name']);
        $user = $request->user();

        $id = DB::transaction(function () use ($user, $name) {
            $existing = DB::table('techtyl_folders')->where('user_id', $user->id)->lockForUpdate()->pluck('position');
            if ($existing->count() >= self::MAX_FOLDERS) {
                throw ValidationException::withMessages(['name' => ['Es sind hoechstens ' . self::MAX_FOLDERS . ' Ordner moeglich.']]);
            }

            return DB::table('techtyl_folders')->insertGetId([
                'user_id' => $user->id,
                'name' => $name,
                'position' => $existing->isEmpty() ? 0 : ((int) $existing->max()) + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return new JsonResponse($this->folderObject($user, (int) $id), JsonResponse::HTTP_CREATED);
    }

    public function folderUpdate(Request $request, string $folder): JsonResponse
    {
        $name = trim($request->validate(['name' => self::FOLDER_NAME_RULES])['name']);
        $user = $request->user();
        $id = $this->ownFolderId($user, $folder);

        DB::table('techtyl_folders')->where('id', $id)->where('user_id', $user->id)->update(['name' => $name, 'updated_at' => now()]);

        return new JsonResponse($this->folderObject($user, $id));
    }

    public function folderDelete(Request $request, string $folder): JsonResponse
    {
        $user = $request->user();
        $id = $this->ownFolderId($user, $folder);

        // Die Zuordnungen fallen ueber den Fremdschluessel mit weg; die Server selbst bleiben unberuehrt.
        DB::table('techtyl_folders')->where('id', $id)->where('user_id', $user->id)->delete();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /** Neue Reihenfolge: alle eigenen Ordner-IDs in der gewuenschten Folge. */
    public function folderOrder(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'folders' => ['present', 'array', 'max:' . self::MAX_FOLDERS],
            'folders.*' => ['required', 'integer', 'min:1', 'distinct'],
        ])['folders'];
        $ids = array_map('intval', $ids);
        $user = $request->user();

        DB::transaction(function () use ($user, $ids) {
            $own = DB::table('techtyl_folders')->where('user_id', $user->id)->lockForUpdate()->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (count($ids) !== count($own) || array_diff($own, $ids) !== []) {
                throw ValidationException::withMessages(['folders' => ['Die Liste muss genau die eigenen Ordner enthalten.']]);
            }
            foreach ($ids as $position => $id) {
                DB::table('techtyl_folders')->where('id', $id)->where('user_id', $user->id)->update(['position' => $position]);
            }
        });

        return $this->folderIndex($request);
    }

    /**
     * Setzt den Inhalt eines Ordners: Server-UUIDs in der gewuenschten Folge. Server, die bisher in
     * einem anderen Ordner lagen, werden verschoben; nicht mehr genannte verlassen den Ordner.
     */
    public function folderServers(Request $request, string $folder): JsonResponse
    {
        $uuids = $request->validate([
            'servers' => ['present', 'array', 'max:' . self::MAX_SERVERS_PER_REQUEST],
            'servers.*' => ['required', 'string', 'uuid', 'distinct'],
        ])['servers'];
        $user = $request->user();
        $id = $this->ownFolderId($user, $folder);

        $allowed = $user->root_admin
            ? DB::table('servers')->whereIn('uuid', $uuids)->pluck('id', 'uuid')
            : $this->accessibleServers($user)->pluck('id', 'uuid');
        $serverIds = [];
        foreach ($uuids as $uuid) {
            if (!isset($allowed[$uuid])) {
                throw ValidationException::withMessages(['servers' => ['Mindestens ein Server wurde nicht gefunden.']]);
            }
            $serverIds[] = (int) $allowed[$uuid];
        }

        DB::transaction(function () use ($user, $id, $serverIds) {
            DB::table('techtyl_folder_servers')->where('user_id', $user->id)
                ->where(fn ($query) => $query->where('folder_id', $id)->orWhereIn('server_id', $serverIds))
                ->delete();
            $rows = [];
            foreach ($serverIds as $position => $serverId) {
                $rows[] = ['folder_id' => $id, 'user_id' => $user->id, 'server_id' => $serverId, 'position' => $position];
            }
            if ($rows !== []) {
                DB::table('techtyl_folder_servers')->insert($rows);
            }
        });

        return new JsonResponse($this->folderObject($user, $id));
    }

    /**
     * Banner aendern duerfen Besitzer, Root-Admins und Subuser mit dem Recht "settings.rename".
     * Dass der Nutzer den Server ueberhaupt sehen darf, prueft vorher die Panel-Middleware
     * AuthenticateServerAccess (sonst 404).
     */
    private function authorizeBanner(User $user, Server $server, array $limits): void
    {
        if (!$user->can(Permission::ACTION_SETTINGS_RENAME, $server)) {
            throw new AccessDeniedHttpException('Keine Berechtigung fuer das Banner dieses Servers.');
        }
        if (!$limits['user_banners'] && !$user->root_admin) {
            throw new AccessDeniedHttpException('Eigene Banner sind abgeschaltet.');
        }
    }

    private function ownFolderId(User $user, string $folder): int
    {
        $id = ctype_digit($folder) && strlen($folder) <= 10 ? (int) $folder : 0;
        if ($id < 1 || !DB::table('techtyl_folders')->where('id', $id)->where('user_id', $user->id)->exists()) {
            throw new NotFoundHttpException('Der Ordner wurde nicht gefunden.');
        }

        return $id;
    }

    /** @param array<string, mixed> $values */
    private function saveUserRow(int $userId, array $values): void
    {
        $updated = DB::table(Settings::USER_TABLE)->where('user_id', $userId)->update($values + ['updated_at' => now()]);
        if ($updated === 0 && !DB::table(Settings::USER_TABLE)->where('user_id', $userId)->exists()) {
            DB::table(Settings::USER_TABLE)->insert($values + ['user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function settingsResponse(User $user): JsonResponse
    {
        return new JsonResponse([
            'object' => 'techtyl_settings',
            'attributes' => $this->settingsAttributes(Settings::effectiveFor($user->id)),
        ]);
    }

    /** @param array<string, mixed> $state Ergebnis von Settings::effectiveFor() */
    private function settingsAttributes(array $state): array
    {
        return [
            'design' => $state['design'],
            'user_design' => (object) $state['own'],
            'background' => [
                'url' => $state['design']['background'],
                'custom' => $state['own_background'] !== '' && $state['design']['background'] === $state['own_background'],
                'custom_url' => $state['own_background'],
            ],
            'permissions' => $state['permissions'],
        ];
    }

    /** Server, die der Nutzer als Besitzer oder Subuser sieht. */
    private function accessibleServers(User $user): Collection
    {
        return DB::table('servers')
            ->where('owner_id', $user->id)
            ->orWhereIn('id', fn ($query) => $query->select('server_id')->from('subusers')->where('user_id', $user->id))
            ->orderBy('id')
            ->get(['id', 'uuid', 'uuidShort', 'owner_id', 'egg_id']);
    }

    /**
     * Banner je Server-UUID: eigenes Banner, sonst das Standard-Banner des Eggs, sonst leer.
     *
     * @return object
     */
    private function banners(User $user, Collection $servers, bool $bannersAllowed): object
    {
        if ($servers->isEmpty()) {
            return (object) [];
        }
        $own = DB::table('techtyl_server_banners')->whereIn('server_id', $servers->pluck('id'))->pluck('file', 'server_id');
        $eggs = DB::table('techtyl_egg_banners')->whereIn('egg_id', $servers->pluck('egg_id')->unique())->pluck('file', 'egg_id');
        $subuser = DB::table('subusers')->where('user_id', $user->id)->pluck('permissions', 'server_id');

        $out = [];
        foreach ($servers as $server) {
            $granted = $user->root_admin || (int) $server->owner_id === $user->id;
            if (!$granted && isset($subuser[$server->id])) {
                $permissions = json_decode((string) $subuser[$server->id], true);
                $granted = is_array($permissions) && in_array(Permission::ACTION_SETTINGS_RENAME, $permissions, true);
            }
            $url = Media::url($own[$server->id] ?? null);
            $source = $url !== '' ? 'server' : null;
            if ($url === '') {
                $url = Media::url($eggs[$server->egg_id] ?? null);
                $source = $url !== '' ? 'egg' : null;
            }
            $out[$server->uuid] = [
                'identifier' => $server->uuidShort,
                'url' => $url,
                'source' => $source,
                'can_edit' => $granted && ($bannersAllowed || $user->root_admin),
            ];
        }

        return (object) $out;
    }

    /** @return array<int, array<string, mixed>> */
    private function folders(User $user, Collection $servers, ?int $onlyId = null): array
    {
        $folders = DB::table('techtyl_folders')->where('user_id', $user->id)
            ->when($onlyId !== null, fn ($query) => $query->where('id', $onlyId))
            ->orderBy('position')->orderBy('id')->get(['id', 'name', 'position']);
        if ($folders->isEmpty()) {
            return [];
        }

        $visible = $servers->pluck('id')->flip();
        $members = DB::table('techtyl_folder_servers as members')
            ->join('servers', 'servers.id', '=', 'members.server_id')
            ->where('members.user_id', $user->id)
            ->whereIn('members.folder_id', $folders->pluck('id'))
            ->orderBy('members.position')
            ->get(['members.folder_id', 'servers.id', 'servers.uuid']);

        $byFolder = [];
        foreach ($members as $member) {
            // Zuordnungen zu Servern, auf die der Nutzer keinen Zugriff mehr hat, werden nicht verraten.
            if ($user->root_admin || isset($visible[$member->id])) {
                $byFolder[$member->folder_id][] = $member->uuid;
            }
        }

        return $folders->map(fn ($folder) => [
            'object' => 'techtyl_folder',
            'attributes' => [
                'id' => (int) $folder->id,
                'name' => $folder->name,
                'position' => (int) $folder->position,
                'servers' => $byFolder[$folder->id] ?? [],
            ],
        ])->all();
    }

    private function folderObject(User $user, int $id): array
    {
        return $this->folders($user, $this->accessibleServers($user), $id)[0];
    }
}
