<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier};

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Nest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Services\Nests\NestCreationService;
use Pterodactyl\Services\Eggs\Sharing\EggImporterService;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Client\BlueprintClientLibrary;

/**
 * Importiert die mit Techtyl gelieferten Eggs (data/eggs/*.json) einmalig ins Panel.
 * Vorhandene Eggs gleichen Namens im selben Nest werden nie ueberschrieben oder doppelt angelegt.
 */
class Eggs
{
    public const RECORD = 'eggs_imported';

    /** Datei => Name des Nests, in das der Egg gehoert */
    public const FILES = [
        'nodejs-generic.json' => 'Generic',
        'nodemon.json' => 'Generic',
        'python-generic.json' => 'Generic',
        'bun.json' => 'Generic',
        'deno-generic.json' => 'Generic',
        'minecraft-fabric.json' => 'Minecraft',
        'minecraft-velocity.json' => 'Minecraft',
    ];

    public static function directory(): string
    {
        return base_path('.blueprint/extensions/{identifier}/private/eggs');
    }

    /** Fuehrt den Import nur aus, solange er noch nicht erfolgreich gelaufen ist. Wirft nie. */
    public static function importOnce(): array
    {
        try {
            $library = app(BlueprintClientLibrary::class);
            if ($library->dbGet(Design::TABLE, self::RECORD, '') === 'yes') {
                return ['imported' => [], 'skipped' => [], 'failed' => [], 'already' => true];
            }
            $result = self::import();
            // Erst als erledigt merken, wenn der Ordner da war und nichts fehlschlug;
            // sonst versucht es der naechste Aufruf erneut.
            if ($result['failed'] === [] && ($result['imported'] !== [] || $result['skipped'] !== [])) {
                $library->dbSet(Design::TABLE, self::RECORD, 'yes');
            }

            return $result;
        } catch (\Throwable $e) {
            Log::warning('techtyl: Egg-Import nicht moeglich', ['error' => $e->getMessage()]);

            return ['imported' => [], 'skipped' => [], 'failed' => ['*'], 'already' => false];
        }
    }

    /** @return array{imported: string[], skipped: string[], failed: string[], already: bool} */
    public static function import(): array
    {
        $result = ['imported' => [], 'skipped' => [], 'failed' => [], 'already' => false];
        $directory = self::directory();
        if (!is_dir($directory)) {
            $result['failed'][] = '*';

            return $result;
        }

        foreach (self::FILES as $file => $nestName) {
            $path = $directory . '/' . $file;
            try {
                $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
                $name = is_array($decoded) ? ($decoded['name'] ?? null) : null;
                if (!is_string($name) || $name === '') {
                    $result['failed'][] = $file;
                    continue;
                }

                $nest = self::nest($nestName);
                if (Egg::query()->where('nest_id', $nest->id)->where('name', $name)->exists()) {
                    $result['skipped'][] = $name;
                    continue;
                }

                app(EggImporterService::class)->handle(
                    new UploadedFile($path, $file, 'application/json', null, true),
                    $nest->id
                );
                $result['imported'][] = $name;
            } catch (\Throwable $e) {
                Log::warning('techtyl: Egg konnte nicht importiert werden', ['file' => $file, 'error' => $e->getMessage()]);
                $result['failed'][] = $file;
            }
        }

        return $result;
    }

    private static function nest(string $name): Nest
    {
        $nest = Nest::query()->where('name', $name)->first();
        if ($nest !== null) {
            return $nest;
        }

        return app(NestCreationService::class)->handle([
            'name' => $name,
            'description' => I18n::t('core.nest_generic'),
        ]);
    }
}
