<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Illuminate\Support\Facades\Log;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Client\BlueprintClientLibrary;

/**
 * Small admin switches for the server tools. Stored as one JSON record.
 * Sharing logs is on until an administrator turns it off.
 */
final class ToolsSettings
{
    public const TABLE = 'techtyl';

    public const RECORD = 'tools_settings';

    /** @var array<string, mixed>|null */
    private static ?array $loaded = null;

    public static function shareLogs(): bool
    {
        return self::all()['share_logs'] === true;
    }

    /** @return array{share_logs: bool} */
    public static function all(): array
    {
        if (self::$loaded === null) {
            $decoded = null;
            try {
                $stored = app(BlueprintClientLibrary::class)->dbGet(self::TABLE, self::RECORD, '');
                $decoded = is_string($stored) && $stored !== '' ? json_decode($stored, true) : null;
            } catch (\Throwable $e) {
                Log::warning('techtyl tools: Einstellungen nicht lesbar', ['error' => substr($e->getMessage(), 0, 200)]);
            }
            self::$loaded = self::normalize(is_array($decoded) ? $decoded : []);
        }

        return self::$loaded;
    }

    /** @param array<string, mixed> $input */
    public static function save(array $input): void
    {
        $clean = self::normalize($input);
        app(BlueprintClientLibrary::class)->dbSet(self::TABLE, self::RECORD, json_encode($clean));
        self::$loaded = $clean;
    }

    public static function flush(): void
    {
        self::$loaded = null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{share_logs: bool}
     */
    public static function normalize(array $input): array
    {
        $value = $input['share_logs'] ?? true;

        return ['share_logs' => $value === true || $value === 1 || $value === '1'];
    }
}
