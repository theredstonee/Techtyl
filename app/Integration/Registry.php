<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Integration;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Design;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Client\BlueprintClientLibrary;

/**
 * Reads optional techtyl.json files from other Blueprint extensions and the
 * admin switches that hide those pages from the Techtyl navigation.
 *
 * A file lives at .blueprint/extensions/<extension>/private/techtyl.json
 * (the extension's data directory after Blueprint builds it). Paths are
 * discovered with glob. Nothing in the path comes from a request.
 *
 * Invalid files are ignored. A notice is written to the log. Callers get
 * empty data instead of an error.
 */
class Registry
{
    public const RECORD = 'nav_hidden';

    private const CACHE_KEY = 'techtyl:integrations';

    private const CACHE_SECONDS = 300;

    private const MAX_BYTES = 32768;

    private const MAX_ENTRIES = 40;

    private const MAX_LABEL = 40;

    /** @var array<string, true> Kebab-case names. Keep in sync with dashboard/components/theme/icons.ts. */
    private const ICONS = [
        'address-book' => true,
        'address-card' => true,
        'archive' => true,
        'arrow-right' => true,
        'balance-scale' => true,
        'ban' => true,
        'bell' => true,
        'bolt' => true,
        'book' => true,
        'box-open' => true,
        'briefcase' => true,
        'bug' => true,
        'calendar-alt' => true,
        'chart-bar' => true,
        'check-circle' => true,
        'clipboard-list' => true,
        'clock' => true,
        'cloud' => true,
        'code' => true,
        'code-branch' => true,
        'cog' => true,
        'cogs' => true,
        'comment' => true,
        'comments' => true,
        'credit-card' => true,
        'cube' => true,
        'database' => true,
        'desktop' => true,
        'download' => true,
        'envelope' => true,
        'exclamation-triangle' => true,
        'eye' => true,
        'file-alt' => true,
        'filter' => true,
        'folder' => true,
        'folder-open' => true,
        'gamepad' => true,
        'globe' => true,
        'globe-europe' => true,
        'hammer' => true,
        'heart' => true,
        'history' => true,
        'home' => true,
        'id-card' => true,
        'image' => true,
        'info-circle' => true,
        'key' => true,
        'link' => true,
        'list' => true,
        'lock' => true,
        'magic' => true,
        'map' => true,
        'network-wired' => true,
        'paint-brush' => true,
        'palette' => true,
        'play' => true,
        'plug' => true,
        'puzzle-piece' => true,
        'robot' => true,
        'rocket' => true,
        'save' => true,
        'search' => true,
        'server' => true,
        'shield-alt' => true,
        'shopping-cart' => true,
        'sliders-h' => true,
        'store' => true,
        'tasks' => true,
        'terminal' => true,
        'th-large' => true,
        'ticket-alt' => true,
        'tools' => true,
        'trash' => true,
        'upload' => true,
        'user' => true,
        'user-circle' => true,
        'user-cog' => true,
        'user-friends' => true,
        'user-shield' => true,
        'users' => true,
        'wallet' => true,
        'wrench' => true,
    ];

    /** @var array<string, true> */
    private const GROUPS = [
        'manage' => true,
        'configure' => true,
        'tools' => true,
        'extensions' => true,
    ];

    /**
     * Normalised integration maps for the client bootstrap.
     * Empty maps are objects so JSON encodes {} rather than [].
     *
     * @return array{server: \stdClass, account: \stdClass}
     */
    public static function forClient(): array
    {
        $data = self::cached();

        return [
            'server' => self::exportSide(is_array($data['server'] ?? null) ? $data['server'] : []),
            'account' => self::exportSide(is_array($data['account'] ?? null) ? $data['account'] : []),
        ];
    }

