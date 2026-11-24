<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Pterodactyl\Models\Egg;
use Illuminate\Support\Facades\Log;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Client\BlueprintClientLibrary;

/**
 * Admin switch for changing a server's Minecraft loader. Off until an administrator
 * turns it on. The allow-list is egg ids, never a free id from the browser.
 * Stored as JSON via dbSet('techtyl', 'tools_switch').
 *
 * If several allowed eggs share one loader, the egg with the lowest id is used.
 * A target egg in another nest is allowed: the panel service sets nest and egg.
 */
final class SoftwarePolicy
{
    public const TABLE = 'techtyl';

    public const RECORD = 'tools_switch';

    /** @var array{enabled: bool, eggs: array<int, int>}|null */
    private static ?array $loaded = null;

    public static function enabled(): bool
    {
        return self::all()['enabled'];
    }

    /** @return array<int, int> */
    public static function allowedIds(): array
    {
        return self::all()['eggs'];
    }

    /** @return array{enabled: bool, eggs: array<int, int>} */
    public static function all(): array
    {
        if (self::$loaded === null) {
            $decoded = null;
            try {
                $stored = app(BlueprintClientLibrary::class)->dbGet(self::TABLE, self::RECORD, '');
                $decoded = is_string($stored) && $stored !== '' ? json_decode($stored, true) : null;
            } catch (\Throwable $e) {
                Log::warning('techtyl tools: software switch settings unreadable', ['error' => substr($e->getMessage(), 0, 200)]);
            }
            self::$loaded = self::normalize(is_array($decoded) ? $decoded : []);
        }

        return self::$loaded;
    }

    /** @param array<string, mixed> $input */
    public static function save(array $input): void
    {
        $clean = self::normalize($input);
        $known = [];
        foreach (self::choices() as $choice) {
            $known[] = $choice['id'];
        }
        $clean['eggs'] = array_values(array_intersect($clean['eggs'], $known));
        app(BlueprintClientLibrary::class)->dbSet(self::TABLE, self::RECORD, json_encode($clean));
        self::$loaded = $clean;
    }

    public static function flush(): void
    {
        self::$loaded = null;
    }

    /**
     * The single egg users may switch to for this loader, or null when none is allowed.
     * Lowest id wins when the allow-list contains more than one egg of the same loader.
     */
    public static function eggFor(string $loader): ?Egg
    {
        if (!in_array($loader, ServerTypes::SOFTWARE, true)) {
            return null;
        }

        return self::eggsByLoader()[$loader] ?? null;
    }

    /**
     * One egg per loader. The query is ordered by id, so the first match is the lowest id.
     *
     * @return array<string, Egg>
     */
    public static function eggsByLoader(): array
    {
        if (!self::enabled()) {
            return [];
        }
        $ids = self::allowedIds();
        if ($ids === []) {
            return [];
        }
        $map = [];
        foreach (Egg::query()->with(['variables', 'nest'])->whereIn('id', $ids)->orderBy('id')->get() as $egg) {
            $type = ServerTypes::forEgg($egg)['type'];
            if (!in_array($type, ServerTypes::SOFTWARE, true) || isset($map[$type])) {
                continue;
            }
            $map[$type] = $egg;
        }

        return $map;
    }

    /**
     * Eggs an administrator may tick. Only Minecraft loaders (not proxies, not other games).
     * The type is the effective mapping: an admin row wins, otherwise the suggestion.
     *
     * @return array<int, array{id: int, name: string, nest: string, type: string, label: string}>
     */
    public static function choices(): array
    {
        $list = [];
        foreach (Egg::query()->with(['variables', 'nest'])->get() as $egg) {
            $type = ServerTypes::forEgg($egg)['type'];
            if (!in_array($type, ServerTypes::SOFTWARE, true)) {
                continue;
            }
            $list[] = [
                'id' => (int) $egg->id,
                'name' => (string) $egg->name,
                'nest' => (string) ($egg->nest->name ?? ''),
                'type' => $type,
                'label' => ServerTypes::label($type),
            ];
        }
        usort($list, fn ($a, $b) => [$a['nest'], $a['name'], $a['id']] <=> [$b['nest'], $b['name'], $b['id']]);

        return $list;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{enabled: bool, eggs: array<int, int>}
     */
    public static function normalize(array $input): array
    {
        $flag = $input['enabled'] ?? false;
        $eggs = [];
        foreach (is_array($input['eggs'] ?? null) ? $input['eggs'] : [] as $id) {
            if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
                continue;
            }
            $id = (int) $id;
            if ($id > 0 && $id <= 1000000000) {
                $eggs[$id] = $id;
            }
            if (count($eggs) >= 500) {
                break;
            }
        }
        $eggs = array_values($eggs);
        sort($eggs);

        return [
            'enabled' => $flag === true || $flag === 1 || $flag === '1',
            'eggs' => $eggs,
        ];
    }
}
