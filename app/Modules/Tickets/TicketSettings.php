<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tickets;

use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Design;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Client\BlueprintClientLibrary;

/** Admin-Einstellungen des Ticket-Moduls (ein JSON-Eintrag im Schluessel-Wert-Speicher der Extension). */
class TicketSettings
{
    public const RECORD = 'tickets_settings';

    public const DEFAULTS = [
        'notify' => true,
        'autoclose_days' => 7,
        'max_open' => 5,
        'attachments' => true,
    ];

    public const MAX_AUTOCLOSE_DAYS = 365;
    public const MAX_OPEN_LIMIT = 50;

    /** @var array<string, mixed>|null */
    private static ?array $loaded = null;

    /** @return array{notify: bool, autoclose_days: int, max_open: int, attachments: bool} */
    public static function get(): array
    {
        if (self::$loaded === null) {
            $decoded = null;
            try {
                $stored = app(BlueprintClientLibrary::class)->dbGet(Design::TABLE, self::RECORD, '{}');
                $decoded = is_string($stored) ? json_decode($stored, true) : null;
            } catch (\Throwable $e) {
                // Ohne lesbare Einstellungen gelten die Standardwerte.
            }
            self::$loaded = self::sanitize(is_array($decoded) ? $decoded : []);
        }

        return self::$loaded;
    }

    /** @param array<string, mixed> $input */
    public static function save(array $input): void
    {
        $values = self::sanitize($input);
        app(BlueprintClientLibrary::class)->dbSet(Design::TABLE, self::RECORD, json_encode($values));
        self::$loaded = $values;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{notify: bool, autoclose_days: int, max_open: int, attachments: bool}
     */
    public static function sanitize(array $input): array
    {
        $input += self::DEFAULTS;

        return [
            'notify' => filter_var($input['notify'], FILTER_VALIDATE_BOOLEAN),
            'autoclose_days' => max(0, min(self::MAX_AUTOCLOSE_DAYS, (int) $input['autoclose_days'])),
            'max_open' => max(1, min(self::MAX_OPEN_LIMIT, (int) $input['max_open'])),
            'attachments' => filter_var($input['attachments'], FILTER_VALIDATE_BOOLEAN),
        ];
    }
}