    /**
     * Pages the administrator turned off, plus whole extensions that have
     * no integration file and were turned off. Default is nothing hidden.
     *
     * @return array{extensions: list<string>, pages: list<string>}
     */
    public static function hidden(): array
    {
        $empty = ['extensions' => [], 'pages' => []];
        try {
            $stored = app(BlueprintClientLibrary::class)->dbGet(Design::TABLE, self::RECORD, '{}');
        } catch (\Throwable $e) {
            return $empty;
        }

        if (is_array($stored)) {
            $decoded = $stored;
        } elseif (is_string($stored)) {
            $decoded = json_decode($stored, true);
        } else {
            return $empty;
        }
        if (!is_array($decoded)) {
            return $empty;
        }

        $extensions = [];
        foreach (is_array($decoded['extensions'] ?? null) ? $decoded['extensions'] : [] as $id) {
            if (count($extensions) >= 200) {
                break;
            }
            if (is_string($id) && self::isIdentifier($id) && !self::isOwn($id)) {
                $extensions[$id] = true;
            }
        }

        $pages = [];
        foreach (is_array($decoded['pages'] ?? null) ? $decoded['pages'] : [] as $key) {
            if (count($pages) >= 500) {
                break;
            }
            if (!is_string($key) || strlen($key) > 180 || !self::isPageKey($key)) {
                continue;
            }
            $pages[$key] = true;
        }

        return [
            'extensions' => array_keys($extensions),
            'pages' => array_keys($pages),
        ];
    }

    /**
     * Rows for the admin box. ok is false only when the list could not be built.
     *
     * @return array{ok: bool, rows: list<array{key: string, identifier: string, side: string, path: string, name: string}>}
     */
    public static function catalog(): array
    {
        try {
            return self::buildCatalog();
        } catch (\Throwable $e) {
            self::notice('*', 'catalog');

            return ['ok' => false, 'rows' => []];
        }
    }

