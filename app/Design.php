<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier};

/**
 * Design-Einstellungen von Techtyl: Standardwerte, Pruefung und Ausgabe als CSS-Variablen.
 * Einzige Stelle, an der Werte aus der Datenbank in CSS uebersetzt werden.
 */
class Design
{
    public const TABLE = 'techtyl';
    public const RECORD = 'design';

    public const VARIANTS = ['werft', 'papier', 'hafen', 'kiefer'];
    public const NAVIGATION = ['sidebar', 'icons', 'top'];
    public const MOTION = ['aus', 'dezent', 'voll'];
    public const SERVER_LAYOUT = ['raster', 'liste', 'kompakt'];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'variant' => 'werft',
            'accent' => '#f0a33b',
            'radius' => 10,
            'navigation' => 'sidebar',
            'server_layout' => 'raster',
            'motion' => 'voll',
            'banners' => true,
            'banner_height' => 96,
            'background' => '',
            'dim' => 55,
            'opacity' => 100,
            'blur' => 0,
        ];
    }

    /**
     * Macht aus beliebiger Eingabe gueltige Einstellungen. Unbekannte Schluessel fallen weg,
     * ungueltige Werte werden durch den Standard ersetzt.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function sanitize(array $input): array
    {
        $d = self::defaults();
        $pick = static fn (string $key, array $allowed) => in_array($input[$key] ?? null, $allowed, true) ? $input[$key] : $d[$key];
        $range = static fn (string $key, int $min, int $max) => is_numeric($input[$key] ?? null)
            ? max($min, min($max, (int) $input[$key]))
            : $d[$key];

        $accent = is_string($input['accent'] ?? null) && preg_match('/^#[0-9a-fA-F]{6}$/', $input['accent'])
            ? strtolower($input['accent'])
            : $d['accent'];

        return [
            'variant' => $pick('variant', self::VARIANTS),
            'accent' => $accent,
            'radius' => $range('radius', 0, 22),
            'navigation' => $pick('navigation', self::NAVIGATION),
            'server_layout' => $pick('server_layout', self::SERVER_LAYOUT),
            'motion' => $pick('motion', self::MOTION),
            'banners' => filter_var($input['banners'] ?? $d['banners'], FILTER_VALIDATE_BOOLEAN),
            'banner_height' => $range('banner_height', 48, 160),
            'background' => self::backgroundUrl($input['background'] ?? ''),
            'dim' => $range('dim', 0, 90),
            'opacity' => $range('opacity', 35, 100),
            'blur' => $range('blur', 0, 24),
        ];
    }

    /** Erlaubt nur https-Adressen und Pfade auf dem eigenen Panel, ohne Zeichen, die aus url("...") ausbrechen. */
    public static function backgroundUrl(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = trim($value);
        if ($value === '' || strlen($value) > 500) {
            return '';
        }
        if (preg_match('/["\'()\\\\<>\s]/', $value)) {
            return '';
        }
        $local = str_starts_with($value, '/') && !str_starts_with($value, '//');
        $remote = str_starts_with($value, 'https://') && filter_var($value, FILTER_VALIDATE_URL) !== false;

        return ($local || $remote) ? $value : '';
    }

    /** @param string|null $stored JSON aus der Datenbank */
    public static function fromStored(?string $stored): array
    {
        $decoded = json_decode((string) $stored, true);

        return self::sanitize(is_array($decoded) ? $decoded : []);
    }

    /**
     * CSS-Variablen fuer den Wrapper. Alle Werte stammen aus sanitize() und sind damit
     * auf Hex-Farben, Zahlen und gepruefte Adressen beschraenkt.
     *
     * @param array<string, mixed> $design
     */
    public static function toCss(array $design): string
    {
        $design = self::sanitize($design);
        $background = $design['background'] === '' ? 'none' : 'url("' . $design['background'] . '")';

        $vars = [
            '--techtyl-accent' => $design['accent'],
            '--techtyl-radius' => $design['radius'] . 'px',
            '--techtyl-banner-height' => $design['banner_height'] . 'px',
            '--techtyl-background' => $background,
            '--techtyl-dim' => (string) ($design['dim'] / 100),
            '--techtyl-opacity' => $design['opacity'] . '%',
            '--techtyl-blur' => $design['blur'] . 'px',
        ];

        $css = '';
        foreach ($vars as $property => $value) {
            $css .= $property . ':' . $value . ';';
        }

        return ':root{' . $css . '}';
    }

    /**
     * Attribute fuer das html-Element, an denen das Stylesheet Variante und Anordnung erkennt.
     *
     * @param array<string, mixed> $design
     * @return array<string, string>
     */
    public static function attributes(array $design): array
    {
        $design = self::sanitize($design);

        return [
            'data-techtyl-variant' => $design['variant'],
            'data-techtyl-nav' => $design['navigation'],
            'data-techtyl-servers' => $design['server_layout'],
            'data-techtyl-motion' => $design['motion'],
            'data-techtyl-banners' => $design['banners'] ? 'an' : 'aus',
        ];
    }
}
