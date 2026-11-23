<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;

/**
 * Modrinth-API v2, auf das beschraenkt, was der Plugin-/Mod-Browser braucht. Alle Rueckgaben
 * sind bereits auf bekannte Felder reduziert; Bildadressen bleiben nur, wenn sie auf
 * cdn.modrinth.com liegen, Links nur mit http/https.
 */
final class Modrinth
{
    /** Translated category name; categories without a translation keep Modrinth's own name. */
    private static function categoryLabel(string $id): string
    {
        $key = 'tools.cat_' . str_replace('-', '_', $id);
        $label = I18n::t($key);

        return $label === $key ? ucfirst(str_replace('-', ' ', $id)) : $label;
    }

    public const ID_PATTERN = '/^[A-Za-z0-9]{6,12}$/';
    public const SORTS = ['relevance', 'downloads', 'follows', 'newest', 'updated'];
    public const PAGE_SIZE = 20;
    public const MAX_PAGE = 49;

    /** Dateinamen, die Techtyl auf den Server schreibt. */
    public const FILE_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9 ._+()\[\]-]{0,150}\.jar$/';

    public static function validId(mixed $id): bool
    {
        return is_string($id) && preg_match(self::ID_PATTERN, $id) === 1;
    }

    /**
     * @param string[] $loaders
     * @param string[] $categories bereits gegen categories() geprueft
     */
    public static function search(string $kind, array $loaders, ?string $gameVersion, string $query, array $categories, string $sort, int $page): array
    {
        $facets = [['project_type:' . ($kind === 'plugin' ? 'plugin' : 'mod')]];
        $facets[] = array_map(fn ($loader) => 'categories:' . $loader, $loaders);
        if ($gameVersion !== null) {
            $facets[] = ['versions:' . $gameVersion];
        }
        if ($kind === 'mod') {
            $facets[] = ['server_side:required', 'server_side:optional'];
        }
        foreach ($categories as $category) {
            $facets[] = ['categories:' . $category];
        }

        $data = ExternalHttp::get('modrinth', '/v2/search', [
            'query' => $query,
            'facets' => json_encode($facets),
            'index' => in_array($sort, self::SORTS, true) ? $sort : 'relevance',
            'limit' => self::PAGE_SIZE,
            'offset' => max(0, min(self::MAX_PAGE, $page)) * self::PAGE_SIZE,
        ], 300);

        $hits = [];
        foreach (is_array($data['hits'] ?? null) ? $data['hits'] : [] as $hit) {
            if (!is_array($hit) || !self::validId($hit['project_id'] ?? null)) {
                continue;
            }
            $hits[] = [
                'project_id' => $hit['project_id'],
                'slug' => self::text($hit['slug'] ?? '', 80),
                'title' => self::text($hit['title'] ?? '', 160),
                'description' => self::text($hit['description'] ?? '', 400),
                'author' => self::text($hit['author'] ?? '', 60),
                'icon_url' => ExternalHttp::imageUrl($hit['icon_url'] ?? null),
                'downloads' => (int) ($hit['downloads'] ?? 0),
                'follows' => (int) ($hit['follows'] ?? 0),
                'categories' => self::strings($hit['display_categories'] ?? $hit['categories'] ?? [], 6),
                'updated_at' => self::text($hit['date_modified'] ?? '', 40),
            ];
        }
        $total = (int) ($data['total_hits'] ?? 0);

        return [
            'hits' => $hits,
            'total' => $total,
            'page' => $page,
            'pages' => (int) min(self::MAX_PAGE + 1, max(1, ceil($total / self::PAGE_SIZE))),
        ];
    }

    /** @return array<int, array{id: string, label: string}> Kategorien fuer die Art (plugin oder mod). */
    public static function categories(string $kind): array
    {
        $list = [];
        foreach (ExternalHttp::get('modrinth', '/v2/tag/category', [], 86400) as $entry) {
            // Plugins fuehrt Modrinth unter denselben Kategorien wie Mods.
            if (is_array($entry) && ($entry['project_type'] ?? '') === 'mod' && ($entry['header'] ?? '') === 'categories'
                && is_string($entry['name'] ?? null) && preg_match('/^[a-z0-9-]{2,30}$/', $entry['name'])) {
                $list[$entry['name']] = ['id' => $entry['name'], 'label' => self::categoryLabel($entry['name'])];
            }
        }
        usort($list, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return array_values($list);
    }

    public static function project(string $id): array
    {
        return self::mapProject(ExternalHttp::get('modrinth', '/v2/project/' . rawurlencode($id), [], 600), true);
    }

    /**
     * @param string[] $ids
     * @return array<string, array> Projekt-ID => Kurzdaten
     */
    public static function projects(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, [self::class, 'validId'])));
        if ($ids === []) {
            return [];
        }
        sort($ids);
        $list = [];
        foreach (ExternalHttp::get('modrinth', '/v2/projects', ['ids' => json_encode($ids)], 600) as $entry) {
            if (is_array($entry) && self::validId($entry['id'] ?? null)) {
                $list[$entry['id']] = self::mapProject($entry, false);
            }
        }

        return $list;
    }

    /**
     * Versionen eines Projekts, neueste zuerst, gefiltert nach Loader und (optional) Spielversion.
     *
     * @param string[] $loaders
     */
    public static function versions(string $projectId, array $loaders, ?string $gameVersion): array
    {
        $query = ['loaders' => json_encode(array_values($loaders))];
        if ($gameVersion !== null) {
            $query['game_versions'] = json_encode([$gameVersion]);
        }
        $list = [];
        foreach (ExternalHttp::get('modrinth', '/v2/project/' . rawurlencode($projectId) . '/version', $query, 300) as $entry) {
            $mapped = is_array($entry) ? self::mapVersion($entry) : null;
            if ($mapped !== null) {
                $list[] = $mapped;
            }
        }

        return array_slice($list, 0, 100);
    }

    public static function version(string $id): array
    {
        $mapped = self::mapVersion(ExternalHttp::get('modrinth', '/v2/version/' . rawurlencode($id), [], 600));
        if ($mapped === null) {
            throw ToolsException::notFound('version_no_file');
        }

        return $mapped;
    }

    /**
     * Neueste passende Version je installierter Datei (ein Aufruf fuer alle).
     *
     * @param string[] $hashes sha1 der installierten Dateien
     * @param string[] $loaders
     * @return array<string, array> sha1 => Version
     */
    public static function updates(array $hashes, array $loaders, string $gameVersion): array
    {
        $hashes = array_values(array_unique(array_filter($hashes, fn ($h) => is_string($h) && preg_match('/^[a-f0-9]{40}$/', $h))));
        if ($hashes === []) {
            return [];
        }
        sort($hashes);
        $data = ExternalHttp::post('modrinth', '/v2/version_files/update', [
            'hashes' => $hashes,
            'algorithm' => 'sha1',
            'loaders' => array_values($loaders),
            'game_versions' => [$gameVersion],
        ], 900);

        $list = [];
        foreach ($data as $hash => $entry) {
            $mapped = is_array($entry) ? self::mapVersion($entry) : null;
            if ($mapped !== null && is_string($hash) && in_array($hash, $hashes, true)) {
                $list[$hash] = $mapped;
            }
        }

        return $list;
    }

    /** Reduziert eine Modrinth-Version auf die Hauptdatei und die Felder, die wir brauchen. */
    private static function mapVersion(array $entry): ?array
    {
        if (!self::validId($entry['id'] ?? null) || !self::validId($entry['project_id'] ?? null)) {
            return null;
        }
        $file = null;
        foreach (is_array($entry['files'] ?? null) ? $entry['files'] : [] as $candidate) {
            if (!is_array($candidate) || !is_string($candidate['filename'] ?? null) || !is_string($candidate['url'] ?? null)) {
                continue;
            }
            if ($file === null || ($candidate['primary'] ?? false) === true) {
                $file = $candidate;
                if (($candidate['primary'] ?? false) === true) {
                    break;
                }
            }
        }
        $sha1 = $file['hashes']['sha1'] ?? null;
        if ($file === null || !is_string($sha1) || !preg_match('/^[a-f0-9]{40}$/', $sha1) || (int) ($file['size'] ?? 0) <= 0) {
            return null;
        }

        $required = [];
        foreach (is_array($entry['dependencies'] ?? null) ? $entry['dependencies'] : [] as $dependency) {
            if (is_array($dependency) && ($dependency['dependency_type'] ?? '') === 'required') {
                $projectId = self::validId($dependency['project_id'] ?? null) ? $dependency['project_id'] : null;
                $versionId = self::validId($dependency['version_id'] ?? null) ? $dependency['version_id'] : null;
                if ($projectId !== null || $versionId !== null) {
                    $required[] = ['project_id' => $projectId, 'version_id' => $versionId];
                }
            }
        }

        return [
            'id' => $entry['id'],
            'project_id' => $entry['project_id'],
            'title' => self::text($entry['name'] ?? '', 120),
            'number' => self::text($entry['version_number'] ?? '', 80),
            'channel' => in_array($entry['version_type'] ?? '', ['release', 'beta', 'alpha'], true) ? $entry['version_type'] : 'release',
            'game_versions' => self::strings($entry['game_versions'] ?? [], 400),
            'loaders' => self::strings($entry['loaders'] ?? [], 20),
            'published_at' => self::text($entry['date_published'] ?? '', 40),
            'downloads' => (int) ($entry['downloads'] ?? 0),
            'required' => array_slice($required, 0, 20),
            'file' => [
                'filename' => $file['filename'],
                'url' => $file['url'],
                'size' => (int) $file['size'],
                'sha1' => $sha1,
            ],
        ];
    }

    private static function mapProject(array $entry, bool $full): array
    {
        $id = $entry['id'] ?? $entry['project_id'] ?? null;
        if (!self::validId($id)) {
            throw ToolsException::notFound('project_missing');
        }
        $project = [
            'project_id' => $id,
            'slug' => self::text($entry['slug'] ?? '', 80),
            'title' => self::text($entry['title'] ?? '', 160),
            'description' => self::text($entry['description'] ?? '', 400),
            'icon_url' => ExternalHttp::imageUrl($entry['icon_url'] ?? null),
            'downloads' => (int) ($entry['downloads'] ?? 0),
            'follows' => (int) ($entry['followers'] ?? 0),
            'categories' => self::strings($entry['categories'] ?? [], 8),
            'loaders' => self::strings($entry['loaders'] ?? [], 20),
            'type' => self::text($entry['project_type'] ?? '', 20),
        ];
        if (!$full) {
            return $project;
        }

        $links = [];
        foreach (['source_url' => 'link_source', 'issues_url' => 'link_issues', 'wiki_url' => 'link_wiki', 'discord_url' => 'link_discord'] as $key => $labelKey) {
            $url = $entry[$key] ?? null;
            if (is_string($url) && strlen($url) <= 300 && preg_match('#^https?://[^\s<>"\']+$#i', $url)) {
                $links[] = ['label' => I18n::t('tools.' . $labelKey), 'url' => $url];
            }
        }
        $gallery = [];
        foreach (is_array($entry['gallery'] ?? null) ? $entry['gallery'] : [] as $image) {
            $url = is_array($image) ? ExternalHttp::imageUrl($image['url'] ?? null) : null;
            if ($url !== null) {
                $gallery[] = ['url' => $url, 'title' => self::text($image['title'] ?? '', 120)];
            }
        }

        return $project + [
            // Rohtext (Markdown/HTML von Modrinth). Das Frontend rendert ihn ausschliesslich als Text.
            'body' => mb_substr(is_string($entry['body'] ?? null) ? $entry['body'] : '', 0, 60000),
            'links' => $links,
            'gallery' => array_slice($gallery, 0, 6),
            'license' => self::text($entry['license']['id'] ?? '', 60),
            'updated_at' => self::text($entry['updated'] ?? '', 40),
            'page_url' => 'https://modrinth.com/project/' . $id,
        ];
    }

    private static function text(mixed $value, int $max): string
    {
        return is_string($value) ? mb_substr(preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', '', $value) ?? '', 0, $max) : '';
    }

    /** @return string[] */
    private static function strings(mixed $value, int $max): array
    {
        $list = [];
        foreach (is_array($value) ? $value : [] as $item) {
            if (is_string($item) && strlen($item) <= 60) {
                $list[] = $item;
            }
        }

        return array_slice($list, 0, $max);
    }
}