    /**
     * Updates nav_hidden from the admin form.
     * $present is every row the form showed. $visible is the checked ones.
     * Rows that were not on the form keep their previous state.
     *
     * @param list<mixed> $present
     * @param list<mixed> $visible
     * @return 'saved'|'unavailable'|'invalid'
     */
    public static function saveVisible(array $present, array $visible): string
    {
        if (count($present) > 500 || count($visible) > 500) {
            return 'invalid';
        }

        $catalog = self::catalog();
        if (!$catalog['ok']) {
            return 'unavailable';
        }

        $allowed = [];
        $catalogExtensions = [];
        $catalogPages = [];
        foreach ($catalog['rows'] as $row) {
            $key = $row['key'];
            $allowed[$key] = true;
            if (str_starts_with($key, 'ext:')) {
                $catalogExtensions[substr($key, 4)] = true;
            } else {
                $catalogPages[$key] = true;
            }
        }

        $presentKeys = [];
        foreach ($present as $key) {
            if (is_string($key) && strlen($key) <= 180 && isset($allowed[$key])) {
                $presentKeys[$key] = true;
            }
        }
        if ($presentKeys === []) {
            return 'saved';
        }

        $show = [];
        foreach ($visible as $key) {
            if (is_string($key) && isset($presentKeys[$key])) {
                $show[$key] = true;
            }
        }

        $current = self::hidden();
        $hiddenExtensions = array_fill_keys($current['extensions'], true);
        $hiddenPages = array_fill_keys($current['pages'], true);
        foreach (array_keys($presentKeys) as $key) {
            if (str_starts_with($key, 'ext:')) {
                $id = substr($key, 4);
                if (isset($show[$key])) {
                    unset($hiddenExtensions[$id]);
                } else {
                    $hiddenExtensions[$id] = true;
                }
            } elseif (isset($show[$key])) {
                unset($hiddenPages[$key]);
            } else {
                $hiddenPages[$key] = true;
            }
        }

        foreach (array_keys($hiddenExtensions) as $id) {
            if (!isset($catalogExtensions[$id])) {
                unset($hiddenExtensions[$id]);
            }
        }
        foreach (array_keys($hiddenPages) as $key) {
            if (!isset($catalogPages[$key])) {
                unset($hiddenPages[$key]);
            }
        }

        try {
            $payload = json_encode([
                'extensions' => array_values(array_keys($hiddenExtensions)),
                'pages' => array_values(array_keys($hiddenPages)),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            app(BlueprintClientLibrary::class)->dbSet(Design::TABLE, self::RECORD, $payload);
        } catch (\Throwable $e) {
            self::notice('*', 'store');

            return 'unavailable';
        }

        return 'saved';
    }

    /**
     * @return array{ok: bool, rows: list<array{key: string, identifier: string, side: string, path: string, name: string}>}
     */
    private static function buildCatalog(): array
    {
        $data = self::cached();
        $pages = is_array($data['pages'] ?? null) ? $data['pages'] : [];
        $withPages = [];
        $rows = [];

        foreach ($pages as $page) {
            if (!is_array($page)) {
                continue;
            }
            $id = $page['identifier'] ?? '';
            $side = $page['side'] ?? '';
            $path = $page['path'] ?? '';
            if (!is_string($id) || !is_string($side) || !is_string($path)) {
                continue;
            }
            if (!self::isIdentifier($id) || self::isOwn($id) || ($side !== 'server' && $side !== 'account') || !self::isPath($path)) {
                continue;
            }
            $withPages[$id] = true;
            $label = is_array($page['label'] ?? null) ? $page['label'] : [];
            $rows[] = [
                'key' => $side . ':' . $id . ':' . $path,
                'identifier' => $id,
                'side' => $side,
                'path' => $path,
                'name' => self::displayName($label, $path),
            ];
        }

        foreach (self::extensionIds() as $id) {
            if (isset($withPages[$id])) {
                continue;
            }
            $rows[] = [
                'key' => 'ext:' . $id,
                'identifier' => $id,
                'side' => '',
                'path' => '',
                'name' => '',
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return [$a['identifier'], $a['side'], $a['path']] <=> [$b['identifier'], $b['side'], $b['path']];
        });

        return ['ok' => true, 'rows' => $rows];
    }

    /** @return array{server: array<string, array<string, mixed>>, account: array<string, array<string, mixed>>, pages: list<array<string, mixed>>} */
    private static function cached(): array
    {
        try {
            $data = Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, static fn () => self::scan());
        } catch (\Throwable $e) {
            self::notice('*', 'cache');

            return self::emptyScan();
        }

        return is_array($data) ? $data : self::emptyScan();
    }

    /** @return array{server: array<string, array<string, mixed>>, account: array<string, array<string, mixed>>, pages: list<array<string, mixed>>} */
    private static function scan(): array
    {
        $result = self::emptyScan();
        try {
            $paths = glob(base_path('.blueprint/extensions/*/private/techtyl.json'));
            if (!is_array($paths)) {
                return $result;
            }
            $found = [];
            foreach ($paths as $file) {
                if (!is_string($file)) {
                    continue;
                }
                $id = basename(dirname(dirname($file)));
                if (!self::isIdentifier($id) || self::isOwn($id)) {
                    continue;
                }
                $found[$id] = $file;
            }
            ksort($found, SORT_STRING);
            if (count($found) > 200) {
                $found = array_slice($found, 0, 200, true);
            }
            foreach ($found as $id => $file) {
                self::readExtension($id, $file, $result);
            }
        } catch (\Throwable $e) {
            self::notice('*', 'scan');

            return self::emptyScan();
        }

        return $result;
    }

    /** @param array{server: array<string, array<string, mixed>>, account: array<string, array<string, mixed>>, pages: list<array<string, mixed>>} $result */
    private static function readExtension(string $id, string $path, array &$result): void
    {
        if (!is_file($path)) {
            self::notice($id, 'read');

            return;
        }
        if (!self::insideExtensions($path)) {
            self::notice($id, 'escape');

            return;
        }
        $size = filesize($path);
        if ($size === false || $size >= self::MAX_BYTES) {
            self::notice($id, 'size');

            return;
        }
        $raw = file_get_contents($path);
        if (!is_string($raw) || strlen($raw) >= self::MAX_BYTES) {
            self::notice($id, 'read');

            return;
        }
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $decoded = json_decode($raw, true, 8);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE || ($decoded !== [] && array_is_list($decoded))) {
            self::notice($id, 'json');

            return;
        }
        if (array_key_exists('version', $decoded) && $decoded['version'] !== 1) {
            self::notice($id, 'version');

            return;
        }

        $server = self::sideMap($decoded['server'] ?? null, $id);
        $account = self::sideMap($decoded['account'] ?? null, $id);
        if (count($server) + count($account) > self::MAX_ENTRIES) {
            self::notice($id, 'count');

            return;
        }

        $dropped = 0;
        foreach (['server' => $server, 'account' => $account] as $side => $entries) {
            foreach ($entries as $routePath => $entry) {
                if (!is_string($routePath) || !self::isPath($routePath) || !is_array($entry) || ($entry !== [] && array_is_list($entry))) {
                    $dropped++;
                    continue;
                }
                $stored = self::normaliseEntry($id, $side, $entry);
                if (!isset($result[$side][$routePath])) {
                    $result[$side][$routePath] = $stored['merged'];
                }
                $result['pages'][] = [
                    'identifier' => $id,
                    'side' => $side,
                    'path' => $routePath,
                    'label' => $stored['label'],
                ];
            }
        }
        if ($dropped > 0) {
            self::notice($id, 'entries');
        }
    }

