<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Designer;

use Illuminate\Support\Facades\Log;

/**
 * Mitgelieferte Server-Banner aus public/defaults/manifest.json (Abschnitt "banners").
 * Zuordnung je Egg: exakter Egg-Name, dann die Stichwort-Regeln in ihrer Reihenfolge
 * ("contains" = Teilzeichenfolge, "word" = ganzes Wort), dann der Nest-Name, sonst der Standard.
 * Das Manifest wird je Anfrage einmal gelesen; Dateinamen nur nach dem Muster ^[a-z0-9-]+\.webp$.
 */
class DefaultBanners
{
    public const URL_PREFIX = '/extensions/{identifier}/defaults/banners/';
    public const DEFAULT_POSITION = '50% 30%';

    private const FILE_PATTERN = '/^[a-z0-9-]+\.webp$/';

    /** @var array{items: array<string, string>, eggs: array<string, string>, rules: array<int, array{0: string, 1: string, 2: string}>, nests: array<string, string>, default: string, position: string}|null */
    private static ?array $data = null;

    private static function load(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }
        $data = ['items' => [], 'eggs' => [], 'rules' => [], 'nests' => [], 'default' => '', 'position' => self::DEFAULT_POSITION];
        try {
            $directory = public_path('extensions/{identifier}/defaults');
            $file = $directory . '/manifest.json';
            $manifest = is_file($file) && filesize($file) <= 262144 ? json_decode((string) file_get_contents($file), true) : null;
            $banners = is_array($manifest['banners'] ?? null) ? $manifest['banners'] : [];

            foreach (is_array($banners['items'] ?? null) ? $banners['items'] : [] as $item) {
                $key = is_array($item) && is_string($item['key'] ?? null) ? $item['key'] : null;
                $name = is_array($item) && is_string($item['file'] ?? null) ? $item['file'] : null;
                if ($key !== null && $name !== null && preg_match(self::FILE_PATTERN, $name) === 1 && is_file($directory . '/banners/' . $name)) {
                    $data['items'][$key] = self::URL_PREFIX . $name;
                }
            }
            foreach (is_array($banners['eggs'] ?? null) ? $banners['eggs'] : [] as $egg => $key) {
                if (is_string($egg) && is_string($key) && isset($data['items'][$key])) {
                    $data['eggs'][$egg] = $key;
                }
            }
            foreach (is_array($banners['rules'] ?? null) ? $banners['rules'] : [] as $rule) {
                if (!is_array($rule) || !is_string($rule['text'] ?? null) || !is_string($rule['banner'] ?? null) || !isset($data['items'][$rule['banner']])) {
                    continue;
                }
                $match = ($rule['match'] ?? 'contains') === 'word' ? 'word' : 'contains';
                $data['rules'][] = [$match, mb_strtolower($rule['text']), $rule['banner']];
            }
            foreach (is_array($banners['nests'] ?? null) ? $banners['nests'] : [] as $nest => $key) {
                if (is_string($nest) && is_string($key) && isset($data['items'][$key])) {
                    $data['nests'][$nest] = $key;
                }
            }
            $default = $banners['default'] ?? 'standard';
            $data['default'] = is_string($default) && isset($data['items'][$default]) ? $default : '';
            $position = $banners['position'] ?? null;
            if (is_string($position) && preg_match('/^\d{1,3}% \d{1,3}%$/', $position) === 1) {
                $data['position'] = $position;
            }
        } catch (\Throwable $e) {
            Log::warning('techtyl: mitgelieferte Banner nicht lesbar', ['error' => $e->getMessage()]);
        }

        return self::$data = $data;
    }

    /** Bildposition, die das Manifest fuer Banner empfiehlt (z. B. "50% 30%"). */
    public static function position(): string
    {
        return self::load()['position'];
    }

    /** Adresse des passenden mitgelieferten Banners; leer, wenn es keins gibt. */
    public static function urlFor(?string $eggName, ?string $nestName): string
    {
        $data = self::load();
        $key = null;
        if (is_string($eggName) && isset($data['eggs'][$eggName])) {
            $key = $data['eggs'][$eggName];
        }
        if ($key === null && is_string($eggName) && $eggName !== '') {
            $haystack = mb_strtolower($eggName);
            foreach ($data['rules'] as [$match, $text, $banner]) {
                $hit = $match === 'word'
                    ? preg_match('/(?<![\p{L}\p{N}])' . preg_quote($text, '/') . '(?![\p{L}\p{N}])/u', $haystack) === 1
                    : str_contains($haystack, $text);
                if ($hit) {
                    $key = $banner;
                    break;
                }
            }
        }
        if ($key === null && is_string($nestName) && isset($data['nests'][$nestName])) {
            $key = $data['nests'][$nestName];
        }
        $key ??= $data['default'];

        return $key !== '' ? ($data['items'][$key] ?? '') : '';
    }
}
