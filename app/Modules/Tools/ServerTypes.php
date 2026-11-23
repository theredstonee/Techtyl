<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;

/**
 * Welcher Servertyp steckt hinter einem Egg? Die Zuordnung des Admins (Tabelle
 * techtyl_tools_egg_types) geht vor; ohne Eintrag gilt die Heuristik suggest().
 * Aus dem Typ folgt, welche Werkzeuge ein Server anbietet (TOOLS).
 */
final class ServerTypes
{
    public const TABLE = 'techtyl_tools_egg_types';

    public const NONE = 'none';

    /** Typ => Bezeichnung */
    public const TYPES = [
        'paper' => 'Paper',
        'purpur' => 'Purpur',
        'folia' => 'Folia',
        'vanilla' => 'Vanilla',
        'fabric' => 'Fabric',
        'forge' => 'Forge',
        'neoforge' => 'NeoForge',
        'velocity' => 'Velocity',
        'other' => 'Minecraft',
        'none' => '-',
    ];

    /**
     * Inhalte aus Modrinth je Typ: Art, Zielordner und die Loader, die der Server laden kann
     * (der erste ist der eigentliche Loader des Servers).
     */
    public const CONTENT = [
        'paper' => ['kind' => 'plugin', 'directory' => 'plugins', 'loaders' => ['paper', 'spigot', 'bukkit']],
        'purpur' => ['kind' => 'plugin', 'directory' => 'plugins', 'loaders' => ['purpur', 'paper', 'spigot', 'bukkit']],
        'folia' => ['kind' => 'plugin', 'directory' => 'plugins', 'loaders' => ['folia']],
        'velocity' => ['kind' => 'plugin', 'directory' => 'plugins', 'loaders' => ['velocity']],
        'fabric' => ['kind' => 'mod', 'directory' => 'mods', 'loaders' => ['fabric']],
        'forge' => ['kind' => 'mod', 'directory' => 'mods', 'loaders' => ['forge']],
        'neoforge' => ['kind' => 'mod', 'directory' => 'mods', 'loaders' => ['neoforge']],
    ];

    /** Typen, deren Version Techtyl wechseln kann, und solche, bei denen das noch folgt. */
    public const VERSION_SWITCH = ['paper', 'purpur', 'folia', 'vanilla', 'fabric', 'velocity'];
    public const VERSION_PLANNED = ['forge', 'neoforge'];

    /**
     * Werkzeug => Typen, fuer die es gilt. Ein weiteres Werkzeug (z. B. fuer eine neue Seite)
     * wird hier eingetragen; die Middleware ToolAvailable und GET /meta nutzen diese Liste.
     */
    public const TOOLS = [
        'content' => ['paper', 'purpur', 'folia', 'velocity', 'fabric', 'forge', 'neoforge'],
        'versions' => ['paper', 'purpur', 'folia', 'vanilla', 'fabric', 'velocity', 'forge', 'neoforge'],
        'players' => ['paper', 'purpur', 'folia', 'vanilla', 'fabric', 'forge', 'neoforge', 'other'],
        'worlds' => ['paper', 'purpur', 'folia', 'vanilla', 'fabric', 'forge', 'neoforge', 'other'],
        'properties' => ['paper', 'purpur', 'folia', 'vanilla', 'fabric', 'forge', 'neoforge', 'other'],
        'logs' => ['paper', 'purpur', 'folia', 'vanilla', 'fabric', 'forge', 'neoforge', 'velocity', 'other'],
    ];

    /** @var array<int, string>|null */
    private static ?array $assigned = null;

    public static function isType(mixed $type): bool
    {
        return is_string($type) && isset(self::TYPES[$type]);
    }

    /** Display name; product names stay as they are, the generic ones are translated. */
    public static function label(string $type): string
    {
        if (!isset(self::TYPES[$type]) || in_array($type, ['velocity', 'other', 'none'], true)) {
            return I18n::t('tools.type_' . (isset(self::TYPES[$type]) ? $type : 'none'));
        }

        return self::TYPES[$type];
    }