    /**
     * @param array<string, mixed> $entry
     * @return array{merged: array<string, mixed>, label: array<string, string>}
     */
    private static function normaliseEntry(string $id, string $side, array $entry): array
    {
        $label = [];
        $rawLabel = $entry['label'] ?? null;
        if (is_array($rawLabel) && ($rawLabel === [] || !array_is_list($rawLabel))) {
            foreach (['en', 'de'] as $code) {
                if (!array_key_exists($code, $rawLabel)) {
                    continue;
                }
                $text = self::plain($rawLabel[$code]);
                if ($text !== '') {
                    $label[$code] = $text;
                }
            }
        }

        $merged = [
            'order' => self::orderOf($entry['order'] ?? null),
            'label' => $label,
            'extension' => $id,
        ];
        if ($side === 'server') {
            $merged['group'] = self::groupOf($entry['group'] ?? null);
        }
        $icon = self::iconOf($entry['icon'] ?? null);
        if ($icon !== '') {
            $merged['icon'] = $icon;
        }

        return ['merged' => $merged, 'label' => $label];
    }

    /** @param array<string, array<string, mixed>> $entries */
    private static function exportSide(array $entries): \stdClass
    {
        $out = [];
        foreach ($entries as $path => $entry) {
            if (!is_string($path) || !self::isPath($path) || !is_array($entry)) {
                continue;
            }
            $extension = $entry['extension'] ?? '';
            if (!is_string($extension) || !self::isIdentifier($extension) || self::isOwn($extension)) {
                continue;
            }
            $label = [];
            if (is_array($entry['label'] ?? null)) {
                foreach (['en', 'de'] as $code) {
                    if (isset($entry['label'][$code]) && is_string($entry['label'][$code]) && $entry['label'][$code] !== '') {
                        $label[$code] = $entry['label'][$code];
                    }
                }
            }
            $row = [
                'order' => self::orderOf($entry['order'] ?? 100),
                'label' => (object) $label,
                'extension' => $extension,
            ];
            if (isset($entry['group']) && is_string($entry['group'])) {
                $row['group'] = self::groupOf($entry['group']);
            }
            if (isset($entry['icon']) && is_string($entry['icon']) && isset(self::ICONS[$entry['icon']])) {
                $row['icon'] = $entry['icon'];
            }
            $out[$path] = $row;
        }

        return (object) $out;
    }

