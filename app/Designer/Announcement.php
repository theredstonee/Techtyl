<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Designer;

/**
 * Ankuendigung im Dashboard. Der Text ist schlichtes Markdown (fett, kursiv, Links); er wird hier
 * nur als Text gespeichert und im Browser von einer eigenen, strikt escapenden Umsetzung
 * dargestellt. HTML wird nie erzeugt oder durchgelassen.
 */
class Announcement
{
    public const RECORD = 'announcement';

    public const TEXT_MAX = 600;
    public const MAX_LINES = 6;

    public const KINDS = ['info', 'neu', 'erfolg', 'warnung', 'wartung'];
    public const ICONS = ['megafon', 'info', 'stern', 'rakete', 'werkzeug', 'warnung', 'herz', 'kalender', 'glocke', 'geschenk'];
    public const POSITIONS = ['karte', 'leiste', 'schwebend'];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'text' => '',
            'kind' => 'info',
            'icon' => 'megafon',
            'position' => 'karte',
            'dismissible' => true,
        ];
    }

    /** @return array<string, array<int, mixed>> Regeln mit Praefix (z. B. "announcement.") */
    public static function rules(string $prefix): array
    {
        return [
            $prefix . 'enabled' => ['sometimes', 'boolean'],
            $prefix . 'text' => ['sometimes', 'nullable', 'string', 'max:' . (self::TEXT_MAX + 100)],
            $prefix . 'kind' => ['sometimes', 'string', 'in:' . implode(',', self::KINDS)],
            $prefix . 'icon' => ['sometimes', 'string', 'in:' . implode(',', self::ICONS)],
            $prefix . 'position' => ['sometimes', 'string', 'in:' . implode(',', self::POSITIONS)],
            $prefix . 'dismissible' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function sanitize(array $input): array
    {
        $d = self::defaults();
        $pick = static fn (string $key, array $allowed) => in_array($input[$key] ?? null, $allowed, true) ? $input[$key] : $d[$key];

        return [
            'enabled' => filter_var($input['enabled'] ?? $d['enabled'], FILTER_VALIDATE_BOOLEAN),
            'text' => Clean::block($input['text'] ?? '', self::TEXT_MAX, self::MAX_LINES),
            'kind' => $pick('kind', self::KINDS),
            'icon' => $pick('icon', self::ICONS),
            'position' => $pick('position', self::POSITIONS),
            'dismissible' => filter_var($input['dismissible'] ?? $d['dismissible'], FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /** @return array<string, mixed> */
    public static function fromStored(mixed $stored): array
    {
        $decoded = is_string($stored) ? json_decode($stored, true) : null;

        return self::sanitize(is_array($decoded) ? $decoded : []);
    }

    /**
     * Fuer das Dashboard: null, wenn nichts angezeigt werden soll. "revision" aendert sich mit jedem
     * Inhalt, damit eine weggeklickte Ankuendigung nach einer Aenderung wieder erscheint.
     *
     * @param array<string, mixed> $announcement
     * @return array<string, mixed>|null
     */
    public static function forClient(array $announcement): ?array
    {
        $announcement = self::sanitize($announcement);
        if (!$announcement['enabled'] || $announcement['text'] === '') {
            return null;
        }
        unset($announcement['enabled']);
        $announcement['revision'] = substr(hash('sha256', json_encode($announcement)), 0, 16);

        return $announcement;
    }
}
