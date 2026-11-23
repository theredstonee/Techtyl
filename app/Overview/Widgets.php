<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Overview;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules;

/**
 * Sammelt die Kacheln eingeschalteter Module (Klasse Modules\<Ordner>\OverviewWidget, siehe Widget).
 */
class Widgets
{
    public const CLASS_NAME = 'OverviewWidget';

    private const CACHE_PREFIX = 'techtyl:overview:widget:';

    /** @return list<array{module: string, title: string, value: string, link: ?string, hint: ?string, tone: string, icon: string}> */
    public static function collect(): array
    {
        $tiles = [];
        foreach (Modules::ALL as $key => $module) {
            if (!Modules::enabled($key)) {
                continue;
            }
            $class = 'Pterodactyl\\BlueprintFramework\\Extensions\\{identifier}\\Modules\\' . $module[0] . '\\' . self::CLASS_NAME;
            if (!class_exists($class) || !is_subclass_of($class, Widget::class)) {
                continue;
            }

            // Leere Ergebnisse werden als ['none' => true] gemerkt, damit auch sie 60 s halten.
            // One cache entry per language, because tile texts come from I18n::t().
            $tile = Cache::remember(self::CACHE_PREFIX . $key . ':' . I18n::locale(), 60, static function () use ($class, $key) {
                try {
                    $result = app($class)->tile();

                    return $result instanceof Tile ? $result->toArray() : ['none' => true];
                } catch (\Throwable $e) {
                    Log::warning('Techtyl-Uebersicht: Kachel des Moduls ' . $key . ' fehlgeschlagen', ['exception' => $e]);

                    return ['none' => true];
                }
            });

            if (is_array($tile) && !isset($tile['none']) && ($tile['title'] ?? '') !== '' && ($tile['value'] ?? '') !== '') {
                $tiles[] = ['module' => $key] + $tile;
            }
        }

        return $tiles;
    }
}
