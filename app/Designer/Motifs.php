<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Designer;

use Illuminate\Support\Facades\Log;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;

/**
 * Mitgelieferte Hintergrund-Motive aus public/defaults/. Quelle ist manifest.json; fehlt es oder
 * ist es unlesbar, werden die Bilddateien im Ordner backgrounds/ direkt angeboten. Jeder Eintrag
 * wird gegen ein festes Namensmuster und das Dateisystem geprueft, Adressen entstehen nur hier.
 */
class Motifs
{
    public const URL_PREFIX = '/extensions/{identifier}/defaults/';

    private const FILE_PATTERN = '/^[a-z0-9-]+\.webp$/';
    private const KEY_PATTERN = '/^[a-z0-9][a-z0-9-]{0,39}$/';

    /** @var array<string, array{key: string, name: string, url: string, variant: ?string}>|null */
    private static ?array $backgrounds = null;

    /** @var array<string, string>|null Variante => Motiv-Schluessel */
    private static ?array $variantDefaults = null;

    private static function directory(): string
    {
        return public_path('extensions/{identifier}/defaults');
    }

    /** @return array<string, mixed>|null */
    private static function manifest(): ?array
    {
        $file = self::directory() . '/manifest.json';
        if (!is_file($file) || filesize($file) > 262144) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Hintergrund-Motive, Schluessel => [key, name, url]. Reihenfolge wie im Manifest.
     *
     * @return array<string, array{key: string, name: string, url: string}>
     */
    public static function backgrounds(): array
    {
        if (self::$backgrounds !== null) {
            return self::$backgrounds;
        }

        $found = [];
        try {
            $manifest = self::manifest();
            $entries = $manifest['backgrounds']['items'] ?? ($manifest['backgrounds'] ?? null);
            if (is_array($entries)) {
                foreach ($entries as $index => $entry) {
                    if (is_string($entry)) {
                        $entry = ['file' => $entry];
                    }
                    if (!is_array($entry)) {
                        continue;
                    }
                    $key = $entry['key'] ?? (is_string($index) ? $index : null);
                    self::add($found, $key, $entry['file'] ?? null, $entry['label'] ?? ($entry['name'] ?? null), $entry['variant'] ?? null);
                }
            }
            if ($found === [] && is_dir(self::directory() . '/backgrounds')) {
                foreach (scandir(self::directory() . '/backgrounds') ?: [] as $file) {
                    self::add($found, null, $file, null);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('techtyl: mitgelieferte Motive nicht lesbar', ['error' => $e->getMessage()]);
            $found = [];
        }

        return self::$backgrounds = $found;
    }

    /** @param array<string, array{key: string, name: string, url: string}> $found */
    private static function add(array &$found, mixed $key, mixed $file, mixed $name, mixed $variant = null): void
    {
        if (!is_string($file)) {
            return;
        }
        $file = basename(str_replace('\\', '/', $file));
        if (preg_match(self::FILE_PATTERN, $file) !== 1 || !is_file(self::directory() . '/backgrounds/' . $file)) {
            return;
        }
        $key = is_string($key) ? strtolower($key) : strtolower((string) preg_replace('/\.[a-z]+$/', '', $file));
        $key = (string) preg_replace('/[^a-z0-9-]+/', '-', $key);
        if (preg_match(self::KEY_PATTERN, $key) !== 1 || isset($found[$key]) || $key === 'none') {
            return;
        }
        $label = Clean::line($name, 40);
        if ($label === '') {
            $label = ucfirst(str_replace('-', ' ', $key));
        }
        $found[$key] = [
            'key' => $key,
            'name' => $label,
            'url' => self::URL_PREFIX . 'backgrounds/' . $file,
            'variant' => is_string($variant) && preg_match(self::KEY_PATTERN, $variant) === 1 ? $variant : null,
        ];
    }

    /**
     * Standard-Motiv einer Variante (manifest: backgrounds.variants, sonst das Motiv mit passender
     * "variant"). Leer, wenn es keins gibt.
     */
    public static function variantDefault(mixed $variant): string
    {
        if (!is_string($variant)) {
            return '';
        }
        if (self::$variantDefaults === null) {
            self::$variantDefaults = [];
            try {
                $map = self::manifest()['backgrounds']['variants'] ?? [];
                foreach (is_array($map) ? $map : [] as $name => $key) {
                    if (is_string($name) && is_string($key) && self::exists($key)) {
                        self::$variantDefaults[$name] = $key;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('techtyl: Varianten-Standardbilder nicht lesbar', ['error' => $e->getMessage()]);
            }
            foreach (self::backgrounds() as $key => $motif) {
                if ($motif['variant'] !== null && !isset(self::$variantDefaults[$motif['variant']])) {
                    self::$variantDefaults[$motif['variant']] = $key;
                }
            }
        }

        return isset(self::$variantDefaults[$variant]) ? self::url(self::$variantDefaults[$variant]) : '';
    }

    public static function exists(mixed $key): bool
    {
        return is_string($key) && isset(self::backgrounds()[$key]);
    }

    public static function url(mixed $key): string
    {
        return self::exists($key) ? self::backgrounds()[$key]['url'] : '';
    }

    /** Liste fuer Browser und Designer. @return array<int, array{key: string, name: string, url: string}> */
    public static function publicList(): array
    {
        $list = [];
        foreach (self::backgrounds() as $key => $motif) {
            // English (and German) names come from the dictionary; the manifest only has German ones.
            $translated = I18n::t('designer.motif_' . $key);
            if ($translated !== 'designer.motif_' . $key) {
                $motif['name'] = $translated;
            }
            $list[] = $motif;
        }

        return $list;
    }
}
