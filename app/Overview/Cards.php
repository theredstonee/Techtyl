<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Overview;

use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Design;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Client\BlueprintClientLibrary;

/**
 * Which cards the admin overview shows, in which order, plus the admin's own cards.
 * Stored as JSON in dbSet('techtyl', 'overview_cards'):
 *   {"order": ["servers", "module:tickets", "custom:1a2b3c4d", ...],
 *    "hidden": ["databases", ...],
 *    "custom": {"1a2b3c4d": {"title": "...", "text": "...", "link": "https://...", "icon": "star"}}}
 * Everything read from storage is validated again; unknown entries are dropped.
 */
class Cards
{
    public const RECORD = 'overview_cards';

    /** Built-in cards in their default order. */
    public const BUILTIN = [
        'servers', 'memory', 'disk', 'users',
        'users_new', 'suspended', 'backups', 'eggs',
        'databases', 'maintenance', 'allocations',
    ];

    /** Hidden until the admin turns them on (their figures are also in the System card). */
    public const DEFAULT_HIDDEN = ['databases', 'maintenance', 'allocations'];

    /** Font Awesome 4.7 icons offered for custom cards. */
    public const ICONS = [
        'star', 'bookmark', 'link', 'book', 'life-ring', 'bullhorn', 'info-circle', 'shield', 'bolt',
        'heart', 'globe', 'envelope', 'github', 'cog', 'wrench', 'server', 'users', 'credit-card',
        'line-chart', 'calendar', 'bell', 'flag', 'rocket', 'cube', 'comments', 'file-text-o',
    ];

    public const MAX_CUSTOM = 12;

    public const TITLE_MAX = 40;

    public const TEXT_MAX = 140;

    public const LINK_MAX = 300;

    public const ID_PATTERN = '/^(?:[a-z][a-z_]{1,19}|module:[a-z]{2,20}|custom:[a-f0-9]{8})$/';

    /** @return array{order: list<string>, hidden: list<string>, custom: array<string, array{title: string, text: string, link: ?string, icon: string}>} */
    public static function config(): array
    {
        try {
            $stored = app(BlueprintClientLibrary::class)->dbGet(Design::TABLE, self::RECORD, '');
        } catch (\Throwable) {
            $stored = '';
        }
        $decoded = is_string($stored) && $stored !== '' ? json_decode($stored, true) : null;

        return self::sanitize(is_array($decoded) ? $decoded : ['hidden' => self::DEFAULT_HIDDEN]);
    }

    public static function store(array $config): void
    {
        app(BlueprintClientLibrary::class)->dbSet(Design::TABLE, self::RECORD, json_encode(self::sanitize($config)));
    }

    /** Cleans a configuration (from storage or from a form). */
    public static function sanitize(array $input): array
    {
        $custom = [];
        foreach ((array) ($input['custom'] ?? []) as $id => $card) {
            if (count($custom) >= self::MAX_CUSTOM || !is_string($id) || preg_match('/^[a-f0-9]{8}$/', $id) !== 1 || !is_array($card)) {
                continue;
            }
            $clean = self::cleanCustom($card);
            if ($clean !== null) {
                $custom[$id] = $clean;
            }
        }

        $ids = [];
        foreach ((array) ($input['order'] ?? []) as $id) {
            if (is_string($id) && preg_match(self::ID_PATTERN, $id) === 1 && !in_array($id, $ids, true)) {
                if (str_starts_with($id, 'custom:') && !isset($custom[substr($id, 7)])) {
                    continue;
                }
                $ids[] = $id;
            }
            if (count($ids) >= 80) {
                break;
            }
        }

        $hidden = [];
        foreach ((array) ($input['hidden'] ?? []) as $id) {
            if (is_string($id) && preg_match(self::ID_PATTERN, $id) === 1 && !in_array($id, $hidden, true) && count($hidden) < 80) {
                $hidden[] = $id;
            }
        }

        return ['order' => $ids, 'hidden' => $hidden, 'custom' => $custom];
    }

    /**
     * Validates a custom card. Title is required; text and link are optional.
     *
     * @return array{title: string, text: string, link: ?string, icon: string}|null
     */
    public static function cleanCustom(array $card): ?array
    {
        $title = self::cleanText($card['title'] ?? '', self::TITLE_MAX);
        if ($title === '') {
            return null;
        }
        $link = self::cleanLink($card['link'] ?? null);
        $icon = is_string($card['icon'] ?? null) && in_array($card['icon'], self::ICONS, true) ? $card['icon'] : self::ICONS[0];

        return [
            'title' => $title,
            'text' => self::cleanText($card['text'] ?? '', self::TEXT_MAX),
            'link' => $link === '' ? null : $link,
            'icon' => $icon,
        ];
    }

    /** Single line of plain text without control characters. */
    public static function cleanText(mixed $value, int $max): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = preg_replace('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]+/u', ' ', $value) ?? '';

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $value) ?? ''), 0, $max);
    }

    /**
     * Absolute http(s) link without credentials, or '' when empty, or null when invalid.
     */
    public static function cleanLink(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (!is_string($value) || strlen($value) > self::LINK_MAX || preg_match('/[\s\x00-\x1f\x7f\\\\]/', $value) === 1) {
            return null;
        }
        $parts = parse_url($value);
        if (!is_array($parts) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return $value;
    }

    /**
     * Puts the available cards in the configured order. Cards that are not in the stored order yet
     * (new built-ins, newly enabled modules) are appended in their default position.
     *
     * @param array<string, array> $available id => card data (with at least 'title')
     * @return list<array> every available card with 'id', 'visible' and its data
     */
    public static function arrange(array $available, array $config): array
    {
        $order = array_values(array_filter($config['order'], static fn ($id) => isset($available[$id])));
        foreach (array_keys($available) as $id) {
            if (!in_array($id, $order, true)) {
                $order[] = $id;
            }
        }

        $cards = [];
        foreach ($order as $id) {
            $cards[] = ['id' => $id, 'visible' => !in_array($id, $config['hidden'], true)] + $available[$id];
        }

        return $cards;
    }

    public static function newId(array $config): string
    {
        do {
            $id = bin2hex(random_bytes(4));
        } while (isset($config['custom'][$id]));

        return $id;
    }
}
