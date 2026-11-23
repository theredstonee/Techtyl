<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier};

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Entries of the Techtyl group in the admin sidebar (views/adminnav.blade.php).
 *
 * Runs on every admin page, so it must never throw and never cost more than one cached
 * count: without Techtyl tables, without settings or without a signed-in root admin it
 * simply returns fewer entries or none.
 */
class AdminNav
{
    private const ROOT = '/admin/extensions/{identifier}';
    private const DESIGNER = '/extensions/{identifier}/designer/editor';
    private const TICKETS_CACHE = 'techtyl:adminnav:tickets-waiting';
    private const TICKETS_TABLE = 'techtyl_tickets_tickets';
    private const TICKETS_WAITING_FOR_TEAM = 'open';
    private const CACHE_SECONDS = 60;

    /** Module key => Font Awesome 4 icon, matching the icon set of the panel's sidebar. */
    private const MODULE_ICONS = [
        'tickets' => 'fa-life-ring',
        'billing' => 'fa-credit-card',
        'auth' => 'fa-user-plus',
        'tools' => 'fa-cubes',
        'assistant' => 'fa-commenting-o',
    ];

    /**
     * @return array<int, array{key: string, label: string, href: string, icon: string, active: bool, count: int, hint: string}>
     */
    public static function items(): array
    {
        try {
            $user = auth()->user();
            if ($user === null || !$user->root_admin) {
                return [];
            }

            $current = self::currentTab();
            $items = [
                self::item('designer', self::DESIGNER, 'fa-paint-brush', false),
                self::item('design', self::ROOT, 'fa-sliders', $current === 'design'),
                self::item('modules', self::ROOT . '?tab=modules', 'fa-th-list', $current === 'modules'),
            ];

            foreach (array_keys(Modules::ALL) as $key) {
                if (!Modules::enabled($key)) {
                    continue;
                }
                $item = self::item($key, self::ROOT . '?tab=' . $key, self::MODULE_ICONS[$key] ?? 'fa-circle-o', $current === $key);
                if ($key === 'tickets') {
                    $item['count'] = self::ticketsWaiting();
                    $item['hint'] = $item['count'] > 0
                        ? I18n::t('adminnav.tickets_waiting', ['count' => $item['count']])
                        : '';
                }
                $items[] = $item;
            }

            return $items;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function title(): string
    {
        try {
            return I18n::t('adminnav.group');
        } catch (\Throwable $e) {
            return 'Techtyl';
        }
    }

    /** @return array{key: string, label: string, href: string, icon: string, active: bool, count: int, hint: string} */
    private static function item(string $key, string $href, string $icon, bool $active): array
    {
        return [
            'key' => $key,
            'label' => I18n::t('adminnav.' . $key),
            'href' => $href,
            'icon' => $icon,
            'active' => $active,
            'count' => 0,
            'hint' => '',
        ];
    }

    /** Tab of the Techtyl admin page that is open right now, or null on any other page. */
    private static function currentTab(): ?string
    {
        $request = request();
        if (trim($request->path(), '/') !== trim(self::ROOT, '/')) {
            return null;
        }

        // Same rule as the admin controller: anything unknown shows the design tab.
        $tab = $request->query('tab', 'design');
        $tab = is_string($tab) ? $tab : 'design';

        return ($tab === 'modules' || Modules::exists($tab)) ? $tab : 'design';
    }

    /**
     * Tickets whose last word came from the user, i.e. the team has to answer.
     * One indexed count, cached for a minute. A missing table (migration not run yet) counts as 0
     * and is cached as well, so a broken state does not query on every page view.
     */
    private static function ticketsWaiting(): int
    {
        try {
            return (int) Cache::remember(self::TICKETS_CACHE, self::CACHE_SECONDS, static function (): int {
                try {
                    return (int) DB::table(self::TICKETS_TABLE)->where('status', self::TICKETS_WAITING_FOR_TEAM)->count();
                } catch (\Throwable $e) {
                    return 0;
                }
            });
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
