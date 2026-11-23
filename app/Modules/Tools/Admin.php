<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Illuminate\Http\Request;
use Pterodactyl\Models\Egg;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;

/**
 * Admin-Reiter "Server-Werkzeuge": Zuordnung Egg -> Servertyp. Ohne eigene Zuordnung gilt der
 * automatische Vorschlag (ServerTypes::suggest).
 */
class Admin
{
    public const AUTO = 'auto';

    /** @return array<string, mixed> */
    public static function data(): array
    {
        $ready = ServerTypes::tableReady();
        $assigned = $ready ? ServerTypes::assigned() : [];
        $servers = DB::table('servers')->selectRaw('egg_id, count(*) as total')->groupBy('egg_id')->pluck('total', 'egg_id');

        $eggs = [];
        foreach (Egg::query()->with(['variables', 'nest'])->get() as $egg) {
            $suggested = ServerTypes::suggest($egg);
            $eggs[] = [
                'id' => $egg->id,
                'name' => (string) $egg->name,
                'nest' => (string) ($egg->nest->name ?? ''),
                'servers' => (int) ($servers[$egg->id] ?? 0),
                'assigned' => $assigned[$egg->id] ?? self::AUTO,
                'suggested' => $suggested,
                'suggested_label' => ServerTypes::label($suggested),
                'effective_label' => ServerTypes::label($assigned[$egg->id] ?? $suggested),
            ];
        }
        usort($eggs, fn ($a, $b) => [$a['nest'], $a['name']] <=> [$b['nest'], $b['name']]);

        return [
            'toolsReady' => $ready,
            'toolsEggs' => $eggs,
            'toolsTypes' => ServerTypes::labels(),
            'toolsQueueSync' => config('queue.default') === 'sync',
            'toolsStats' => $ready ? [
                'installed' => DB::table(Content::TABLE)->count(),
                'active' => DB::table(Tasks::TABLE)->whereIn('status', Tasks::ACTIVE)->count(),
                'failed' => DB::table(Tasks::TABLE)->where('status', Tasks::FAILED)->where('finished_at', '>', now()->subDays(7))->count(),
            ] : ['installed' => 0, 'active' => 0, 'failed' => 0],
        ];
    }

    public static function handle(Request $request, string $action): string
    {
        if ($action !== 'egg-types') {
            abort(404);
        }
        if (!ServerTypes::tableReady()) {
            throw ValidationException::withMessages(['types' => [I18n::t('tools.admin_tables_missing')]]);
        }

        $input = $request->validate([
            'types' => ['required', 'array', 'max:2000'],
            'types.*' => ['required', 'string', 'in:' . self::AUTO . ',' . implode(',', array_keys(ServerTypes::TYPES))],
        ])['types'];

        $known = Egg::query()->pluck('id')->all();
        $now = now();
        DB::transaction(function () use ($input, $known, $now) {
            foreach ($input as $eggId => $type) {
                if (!is_int($eggId) && !ctype_digit((string) $eggId)) {
                    continue;
                }
                $eggId = (int) $eggId;
                if (!in_array($eggId, $known, true)) {
                    continue;
                }
                if ($type === self::AUTO) {
                    DB::table(ServerTypes::TABLE)->where('egg_id', $eggId)->delete();
                } else {
                    DB::table(ServerTypes::TABLE)->updateOrInsert(['egg_id' => $eggId], ['type' => $type, 'updated_at' => $now]);
                    DB::table(ServerTypes::TABLE)->where('egg_id', $eggId)->whereNull('created_at')->update(['created_at' => $now]);
                }
            }
        });
        ServerTypes::flush();

        return I18n::t('tools.admin_saved');
    }
}