    /** @return array<string, string> type => display name */
    public static function labels(): array
    {
        $labels = [];
        foreach (array_keys(self::TYPES) as $type) {
            $labels[$type] = self::label($type);
        }

        return $labels;
    }

    /** @return array<int, string> Egg-ID => vom Admin festgelegter Typ */
    public static function assigned(): array
    {
        if (self::$assigned === null) {
            self::$assigned = [];
            try {
                foreach (DB::table(self::TABLE)->pluck('type', 'egg_id') as $eggId => $type) {
                    if (self::isType($type)) {
                        self::$assigned[(int) $eggId] = $type;
                    }
                }
            } catch (\Throwable $e) {
                // Tabelle fehlt (Migration noch nicht gelaufen): es gilt die Heuristik.
            }
        }

        return self::$assigned;
    }

    public static function flush(): void
    {
        self::$assigned = null;
    }

    /** @return array{type: string, source: string} source ist "admin" oder "auto". */
    public static function forEgg(Egg $egg): array
    {
        $assigned = self::assigned()[$egg->id] ?? null;

        return $assigned !== null
            ? ['type' => $assigned, 'source' => 'admin']
            : ['type' => self::suggest($egg), 'source' => 'auto'];
    }

    /** @return array{type: string, source: string} */
    public static function forServer(Server $server): array
    {
        return self::forEgg($server->egg);
    }

    public static function toolAvailable(string $tool, string $type): bool
    {
        return in_array($type, self::TOOLS[$tool] ?? [], true);
    }

    /**
     * Vorschlag aus Name, Variablen und Features des Eggs. Der Name entscheidet zuerst, dann
     * typische Variablen; ein Egg mit eula-Feature oder SERVER_JARFILE gilt mindestens als
     * "anderer Minecraft-Typ", alles Uebrige als kein Minecraft-Server.
     */
    public static function suggest(Egg $egg): string
    {
        $name = strtolower((string) $egg->name);
        $variables = $egg->variables->pluck('env_variable')->map(fn ($v) => strtoupper((string) $v))->all();
        $has = fn (string $variable) => in_array($variable, $variables, true);
        $features = is_array($egg->features) ? $egg->features : [];
        $minecraft = in_array('eula', $features, true) || $has('SERVER_JARFILE');

        // Bedrock, Proxys ohne Unterstuetzung und Aehnliches: Minecraft, aber ohne passende Werkzeuge.
        foreach (['bedrock', 'nukkit', 'pocketmine', 'bungee', 'waterfall', 'sponge', 'quilt', 'geyser'] as $word) {
            if (str_contains($name, $word)) {
                return 'other';
            }
        }
        if (!$minecraft && !preg_match('/minecraft|paper|purpur|folia|fabric|forge|velocity|vanilla/', $name)) {
            return self::NONE;
        }

        foreach (['neoforge' => 'neoforge', 'purpur' => 'purpur', 'folia' => 'folia', 'velocity' => 'velocity', 'fabric' => 'fabric', 'forge' => 'forge', 'paper' => 'paper', 'vanilla' => 'vanilla'] as $word => $type) {
            if (str_contains($name, $word)) {
                return $type;
            }
        }

        if ($has('VELOCITY_VERSION')) {
            return 'velocity';
        }
        if ($has('NEOFORGE_VERSION')) {
            return 'neoforge';
        }
        if ($has('FORGE_VERSION')) {
            return 'forge';
        }
        if ($has('FABRIC_VERSION') || $has('LOADER_VERSION')) {
            return 'fabric';
        }
        if ($has('VANILLA_VERSION')) {
            return 'vanilla';
        }
        if ($has('MINECRAFT_VERSION') && $has('BUILD_NUMBER')) {
            return 'paper';
        }

        return $minecraft ? 'other' : self::NONE;
    }

    public static function tableReady(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
