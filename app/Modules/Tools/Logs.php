<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Pterodactyl\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant\Redactor;

/**
 * Tail of logs/latest.log, crash reports, and a masked upload to mclo.gs.
 */
final class Logs
{
    public const TAIL = 262144;

    public const MAX_READ = 33554432;

    public const SHARE_BYTES = 1048576;

    public const SHARE_LINES = 10000;

    public const CRASH_ROUTE = 'crash-\d{4}-\d{2}-\d{2}_\d{2}\.\d{2}\.\d{2}-[A-Za-z0-9]{1,24}\.txt';

    public const CRASH = '/^' . self::CRASH_ROUTE . '$/';

    /** @return array<string, mixed> */
    public static function show(Wings $wings): array
    {
        return self::present($wings->tail('/logs/latest.log', self::TAIL, self::MAX_READ), 'logs/latest.log');
    }

    /** @return array{reports: array<int, array{name: string, size: int, modified: string}>} */
    public static function crashes(Wings $wings): array
    {
        $reports = [];
        foreach ($wings->list('/crash-reports') as $entry) {
            if (!$entry['file'] || preg_match(self::CRASH, $entry['name']) !== 1) {
                continue;
            }
            $reports[] = [
                'name' => $entry['name'],
                'size' => $entry['size'],
                'modified' => $entry['modified'],
            ];
        }
        usort($reports, fn ($a, $b) => [$b['modified'], $b['name']] <=> [$a['modified'], $a['name']]);

        return ['reports' => array_slice($reports, 0, 40)];
    }

    /** @return array<string, mixed> */
    public static function crash(Wings $wings, string $file): array
    {
        if (preg_match(self::CRASH, $file) !== 1) {
            throw ToolsException::invalid('log_crash_name');
        }

        return self::present($wings->tail('/crash-reports/' . $file, self::TAIL, self::MAX_READ), 'crash-reports/' . $file) + ['name' => $file];
    }

    /** @return array{id: string, url: string} */
    public static function share(User $user, string $content): array
    {
        if (!ToolsSettings::shareLogs()) {
            throw ToolsException::forbidden('share_disabled');
        }
        if (strlen($content) > self::SHARE_BYTES || substr_count($content, "\n") + 1 > self::SHARE_LINES) {
            throw ToolsException::invalid('log_too_long');
        }
        if (trim($content) === '') {
            throw ToolsException::invalid('log_empty');
        }
        $limit = 'techtyl-tools-share:' . $user->id;
        if (RateLimiter::tooManyAttempts($limit, 5)) {
            throw ToolsException::limited('share_limited', RateLimiter::availableIn($limit));
        }
        RateLimiter::hit($limit, 600);

        try {
            $masked = Redactor::mask($content);
        } catch (\Throwable $e) {
            Log::warning('techtyl tools: Log konnte nicht maskiert werden', ['error' => substr($e->getMessage(), 0, 200)]);
            throw ToolsException::upstream('mclo.gs');
        }
        if (trim($masked) === '') {
            throw ToolsException::invalid('log_empty');
        }
        $data = ExternalHttp::post('mclo', '/1/log', ['content' => $masked, 'source' => 'Techtyl'], 0);
        $id = $data['id'] ?? null;
        $url = $data['url'] ?? null;
        if (($data['success'] ?? false) !== true || !is_string($id) || preg_match('/^[A-Za-z0-9]{4,16}$/', $id) !== 1) {
            Log::warning('techtyl tools: mclo.gs Antwort ohne Kennung');
            throw ToolsException::upstream('mclo.gs');
        }
        $expected = 'https://mclo.gs/' . $id;
        if (!is_string($url) || $url !== $expected) {
            Log::warning('techtyl tools: mclo.gs lieferte eine unerwartete Adresse');
            throw ToolsException::upstream('mclo.gs');
        }

        return ['id' => $id, 'url' => $expected];
    }

    /**
     * @param array{text: ?string, missing: bool, too_large: bool, truncated: bool, bytes: int} $tail
     * @return array<string, mixed>
     */
    private static function present(array $tail, string $file): array
    {
        return [
            'file' => $file,
            'missing' => $tail['missing'],
            'too_large' => $tail['too_large'],
            'truncated' => $tail['truncated'],
            'bytes' => $tail['bytes'],
            'text' => $tail['text'] ?? '',
            'sharing' => ToolsSettings::shareLogs(),
        ];
    }
}
