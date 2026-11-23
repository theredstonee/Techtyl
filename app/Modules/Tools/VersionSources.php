<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;

/**
 * Versionen, Builds und Download-Daten je Servertyp:
 *   paper, folia, velocity  -> PaperMC Fill v3
 *   purpur                  -> api.purpurmc.org v2
 *   vanilla                 -> Mojang piston-meta
 *   fabric                  -> meta.fabricmc.net (Build = Loader-Version)
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

    private static function looksStable(string $id): bool
    {
        return preg_match('/^\d+(\.\d+)*$/', $id) === 1;
    }

    private static function group(string $id): string
    {
        return preg_match('/^(\d+\.\d+)/', $id, $m) ? $m[1] : $id;
    }
}
