<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier};

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Client\BlueprintClientLibrary;

/**
 * Liest die Admin-Einstellungen (Design, Grenzen, Standardbilder) mit EINER Abfrage je
 * Seitenaufruf und berechnet daraus das wirksame Design eines Nutzers.
 */
class Settings
{
    public const MEDIA_RECORD = 'media';

    public const USER_TABLE = 'techtyl_user_settings';

    /** @var array<string, mixed>|null */
    private static ?array $loaded = null;

    /** @return array<string, mixed> */
    private static function load(): array
    {
        if (self::$loaded === null) {
            self::$loaded = app(BlueprintClientLibrary::class)
                ->dbGetMany(Design::TABLE, [Design::RECORD, Limits::RECORD, self::MEDIA_RECORD]);
        }

        return self::$loaded;
    }

    /** Nach dem Speichern aufrufen, damit derselbe Seitenaufruf die neuen Werte sieht. */
    public static function flush(): void
    {
        self::$loaded = null;
    }

    /** Admin-Standard des Designs. */
    public static function design(): array
    {
        $stored = self::load()[Design::RECORD] ?? null;

        return Design::fromStored(is_string($stored) ? $stored : null);
    }

    public static function limits(): array
    {
        return Limits::fromStored(self::load()[Limits::RECORD] ?? null);
    }

    /** @return array{background_file: string} Vom Admin hochgeladene Standardbilder. */
    public static function media(): array
    {
        $stored = self::load()[self::MEDIA_RECORD] ?? null;
        $decoded = is_string($stored) ? json_decode($stored, true) : null;
        $file = is_array($decoded) ? ($decoded['background_file'] ?? '') : '';

        return ['background_file' => Media::isName($file) ? $file : ''];
    }

    /**
     * Wirksames Design fuer einen Nutzer (oder der Admin-Standard, wenn niemand angemeldet ist).
     *
     * @return array{design: array<string, mixed>, own: array<string, mixed>, own_background: string, permissions: array<string, bool>, limits: array<string, mixed>}
     */
    public static function effectiveFor(?int $userId): array
    {
        $admin = self::design();
        $limits = self::limits();
        $permissions = Limits::permissions($limits);
        $own = [];
        $ownBackground = '';

        if ($userId !== null) {
            $row = DB::table(self::USER_TABLE)->where('user_id', $userId)->first(['design', 'background_file']);
            if ($row !== null) {
                $decoded = json_decode((string) $row->design, true);
                $own = Design::partial(is_array($decoded) ? $decoded : []);
                $ownBackground = Media::url($row->background_file);
            }
        }

        return [
            'design' => Design::effective($admin, $own, $permissions, $ownBackground),
            'own' => $own,
            'own_background' => $ownBackground,
            'permissions' => $permissions,
            'limits' => $limits,
        ];
    }

    /**
     * Fuer den Wrapper: darf nie eine Seite zerlegen. Fehlt z. B. die Tabelle (Migration noch
     * nicht gelaufen), gilt der Admin-Standard bzw. das eingebaute Standard-Design.
     *
     * @return array<string, mixed>
     */
    public static function designForPage(?int $userId): array
    {
        try {
            return self::effectiveFor($userId)['design'];
        } catch (\Throwable $e) {
            Log::warning('techtyl: wirksames Design nicht lesbar, Standard wird verwendet', ['error' => $e->getMessage()]);
        }
        try {
            return self::design();
        } catch (\Throwable $e) {
            return Design::defaults();
        }
    }
}
