<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier};

/**
 * Grenzen, die der Admin den Nutzern setzt: was sie selbst aendern duerfen und wie gross
 * ein hochgeladenes Bild sein darf.
 */
class Limits
{
    public const RECORD = 'limits';

    public const MIN_UPLOAD_KB = 64;
    public const MAX_UPLOAD_KB = 10240;

    private const FLAGS = ['user_background', 'user_banners', 'user_appearance', 'user_layout'];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'user_background' => true,
            'user_banners' => true,
            'user_appearance' => true,
            'user_layout' => true,
            'max_upload_kb' => 2048,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function sanitize(array $input): array
    {
        $out = self::defaults();
        foreach (self::FLAGS as $flag) {
            if (array_key_exists($flag, $input)) {
                $out[$flag] = filter_var($input[$flag], FILTER_VALIDATE_BOOLEAN);
            }
        }
        if (is_numeric($input['max_upload_kb'] ?? null)) {
            $out['max_upload_kb'] = max(self::MIN_UPLOAD_KB, min(self::MAX_UPLOAD_KB, (int) $input['max_upload_kb']));
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public static function fromStored(mixed $stored): array
    {
        $decoded = is_string($stored) ? json_decode($stored, true) : null;

        return self::sanitize(is_array($decoded) ? $decoded : []);
    }

    /**
     * Freigaben in der Form, die Design::effective() und die API verwenden.
     *
     * @param array<string, mixed> $limits
     * @return array<string, bool>
     */
    public static function permissions(array $limits): array
    {
        return [
            'appearance' => (bool) $limits['user_appearance'],
            'layout' => (bool) $limits['user_layout'],
            'background' => (bool) $limits['user_background'],
            'banners' => (bool) $limits['user_banners'],
        ];
    }
}
