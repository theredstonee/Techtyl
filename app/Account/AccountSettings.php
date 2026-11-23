<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Account;

use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Client\BlueprintClientLibrary;

/**
 * Admin-Schalter des Kontobereichs, gespeichert als JSON unter dbGet/dbSet('techtyl', 'account_settings').
 *
 *   uploads    Nutzer duerfen Profilbilder hochladen (Standard: an)
 *   gravatar   Gravatar als Rueckfall, wenn kein eigenes Bild da ist (Standard: aus, Datenschutz:
 *              der Browser ruft dann gravatar.com mit einem Hash der E-Mail-Adresse auf)
 *   names      Nutzer duerfen Vor- und Nachnamen selbst aendern (Standard: aus, wie im Panel)
 */
class AccountSettings
{
    public const TABLE = '{identifier}';
    public const RECORD = 'account_settings';

    private const FLAGS = ['uploads', 'gravatar', 'names'];

    /** @var array<string, bool>|null */
    private static ?array $loaded = null;

    /** @return array<string, bool> */
    public static function defaults(): array
    {
        return ['uploads' => true, 'gravatar' => false, 'names' => false];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, bool>
     */
    public static function sanitize(array $input): array
    {
        $out = self::defaults();
        foreach (self::FLAGS as $flag) {
            if (array_key_exists($flag, $input)) {
                $out[$flag] = filter_var($input[$flag], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $out;
    }

    /** @return array<string, bool> */
    public static function get(): array
    {
        if (self::$loaded === null) {
            try {
                $stored = app(BlueprintClientLibrary::class)->dbGet(self::TABLE, self::RECORD, '{}');
                $decoded = is_string($stored) ? json_decode($stored, true) : null;
                self::$loaded = self::sanitize(is_array($decoded) ? $decoded : []);
            } catch (\Throwable $e) {
                self::$loaded = self::defaults();
            }
        }

        return self::$loaded;
    }

    /**
     * Speichert die Schalter (fuer die Admin-Ansicht). Unbekannte Schluessel werden verworfen.
     *
     * @param array<string, mixed> $input
     */
    public static function save(array $input): void
    {
        $merged = self::sanitize(array_merge(self::get(), $input));
        app(BlueprintClientLibrary::class)->dbSet(self::TABLE, self::RECORD, json_encode($merged));
        self::$loaded = null;
    }
}
