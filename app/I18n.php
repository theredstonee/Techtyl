<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier};

use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Client\BlueprintClientLibrary;

/**
 * Translations for Techtyl. English is the default, German is available.
 *
 * Every area keeps its own file lang/<area>.php that returns
 *   ['en' => ['key' => 'Text with :placeholder'], 'de' => ['key' => 'Text mit :placeholder']]
 * and is addressed as I18n::t('<area>.<key>', ['placeholder' => 'value']).
 * Missing German texts fall back to English, missing keys to the key itself.
 *
 * Language: the signed-in user's panel language if it is supported, otherwise the default
 * language the administrator chose (record "locale"), otherwise English.
 */
class I18n
{
    public const SUPPORTED = ['en', 'de'];
    public const DEFAULT = 'en';
    public const RECORD = 'locale';

    /** @var array<string, array<string, array<string, string>>> */
    private static array $loaded = [];

    private static ?string $locale = null;

    public static function locale(): string
    {
        if (self::$locale !== null) {
            return self::$locale;
        }

        // The panel stores "en" for every account by default, so only a non-default language counts
        // as the user's own choice; otherwise the administrator's default applies.
        $user = auth()->user();
        $userLanguage = $user !== null ? strtolower(substr((string) ($user->language ?? ''), 0, 2)) : '';
        if (in_array($userLanguage, self::SUPPORTED, true) && $userLanguage !== self::DEFAULT) {
            return self::$locale = $userLanguage;
        }

        return self::$locale = self::fallbackLocale();
    }

    /** Default language chosen by the administrator. */
    public static function fallbackLocale(): string
    {
        try {
            $stored = app(BlueprintClientLibrary::class)->dbGet(Design::TABLE, self::RECORD, self::DEFAULT);
        } catch (\Throwable $e) {
            return self::DEFAULT;
        }

        return in_array($stored, self::SUPPORTED, true) ? $stored : self::DEFAULT;
    }

    /** For tests and for rendering mails in the recipient's language. */
    public static function using(string $locale, callable $callback): mixed
    {
        $previous = self::$locale;
        self::$locale = in_array($locale, self::SUPPORTED, true) ? $locale : self::DEFAULT;
        try {
            return $callback();
        } finally {
            self::$locale = $previous;
        }
    }

    /** @param array<string, string|int|float> $replace */
    public static function t(string $key, array $replace = [], ?string $locale = null): string
    {
        $dot = strpos($key, '.');
        if ($dot === false) {
            return $key;
        }
        $area = substr($key, 0, $dot);
        $name = substr($key, $dot + 1);
        $texts = self::area($area);
        $locale ??= self::locale();

        $text = $texts[$locale][$name] ?? $texts[self::DEFAULT][$name] ?? $key;
        foreach ($replace as $placeholder => $value) {
            $text = str_replace(':' . $placeholder, (string) $value, $text);
        }

        return $text;
    }

    /** @return array<string, array<string, string>> */
    private static function area(string $area): array
    {
        if (!preg_match('/^[a-z][a-z0-9_-]*$/', $area)) {
            return [];
        }
        if (!isset(self::$loaded[$area])) {
            $file = base_path('app/BlueprintFramework/Extensions/{identifier}/lang/' . $area . '.php');
            $texts = is_file($file) ? require $file : [];
            self::$loaded[$area] = is_array($texts) ? $texts : [];
        }

        return self::$loaded[$area];
    }
}