    /** @return list<string> */
    private static function extensionIds(): array
    {
        $ids = [];
        $add = static function (string $id) use (&$ids): void {
            if (self::isIdentifier($id) && !self::isOwn($id)) {
                $ids[$id] = true;
            }
        };

        $file = base_path('.blueprint/extensions/blueprint/private/db/installed_extensions');
        if (is_file($file)) {
            $size = filesize($file);
            if ($size !== false && $size < 65536) {
                $raw = file_get_contents($file);
                if (is_string($raw)) {
                    foreach (explode(',', str_replace('|', '', $raw)) as $part) {
                        $add(trim($part));
                    }
                }
            }
        }

        $dirs = glob(base_path('.blueprint/extensions/*'), GLOB_ONLYDIR);
        if (is_array($dirs)) {
            foreach ($dirs as $dir) {
                if (is_string($dir)) {
                    $add(basename($dir));
                }
            }
        }

        ksort($ids, SORT_STRING);
        $keys = array_keys($ids);

        return count($keys) > 200 ? array_slice($keys, 0, 200) : $keys;
    }

    /** @param array<string, mixed> $label */
    private static function displayName(array $label, string $path): string
    {
        try {
            $locale = I18n::locale();
        } catch (\Throwable $e) {
            $locale = 'en';
        }
        $text = '';
        if (isset($label[$locale]) && is_string($label[$locale])) {
            $text = $label[$locale];
        } elseif (isset($label['en']) && is_string($label['en'])) {
            $text = $label['en'];
        }

        return $text !== '' ? $text : $path;
    }

    /** @return array{server: array<string, array<string, mixed>>, account: array<string, array<string, mixed>>, pages: list<array<string, mixed>>} */
    private static function emptyScan(): array
    {
        return ['server' => [], 'account' => [], 'pages' => []];
    }

    private static function sideMap(mixed $value, string $id): array
    {
        if ($value === null) {
            return [];
        }
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            self::notice($id, 'shape');

            return [];
        }

        return $value;
    }

    private static function insideExtensions(string $path): bool
    {
        $root = realpath(base_path('.blueprint/extensions'));
        $file = realpath($path);
        if ($root === false || $file === false) {
            return false;
        }
        $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($file, $prefix);
    }

    private static function isOwn(string $id): bool
    {
        return $id === 'techtyl' || $id === 'blueprint';
    }

    private static function isIdentifier(string $id): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $id) === 1;
    }

    private static function isPath(string $path): bool
    {
        return preg_match('#^/[A-Za-z0-9/_-]{0,64}$#', $path) === 1;
    }

    private static function isPageKey(string $key): bool
    {
        if (preg_match('#^(server|account):([a-z0-9][a-z0-9_-]{0,63}):(/[A-Za-z0-9/_-]{0,64})$#', $key, $match) !== 1) {
            return false;
        }

        return !self::isOwn($match[2]);
    }

    private static function groupOf(mixed $value): string
    {
        return is_string($value) && isset(self::GROUPS[$value]) ? $value : 'extensions';
    }

    private static function iconOf(mixed $value): string
    {
        return is_string($value) && isset(self::ICONS[$value]) ? $value : '';
    }

    private static function orderOf(mixed $value): int
    {
        $whole = is_int($value) || (is_float($value) && is_finite($value) && floor($value) === $value);
        if (!$whole) {
            return 100;
        }
        $order = (int) $value;

        return $order >= -10000 && $order <= 10000 ? $order : 100;
    }

    private static function plain(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $text = preg_replace('/[\x00-\x1F\x7F\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}<>]/u', '', $value);
        if (!is_string($text)) {
            return '';
        }
        $text = trim($text);
        if (mb_strlen($text, 'UTF-8') > self::MAX_LABEL) {
            $text = mb_substr($text, 0, self::MAX_LABEL, 'UTF-8');
        }

        return trim($text);
    }

    private static function notice(string $extension, string $reason): void
    {
        try {
            Log::notice('techtyl: ignored an extension integration file', [
                'extension' => $extension,
                'reason' => $reason,
            ]);
        } catch (\Throwable $e) {
            // A logging failure must not take the navigation or the admin page down.
        }
    }
}
