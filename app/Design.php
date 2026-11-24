<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier};

use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Designer\Clean;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Designer\Motifs;

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

    /**
     * Szenen, die die Zeichen-Engine versteht. Gespeichert wird zusaetzlich "auto" (Standard)
     * oder "none". "auto" steht nie im data-Attribut; resolveScene() loest es auf.
     */
    public const SCENES = ['contours', 'aurora', 'stars', 'forest', 'harbor', 'grid'];

    /** Szene je Variante, wenn "auto" gilt und kein eigenes Bild liegt. */
    private const VARIANT_SCENES = [
        'werft' => 'contours',
        'papier' => 'grid',
        'hafen' => 'harbor',
        'kiefer' => 'forest',
    ];

    public const LOGIN_TITLE_MAX = 60;
    public const LOGIN_SUBTITLE_MAX = 160;

    /** Wert von "motif" fuer "bewusst kein Bild" (sonst Schluessel eines mitgelieferten Motivs). */
    public const MOTIF_NONE = 'none';

    /**
     * Wert von "background" fuer "bewusst kein Bild". Leer ('') bedeutet: das mitgelieferte
     * Standardbild der jeweils aktiven Variante (siehe backgroundFor()).
     */
    public const BACKGROUND_NONE = 'none';

    /**
     * Welche Einstellungen ein Nutzer selbst setzen darf, gruppiert nach der Admin-Grenze,
     * die sie freigibt. Die Bildadresse (background) ist nie frei waehlbar: eigene Bilder
     * kommen nur ueber den Upload, mitgelieferte ueber den Motiv-Schluessel (motif).
     * Die Login-Werte (login_*) setzt nur der Admin. Die Szene (scene) gehoert zur Hintergrund-Grenze.
     */
    public const GROUPS = [
        'appearance' => ['variant', 'accent', 'radius', 'motion'],
        'layout' => ['navigation', 'server_layout', 'banners', 'banner_height'],
        'background' => ['dim', 'opacity', 'blur', 'motif', 'scene'],
    ];

    /** @return string[] */
    public static function userKeys(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }

    /** Gruppe (Admin-Grenze), zu der ein Schluessel gehoert, oder null. */
    public static function groupOf(string $key): ?string
    {
        foreach (self::GROUPS as $group => $keys) {
            if (in_array($key, $keys, true)) {
                return $group;
            }
        }

        return null;
    }

    /**
     * Laravel-Regeln je Schluessel (ohne required), fuer Teil-Eingaben der Client-API.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'variant' => ['string', 'in:' . implode(',', self::VARIANTS)],
            'accent' => ['string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'radius' => ['integer', 'min:0', 'max:22'],
            'navigation' => ['string', 'in:' . implode(',', self::NAVIGATION)],
            'server_layout' => ['string', 'in:' . implode(',', self::SERVER_LAYOUT)],
            'motion' => ['string', 'in:' . implode(',', self::MOTION)],
            'banners' => ['boolean'],
            'banner_height' => ['integer', 'min:48', 'max:160'],
            'dim' => ['integer', 'min:0', 'max:90'],
            'opacity' => ['integer', 'min:35', 'max:100'],
            'blur' => ['integer', 'min:0', 'max:24'],
            'motif' => ['string', 'max:40', 'regex:/^[a-z0-9][a-z0-9-]{0,39}$/'],
            'scene' => ['string', 'in:' . implode(',', self::sceneValues())],
        ];
    }

    /**
     * Regeln fuer den kompletten Admin-Standard aus dem Designer (alle Schluessel, auch die,
     * die nur der Admin setzt).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function adminRules(string $prefix): array
    {
        $rules = [];
        foreach (self::rules() as $key => $keyRules) {
            if ($key !== 'motif') {
                $rules[$prefix . $key] = array_merge(['required'], $keyRules);
            }
        }
        $rules[$prefix . 'background'] = ['nullable', 'string', 'max:500'];
        $rules[$prefix . 'login_title'] = ['nullable', 'string', 'max:' . (self::LOGIN_TITLE_MAX + 40)];
        $rules[$prefix . 'login_subtitle'] = ['nullable', 'string', 'max:' . (self::LOGIN_SUBTITLE_MAX + 40)];
        $rules[$prefix . 'login_image'] = ['nullable', 'string', 'max:500'];

        return $rules;
    }

    /**
     * Teil-Einstellungen eines Nutzers: nur erlaubte Schluessel, die in der Eingabe wirklich
     * vorkommen, mit geprueften Werten.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function partial(array $input): array
    {
        $clean = self::sanitize($input);
        $out = [];
        foreach (self::userKeys() as $key) {
            if (array_key_exists($key, $input) && $input[$key] !== null) {
                $out[$key] = $clean[$key];
            }
        }

        return $out;
    }

    /**
     * Wirksames Design: Admin-Standard, ueberlagert mit den Nutzerwerten der freigegebenen Gruppen
     * und (falls erlaubt) dem eigenen Hintergrundbild.
     *
     * @param array<string, mixed> $admin Admin-Standard
     * @param array<string, mixed> $own Teil-Einstellungen des Nutzers
     * @param array<string, bool> $allowed Gruppe => freigegeben
     * @return array<string, mixed>
     */
    public static function effective(array $admin, array $own, array $allowed, string $ownBackground = ''): array
    {
        $design = self::sanitize($admin);
        $own = self::partial($own);
        foreach (self::GROUPS as $group => $keys) {
            if (empty($allowed[$group])) {
                continue;
            }
            foreach ($keys as $key) {
                if (array_key_exists($key, $own)) {
                    $design[$key] = $own[$key];
                }
            }
        }
        // Bildwahl des Nutzers: eigener Upload vor mitgeliefertem Motiv vor "kein Bild".
        if (!empty($allowed['background'])) {
            if (self::backgroundUrl($ownBackground) !== '') {
                $design['background'] = self::backgroundUrl($ownBackground);
            } elseif (($own['motif'] ?? '') === self::MOTIF_NONE) {
                $design['background'] = self::BACKGROUND_NONE;
            } elseif (Motifs::exists($own['motif'] ?? null)) {
                $design['background'] = Motifs::url($own['motif']);
            }
        }

        return $design;
    }

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
            'motif' => '',
            'scene' => 'auto',
            'login_title' => '',
            'login_subtitle' => '',
            'login_image' => '',
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
            'background' => ($input['background'] ?? null) === self::BACKGROUND_NONE ? self::BACKGROUND_NONE : self::backgroundUrl($input['background'] ?? ''),
            'dim' => $range('dim', 0, 90),
            'opacity' => $range('opacity', 35, 100),
            'blur' => $range('blur', 0, 24),
            'motif' => ($input['motif'] ?? null) === self::MOTIF_NONE || Motifs::exists($input['motif'] ?? null) ? $input['motif'] : '',
            'scene' => in_array($input['scene'] ?? null, self::sceneValues(), true) ? $input['scene'] : $d['scene'],
            'login_title' => Clean::line($input['login_title'] ?? '', self::LOGIN_TITLE_MAX),
            'login_subtitle' => Clean::line($input['login_subtitle'] ?? '', self::LOGIN_SUBTITLE_MAX),
            'login_image' => self::backgroundUrl($input['login_image'] ?? ''),
        ];
    }

    /**
     * Tatsaechliche Bildadresse des Hintergrunds: '' bei "kein Bild", das Standardbild der Variante,
     * wenn nichts gewaehlt ist, sonst die gespeicherte Adresse.
     *
     * @param array<string, mixed> $design
     */
    public static function backgroundFor(array $design): string
    {
        $background = $design['background'] ?? '';
        if ($background === self::BACKGROUND_NONE) {
            return '';
        }
        if ($background === '' || !is_string($background)) {
            return Motifs::variantDefault($design['variant'] ?? self::defaults()['variant']);
        }

        return self::backgroundUrl($background);
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
        $resolved = self::backgroundFor($design);
        // Mit einer Szene ersetzt die Animation das Standardbild der Variante, statt darueber zu liegen.
        // Ausdruecklich gewaehlte Bilder bleiben stehen (dann ergibt "auto" ohnehin keine Szene).
        if (($design["background"] ?? "") === "" && self::resolveScene($design) !== "none") {
            $resolved = "";
        }
        $background = $resolved === '' ? 'none' : 'url("' . $resolved . '")';

        $vars = [
            '--techtyl-accent' => $design['accent'],
            '--techtyl-radius' => $design['radius'] . 'px',
            '--techtyl-banner-height' => $design['banner_height'] . 'px',
            '--techtyl-background' => $background,
            '--techtyl-dim' => (string) ($design['dim'] / 100),
            '--techtyl-opacity' => $design['opacity'] . '%',
            '--techtyl-blur' => $design['blur'] . 'px',
            '--techtyl-login-image' => $design['login_image'] === '' ? 'none' : 'url("' . $design['login_image'] . '")',
        ];
        // Login-Texte als CSS-Zeichenketten (jedes Sonderzeichen als Hex-Folge, siehe Clean::cssString).
        // Nur gesetzt, wenn vorhanden, damit das Stylesheet sonst auf den Panel-Namen zurueckfallen kann.
        if ($design['login_title'] !== '') {
            $vars['--techtyl-login-title'] = Clean::cssString($design['login_title']);
        }
        if ($design['login_subtitle'] !== '') {
            $vars['--techtyl-login-subtitle'] = Clean::cssString($design['login_subtitle']);
        }

        $css = '';
        foreach ($vars as $property => $value) {
            $css .= $property . ':' . $value . ';';
        }

        return ':root{' . $css . '}';
    }

    /** @return string[] auto, none und die sechs Szenen. */
    public static function sceneValues(): array
    {
        return array_merge(['auto', 'none'], self::SCENES);
    }

    /**
     * Szene fuer das html-Attribut. "auto" wird hier aufgeloest und nie ausgegeben:
     * ein eigenes Bild (Upload oder eine Adresse, die kein mitgeliefertes Motiv ist) ergibt
     * "none", sonst die Szene der Variante. Fehlt der Schluessel, gilt "auto" (sanitize).
     *
     * @param array<string, mixed> $design
     */
    public static function resolveScene(array $design): string
    {
        $design = self::sanitize($design);
        $scene = $design['scene'];
        if ($scene !== 'auto') {
            return $scene;
        }
        if (self::customBackground($design)) {
            return 'none';
        }

        return self::VARIANT_SCENES[$design['variant']] ?? 'contours';
    }

    /**
     * Hat jemand ausdruecklich ein Bild gewaehlt (eigener Upload, Adresse oder ein mitgeliefertes
     * Motiv)? Dann bleibt dieses Bild stehen und "auto" waehlt keine Szene. Leer ist das Standardbild
     * der Variante, "none" heisst ohne Bild; beides ist keine ausdrueckliche Bildwahl.
     *
     * @param array<string, mixed> $design bereits durch sanitize()
     */
    private static function customBackground(array $design): bool
    {
        $background = $design["background"] ?? "";

        return is_string($background) && $background !== "" && $background !== self::BACKGROUND_NONE;
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
            'data-techtyl-scene' => self::resolveScene($design),
            'data-techtyl-banners' => $design['banners'] ? 'an' : 'aus',
            'data-techtyl-logintext' => $design['login_title'] !== '' || $design['login_subtitle'] !== '' ? 'an' : 'aus',
            'data-techtyl-loginbild' => $design['login_image'] !== '' ? 'an' : 'aus',
        ];
    }
}
