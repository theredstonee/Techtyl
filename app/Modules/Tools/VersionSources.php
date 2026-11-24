<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Illuminate\Support\Facades\Log;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;

/**
 * Versionen, Builds und Download-Daten je Servertyp:
 *   paper, folia, velocity  -> PaperMC Fill v3
 *   purpur                  -> api.purpurmc.org v2
 *   vanilla                 -> Mojang piston-meta
 *   fabric                  -> meta.fabricmc.net (Build = Loader-Version)
 *   forge                   -> promotions_slim.json plus maven-metadata.xml
 *   neoforge                -> maven.neoforged.net metadata (not the legacy forge artifact)
 *
 * Kennungen aus Nutzereingaben gelten erst, wenn sie in der vom Dienst gelieferten Liste stehen.
 * Das Versionsschema ist bewusst nicht auf "1.x" festgelegt (es gibt 26.x und Vorabversionen).
 */
final class VersionSources
{
    /** Form einer Versions- oder Build-Kennung; die eigentliche Pruefung ist die Liste des Dienstes. */
    public const ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9.+_-]{0,39}$/';

    private const FILL = ['paper' => 'paper', 'folia' => 'folia', 'velocity' => 'velocity'];
    private const LIST_TTL = 600;
    private const MAX_BUILDS = 60;

    public static function validId(mixed $id): bool
    {
        return is_string($id) && preg_match(self::ID_PATTERN, $id) === 1;
    }

    public static function supports(string $type): bool
    {
        return in_array($type, ServerTypes::VERSION_SWITCH, true);
    }

    /**
     * @return array<int, array{id: string, stable: bool, group: string}> neueste zuerst
     */
    public static function versions(string $type): array
    {
        if (isset(self::FILL[$type])) {
            $data = ExternalHttp::get('fill', '/v3/projects/' . self::FILL[$type], [], self::LIST_TTL);
            $list = [];
            foreach (is_array($data['versions'] ?? null) ? $data['versions'] : [] as $group => $ids) {
                foreach (is_array($ids) ? $ids : [] as $id) {
                    if (self::validId($id)) {
                        $list[] = ['id' => $id, 'stable' => self::looksStable($id), 'group' => (string) $group];
                    }
                }
            }

            return $list;
        }

        if ($type === 'purpur') {
            $data = ExternalHttp::get('purpur', '/v2/purpur', [], self::LIST_TTL);
            $list = [];
            foreach (array_reverse(is_array($data['versions'] ?? null) ? $data['versions'] : []) as $id) {
                if (self::validId($id)) {
                    $list[] = ['id' => $id, 'stable' => true, 'group' => self::group($id)];
                }
            }

            return $list;
        }

        if ($type === 'vanilla') {
            $list = [];
            foreach (self::vanillaManifest() as $id => $entry) {
                if ($entry['type'] === 'release') {
                    $list[] = ['id' => (string) $id, 'stable' => true, 'group' => self::group((string) $id)];
                }
            }

            return $list;
        }

        if ($type === 'forge') {
            return self::forgeIndex()['games'];
        }

        if ($type === 'neoforge') {
            return self::neoIndex()['games'];
        }

        if ($type === 'fabric') {
            $data = ExternalHttp::get('fabric', '/v2/versions/game', [], self::LIST_TTL);
            $list = [];
            foreach ($data as $entry) {
                $id = is_array($entry) ? ($entry['version'] ?? null) : null;
                if (self::validId($id) && ($entry['stable'] ?? false) === true) {
                    $list[] = ['id' => $id, 'stable' => true, 'group' => self::group($id)];
                }
            }

            return $list;
        }

        throw ToolsException::invalid('no_switch');
    }

    public static function hasVersion(string $type, string $version): bool
    {
        foreach (self::versions($type) as $entry) {
            if ($entry['id'] === $version) {
                return true;
            }
        }

        return false;
    }

    /**
     * Builds einer Version, neueste zuerst.
     *
     * @return array<int, array{id: string, label: string, channel: string, time: ?string, size: ?int}>
     */
    public static function builds(string $type, string $version): array
    {
        if (!self::validId($version) || !self::hasVersion($type, $version)) {
            throw ToolsException::notFound('version_unknown');
        }

        if (isset(self::FILL[$type])) {
            $list = [];
            foreach (self::fillBuilds($type, $version) as $build) {
                $list[] = [
                    'id' => $build['id'],
                    'label' => I18n::t('tools.build_label', ['id' => $build['id']]),
                    'channel' => $build['channel'],
                    'time' => $build['time'],
                    'size' => $build['size'],
                ];
            }

            return array_slice($list, 0, self::MAX_BUILDS);
        }

        if ($type === 'purpur') {
            $data = ExternalHttp::get('purpur', '/v2/purpur/' . rawurlencode($version), [], self::LIST_TTL);
            $all = is_array($data['builds']['all'] ?? null) ? $data['builds']['all'] : [];
            $list = [];
            foreach (array_reverse($all) as $id) {
                if (is_string($id) && ctype_digit($id)) {
                    $list[] = ['id' => $id, 'label' => I18n::t('tools.build_label', ['id' => $id]), 'channel' => 'stable', 'time' => null, 'size' => null];
                }
            }

            return array_slice($list, 0, self::MAX_BUILDS);
        }

        if ($type === 'vanilla') {
            $entry = self::vanillaManifest()[$version];

            return [['id' => 'release', 'label' => I18n::t('tools.build_official'), 'channel' => 'stable', 'time' => $entry['time'], 'size' => null]];
        }

        if ($type === 'forge') {
            return self::forgeIndex()['builds'][$version] ?? [];
        }

        if ($type === 'neoforge') {
            return self::neoIndex()['builds'][$version] ?? [];
        }

        // Fabric: der "Build" ist die Loader-Version.
        $list = [];
        foreach (ExternalHttp::get('fabric', '/v2/versions/loader', [], self::LIST_TTL) as $entry) {
            $id = is_array($entry) ? ($entry['version'] ?? null) : null;
            if (self::validId($id)) {
                $stable = ($entry['stable'] ?? false) === true;
                $list[] = ['id' => $id, 'label' => I18n::t('tools.loader_label', ['id' => $id]), 'channel' => $stable ? 'stable' : 'beta', 'time' => null, 'size' => null];
            }
        }

        return array_slice($list, 0, self::MAX_BUILDS);
    }

    /**
     * Alles, was der Wechsel braucht. Die Dateien liefert ausschliesslich der Dienst; die
     * Adressen sind gegen die Download-Hosts geprueft.
     *
     * @return array{
     *   version: string, build: string, java: int,
     *   files: array<int, array{role: string, url: string, via: string, size: ?int, checksum: ?array{0: string, 1: string}}>,
     *   extra: array<string, string>
     * }
     */
    public static function resolve(string $type, string $version, string $build): array
    {
        if (!self::validId($build)) {
            throw ToolsException::invalid('build_invalid');
        }
        $known = null;
        foreach (self::builds($type, $version) as $entry) {
            if ($entry['id'] === $build) {
                $known = $entry;
                break;
            }
        }
        if ($known === null) {
            throw ToolsException::notFound('build_unknown');
        }

        if (isset(self::FILL[$type])) {
            foreach (self::fillBuilds($type, $version) as $entry) {
                if ($entry['id'] === $build) {
                    return [
                        'version' => $version,
                        'build' => $build,
                        'java' => self::fillJava($type, $version),
                        'files' => [[
                            'role' => 'server',
                            'url' => ExternalHttp::downloadUrl($entry['url']),
                            'via' => 'pull',
                            'size' => $entry['size'],
                            'checksum' => $entry['sha256'] !== null ? ['sha256', $entry['sha256']] : null,
                        ]],
                        'extra' => [],
                    ];
                }
            }
            throw ToolsException::notFound('build_unknown');
        }

        if ($type === 'purpur') {
            $data = ExternalHttp::get('purpur', '/v2/purpur/' . rawurlencode($version) . '/' . rawurlencode($build), [], self::LIST_TTL);
            $md5 = is_string($data['md5'] ?? null) && preg_match('/^[a-f0-9]{32}$/', $data['md5']) ? $data['md5'] : null;
            if (($data['result'] ?? '') !== 'SUCCESS' || $md5 === null) {
                throw ToolsException::notFound('purpur_build');
            }

            return [
                'version' => $version,
                'build' => $build,
                'java' => self::javaFor($version),
                'files' => [[
                    'role' => 'server',
                    'url' => ExternalHttp::downloadUrl('https://api.purpurmc.org/v2/purpur/' . rawurlencode($version) . '/' . rawurlencode($build) . '/download'),
                    'via' => 'pull',
                    'size' => null,
                    'checksum' => ['md5', $md5],
                ]],
                'extra' => [],
            ];
        }

        if ($type === 'forge') {
            $info = self::forgeIndex()['by_full'][$build] ?? null;
            if (!is_array($info) || ($info['mc'] ?? '') !== $version) {
                throw ToolsException::notFound('build_unknown');
            }

            return [
                'version' => $version,
                'build' => $build,
                'java' => self::javaFor($version),
                'files' => [],
                'extra' => [
                    'forge_version' => $build,
                    'build_type' => !empty($info['recommended']) ? 'recommended' : 'latest',
                ],
            ];
        }

        if ($type === 'neoforge') {
            $info = self::neoIndex()['by_full'][$build] ?? null;
            if (!is_array($info) || ($info['mc'] ?? '') !== $version) {
                throw ToolsException::notFound('build_unknown');
            }

            return [
                'version' => $version,
                'build' => $build,
                'java' => self::javaFor($version),
                'files' => [],
                'extra' => ['neoforge_version' => $build],
            ];
        }

        if ($type === 'vanilla') {
            $server = self::vanillaServer($version);

            return [
                'version' => $version,
                'build' => $build,
                'java' => $server['java'],
                'files' => [['role' => 'server', 'url' => $server['url'], 'via' => 'pull', 'size' => $server['size'], 'checksum' => ['sha1', $server['sha1']]]],
                'extra' => [],
            ];
        }

        // Fabric: Starter (klein, ohne Content-Length -> laedt das Panel) plus passende Vanilla-Jar.
        $installer = null;
        foreach (ExternalHttp::get('fabric', '/v2/versions/installer', [], self::LIST_TTL) as $entry) {
            if (is_array($entry) && ($entry['stable'] ?? false) === true && self::validId($entry['version'] ?? null)) {
                $installer = $entry['version'];
                break;
            }
        }
        if ($installer === null) {
            throw ToolsException::upstream(ExternalHttp::label('fabric'));
        }
        $vanilla = self::vanillaServer($version);

        return [
            'version' => $version,
            'build' => $build,
            'java' => $vanilla['java'],
            'files' => [
                [
                    'role' => 'server',
                    'url' => ExternalHttp::downloadUrl('https://meta.fabricmc.net/v2/versions/loader/' . rawurlencode($version) . '/' . rawurlencode($build) . '/' . rawurlencode($installer) . '/server/jar'),
                    'via' => 'panel',
                    'size' => null,
                    'checksum' => null,
                ],
                ['role' => 'vanilla', 'url' => $vanilla['url'], 'via' => 'pull', 'size' => $vanilla['size'], 'checksum' => ['sha1', $vanilla['sha1']]],
            ],
            'extra' => ['installer' => $installer],
        ];
    }

    /**
     * Sucht den Fill-Build, dessen Datei genau $size Bytes hat (Erkennung der laufenden Version).
     *
     * @return array{id: string, sha256: ?string}|null
     */
    public static function fillBuildBySize(string $type, string $version, int $size): ?array
    {
        if (!isset(self::FILL[$type]) || !self::validId($version)) {
            return null;
        }
        $match = null;
        foreach (self::fillBuilds($type, $version) as $build) {
            if ($build['size'] === $size) {
                if ($match !== null) {
                    return null; // nicht eindeutig
                }
                $match = ['id' => $build['id'], 'sha256' => $build['sha256']];
            }
        }

        return $match;
    }

    /** Groesse der offiziellen Server-Jar einer Vanilla-Version (fuer die Erkennung). */
    public static function vanillaSize(string $version): ?int
    {
        try {
            return self::vanillaServer($version)['size'];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Minecraft game id for a NeoForge loader id such as 21.1.172, or null when it does not match.
     * NeoForge 1.20.1 lives on the legacy net.neoforged:forge line and is not listed here.
     */
    public static function neoforgeGame(string $id): ?string
    {
        $split = self::splitNeo($id);

        return $split['mc'] ?? null;
    }

    /** Benoetigte Java-Hauptversion, wenn der Dienst keine nennt. */
    public static function javaFor(string $version): int
    {
        if (!preg_match('/^(\d+)\.(\d+)(?:\.(\d+))?/', $version, $m)) {
            return 21;
        }
        $major = (int) $m[1];
        $minor = (int) $m[2];
        $patch = (int) ($m[3] ?? 0);
        if ($major >= 26) {
            return 25;
        }
        if ($major !== 1) {
            return 21;
        }
        if ($minor >= 21 || ($minor === 20 && $patch >= 5)) {
            return 21;
        }
        if ($minor >= 18) {
            return 17;
        }
        if ($minor === 17) {
            return 16;
        }

        return 8;
    }

    /** -1, 0, 1 wie version_compare; null, wenn eine Seite nicht vergleichbar ist. */
    public static function compare(?string $from, ?string $to): ?int
    {
        if ($from === null || $to === null || !preg_match('/^\d+(\.\d+)*/', $from) || !preg_match('/^\d+(\.\d+)*/', $to)) {
            return null;
        }

        return version_compare(strtolower($from), strtolower($to));
    }

    /**
     * @return array<int, array{id: string, channel: string, time: ?string, size: ?int, url: string, sha256: ?string}>
     */
    private static function fillBuilds(string $type, string $version): array
    {
        $data = ExternalHttp::get('fill', '/v3/projects/' . self::FILL[$type] . '/versions/' . rawurlencode($version) . '/builds', [], self::LIST_TTL);
        $list = [];
        foreach ($data as $build) {
            $file = is_array($build) ? ($build['downloads']['server:default'] ?? null) : null;
            if (!is_array($file) || !is_string($file['url'] ?? null) || !isset($build['id'])) {
                continue;
            }
            $sha = $file['checksums']['sha256'] ?? null;
            $list[] = [
                'id' => (string) $build['id'],
                'channel' => strtolower((string) ($build['channel'] ?? 'stable')),
                'time' => is_string($build['time'] ?? null) ? $build['time'] : null,
                'size' => isset($file['size']) ? (int) $file['size'] : null,
                'url' => $file['url'],
                'sha256' => is_string($sha) && preg_match('/^[a-f0-9]{64}$/', $sha) ? $sha : null,
            ];
        }
        usort($list, fn ($a, $b) => (int) $b['id'] <=> (int) $a['id']);

        return $list;
    }

    private static function fillJava(string $type, string $version): int
    {
        try {
            $data = ExternalHttp::get('fill', '/v3/projects/' . self::FILL[$type] . '/versions/' . rawurlencode($version), [], self::LIST_TTL);
            $minimum = (int) ($data['version']['java']['version']['minimum'] ?? 0);
            if ($minimum >= 8 && $minimum <= 99) {
                return $minimum;
            }
        } catch (\Throwable $e) {
            // Ohne Angabe gilt die Faustregel.
        }

        return $type === 'velocity' ? 21 : self::javaFor($version);
    }

    /** @return array<string, array{type: string, url: string, time: ?string}> Versionskennung => Eintrag */
    private static function vanillaManifest(): array
    {
        $data = ExternalHttp::get('piston', '/mc/game/version_manifest_v2.json', [], self::LIST_TTL);
        $list = [];
        foreach (is_array($data['versions'] ?? null) ? $data['versions'] : [] as $entry) {
            if (is_array($entry) && self::validId($entry['id'] ?? null) && is_string($entry['url'] ?? null)) {
                $list[$entry['id']] = [
                    'type' => (string) ($entry['type'] ?? ''),
                    'url' => $entry['url'],
                    'time' => is_string($entry['releaseTime'] ?? null) ? $entry['releaseTime'] : null,
                ];
            }
        }

        return $list;
    }

    /** @return array{url: string, size: int, sha1: string, java: int} */
    private static function vanillaServer(string $version): array
    {
        $entry = self::vanillaManifest()[$version] ?? null;
        if ($entry === null) {
            throw ToolsException::notFound('mojang_unknown');
        }
        $data = ExternalHttp::getUrl('piston', $entry['url'], 86400);
        $server = $data['downloads']['server'] ?? null;
        if (!is_array($server) || !is_string($server['url'] ?? null) || !is_string($server['sha1'] ?? null)
            || !preg_match('/^[a-f0-9]{40}$/', $server['sha1']) || (int) ($server['size'] ?? 0) <= 0) {
            throw ToolsException::notFound('mojang_no_server');
        }
        $java = (int) ($data['javaVersion']['majorVersion'] ?? 0);

        return [
            'url' => ExternalHttp::downloadUrl($server['url']),
            'size' => (int) $server['size'],
            'sha1' => $server['sha1'],
            'java' => $java >= 8 && $java <= 99 ? $java : self::javaFor($version),
        ];
    }

    /**
     * Forge index for this request. Promotions add the recommended flag. If that file fails,
     * the maven list is still used and nothing is marked recommended.
     *
     * @return array{games: array<int, array{id: string, stable: bool, group: string}>, builds: array<string, array<int, array{id: string, label: string, channel: string, time: null, size: null}>>, by_full: array<string, array{mc: string, recommended: bool}>}
     */
    private static function forgeIndex(): array
    {
        $promos = [];
        try {
            $data = ExternalHttp::get('forge_files', '/net/minecraftforge/forge/promotions_slim.json', [], self::LIST_TTL);
            $raw = is_array($data['promos'] ?? null) ? $data['promos'] : [];
            $count = 0;
            foreach ($raw as $key => $value) {
                if ($count >= 2000) {
                    break;
                }
                $count++;
                if (!is_string($key) || !is_string($value)) {
                    continue;
                }
                if (!preg_match('/^(\d+\.\d+(?:\.\d+)?)-(latest|recommended)$/', $key, $match)) {
                    continue;
                }
                $full = self::forgeFull($match[1], trim($value));
                if ($full === null) {
                    continue;
                }
                $promos[$full] = $match[2];
            }
        } catch (\Throwable $e) {
            Log::warning('techtyl tools: forge promotions unavailable', ['error' => substr($e->getMessage(), 0, 200)]);
        }

        $grouped = [];
        foreach (self::mavenVersions('forge_maven', '/net/minecraftforge/forge/maven-metadata.xml') as $full) {
            $split = self::splitForge($full);
            if ($split === null) {
                continue;
            }
            $grouped[$split['mc']][$full] = ($promos[$full] ?? '') === 'recommended';
        }

        $games = [];
        $builds = [];
        $byFull = [];
        foreach ($grouped as $mc => $rows) {
            if (!self::validId($mc)) {
                continue;
            }
            $games[] = ['id' => $mc, 'stable' => self::looksStable($mc), 'group' => self::group($mc)];
            $list = [];
            foreach ($rows as $full => $recommended) {
                $split = self::splitForge($full);
                if ($split === null) {
                    continue;
                }
                $byFull[$full] = ['mc' => $mc, 'recommended' => $recommended];
                $list[] = [
                    'id' => $full,
                    'label' => I18n::t($recommended ? 'tools.forge_recommended' : 'tools.forge_build', ['id' => $split['build']]),
                    'channel' => 'stable',
                    'time' => null,
                    'size' => null,
                    'sort' => $split['build'],
                    'recommended' => $recommended,
                ];
            }
            usort($list, fn ($a, $b) => version_compare($b['sort'], $a['sort']));
            $recommended = null;
            $rest = [];
            foreach ($list as $row) {
                unset($row['sort']);
                if ($row['recommended']) {
                    $recommended = $row;
                    unset($recommended['recommended']);
                } else {
                    unset($row['recommended']);
                    $rest[] = $row;
                }
            }
            $rest = array_slice($rest, 0, self::MAX_BUILDS);
            $builds[$mc] = $recommended !== null ? array_merge([$recommended], $rest) : $rest;
        }
        usort($games, fn ($a, $b) => version_compare($b['id'], $a['id']));

        return ['games' => $games, 'builds' => $builds, 'by_full' => $byFull];
    }

    /**
     * NeoForge loader ids mapped onto Minecraft. A suffix such as -beta is unstable.
     * The legacy 1.20.1 line is a different maven artifact and is intentionally absent.
     *
     * @return array{games: array<int, array{id: string, stable: bool, group: string}>, builds: array<string, array<int, array{id: string, label: string, channel: string, time: null, size: null}>>, by_full: array<string, array{mc: string}>}
     */
    private static function neoIndex(): array
    {
        $grouped = [];
        $stableGame = [];
        foreach (self::mavenVersions('neoforge', '/releases/net/neoforged/neoforge/maven-metadata.xml') as $id) {
            $split = self::splitNeo($id);
            if ($split === null) {
                continue;
            }
            $grouped[$split['mc']][] = ['id' => $id, 'unstable' => $split['unstable']];
            if (!$split['unstable']) {
                $stableGame[$split['mc']] = true;
            }
        }

        $games = [];
        $builds = [];
        $byFull = [];
        foreach ($grouped as $mc => $rows) {
            $games[] = ['id' => $mc, 'stable' => isset($stableGame[$mc]), 'group' => self::group($mc)];
            usort($rows, fn ($a, $b) => version_compare($b['id'], $a['id']));
            $list = [];
            foreach (array_slice($rows, 0, self::MAX_BUILDS) as $row) {
                $byFull[$row['id']] = ['mc' => $mc];
                $list[] = [
                    'id' => $row['id'],
                    'label' => $row['unstable'] ? I18n::t('tools.neoforge_beta', ['id' => $row['id']]) : $row['id'],
                    'channel' => $row['unstable'] ? 'beta' : 'stable',
                    'time' => null,
                    'size' => null,
                ];
            }
            $builds[$mc] = $list;
        }
        usort($games, fn ($a, $b) => version_compare($b['id'], $a['id']));

        return ['games' => $games, 'builds' => $builds, 'by_full' => $byFull];
    }

    /** @return array<int, string> */
    private static function mavenVersions(string $service, string $path): array
    {
        $xml = ExternalHttp::text($service, $path, [], self::LIST_TTL);
        if ($xml === '' || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw ToolsException::upstream(ExternalHttp::label($service));
        }

        $versions = self::mavenRead($xml);
        if ($versions === [] && str_contains($xml, 'xmlns=')) {
            $stripped = preg_replace('/\sxmlns="[^"]*"/', '', $xml, 1);
            if (is_string($stripped)) {
                $versions = self::mavenRead($stripped);
            }
        }
        if ($versions === []) {
            Log::warning('techtyl tools: maven metadata has no versions', ['service' => $service]);
            throw ToolsException::upstream(ExternalHttp::label($service));
        }

        return $versions;
    }

    /** @return array<int, string> */
    private static function mavenRead(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
        } catch (\Throwable $e) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            Log::warning('techtyl tools: maven metadata unreadable', ['error' => substr($e->getMessage(), 0, 200)]);

            return [];
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($doc === false) {
            return [];
        }

        $versions = [];
        $nodes = $doc->versioning->versions->version ?? [];
        foreach ($nodes as $node) {
            $id = trim((string) $node);
            if ($id !== '' && self::validId($id)) {
                $versions[] = $id;
            }
            if (count($versions) >= 8000) {
                break;
            }
        }

        return $versions;
    }

    /** @return array{mc: string, build: string}|null */
    private static function splitForge(string $full): ?array
    {
        if (!self::validId($full)) {
            return null;
        }
        if (!preg_match('/^(\d+\.\d+(?:\.\d+)?)-(\d+(?:\.\d+){1,3})(?:-(\d+\.\d+(?:\.\d+)?))?$/', $full, $match)) {
            return null;
        }
        if (($match[3] ?? '') !== '' && $match[3] !== $match[1]) {
            return null;
        }

        return ['mc' => $match[1], 'build' => $match[2]];
    }

    private static function forgeFull(string $mc, string $value): ?string
    {
        if ($value === '' || strlen($value) > 60) {
            return null;
        }
        $full = str_starts_with($value, $mc . '-') ? $value : $mc . '-' . $value;
        $split = self::splitForge($full);
        if ($split === null || $split['mc'] !== $mc) {
            return null;
        }

        return $full;
    }

    /** @return array{mc: string, unstable: bool}|null */
    private static function splitNeo(string $id): ?array
    {
        if (!self::validId($id)) {
            return null;
        }
        if (!preg_match('/^(\d{2})\.(\d{1,3})\.(\d{1,5})(-[a-z0-9.]{1,16})?$/', $id, $match)) {
            return null;
        }
        $major = (int) $match[1];
        $minor = (int) $match[2];
        if ($major < 20 || $major > 80) {
            return null;
        }
        $mc = $minor === 0 ? '1.' . $major : '1.' . $major . '.' . $minor;
        if (!self::validId($mc)) {
            return null;
        }

        return ['mc' => $mc, 'unstable' => ($match[4] ?? '') !== ''];
    }

    private static function looksStable(string $id): bool
    {
        return preg_match('/^\d+(\.\d+)*$/', $id) === 1;
    }

    private static function group(string $id): string
    {
        return preg_match('/^(\d+\.\d+)/', $id, $m) ? $m[1] : $id;
    }
}
