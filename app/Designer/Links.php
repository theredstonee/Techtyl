<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Designer;

use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;

/**
 * Website-Links (Support, Status, Abrechnung ...) und Social-Links, die im Dashboard ueber dem
 * Inhalt oder als schwebende Leiste erscheinen. Nur http- und https-Adressen.
 */
class Links
{
    public const RECORD = 'links';

    public const MAX_WEBSITE = 6;
    public const MAX_SOCIAL = 8;
    public const LABEL_MAX = 32;
    public const URL_MAX = 300;

    /** Art => Standard-Beschriftung */
    public const WEBSITE_KINDS = [
        'support' => 'Support',
        'status' => 'Status',
        'billing' => 'Abrechnung',
        'docs' => 'Anleitungen',
        'website' => 'Website',
    ];

    /** Netzwerk => Anzeigename */
    public const SOCIAL_NETWORKS = [
        'discord' => 'Discord',
        'github' => 'GitHub',
        'youtube' => 'YouTube',
        'twitch' => 'Twitch',
        'x' => 'X',
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
        'mastodon' => 'Mastodon',
        'reddit' => 'Reddit',
    ];

    public const POSITIONS = ['oben', 'schwebend'];
    public const ALIGN = ['links', 'mitte', 'rechts'];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'website' => [],
            'social' => [],
            'position' => 'oben',
            'align' => 'rechts',
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public static function rules(string $prefix): array
    {
        $url = static function (string $attribute, mixed $value, \Closure $fail): void {
            if (Clean::httpUrl($value, self::URL_MAX) === '') {
                $fail(I18n::t('designer.v_url'));
            }
        };

        return [
            $prefix . 'website' => ['sometimes', 'array', 'max:' . self::MAX_WEBSITE],
            $prefix . 'website.*' => ['array'],
            $prefix . 'website.*.kind' => ['required', 'string', 'in:' . implode(',', array_keys(self::WEBSITE_KINDS))],
            $prefix . 'website.*.label' => ['nullable', 'string', 'max:' . self::LABEL_MAX],
            $prefix . 'website.*.url' => ['required', 'string', 'max:' . self::URL_MAX, $url],
            $prefix . 'social' => ['sometimes', 'array', 'max:' . self::MAX_SOCIAL],
            $prefix . 'social.*' => ['array'],
            $prefix . 'social.*.network' => ['required', 'string', 'in:' . implode(',', array_keys(self::SOCIAL_NETWORKS))],
            $prefix . 'social.*.url' => ['required', 'string', 'max:' . self::URL_MAX, $url],
            $prefix . 'position' => ['sometimes', 'string', 'in:' . implode(',', self::POSITIONS)],
            $prefix . 'align' => ['sometimes', 'string', 'in:' . implode(',', self::ALIGN)],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function sanitize(array $input): array
    {
        $d = self::defaults();
        $website = [];
        foreach (is_array($input['website'] ?? null) ? $input['website'] : [] as $entry) {
            if (!is_array($entry) || count($website) >= self::MAX_WEBSITE) {
                continue;
            }
            $kind = is_string($entry['kind'] ?? null) && isset(self::WEBSITE_KINDS[$entry['kind']]) ? $entry['kind'] : null;
            $url = Clean::httpUrl($entry['url'] ?? null, self::URL_MAX);
            if ($kind === null || $url === '') {
                continue;
            }
            $label = Clean::line($entry['label'] ?? '', self::LABEL_MAX);
            // Empty label: the dashboard shows the translated name of the kind.
            $website[] = ['kind' => $kind, 'label' => $label, 'url' => $url];
        }

        $social = [];
        foreach (is_array($input['social'] ?? null) ? $input['social'] : [] as $entry) {
            if (!is_array($entry) || count($social) >= self::MAX_SOCIAL) {
                continue;
            }
            $network = is_string($entry['network'] ?? null) && isset(self::SOCIAL_NETWORKS[$entry['network']]) ? $entry['network'] : null;
            $url = Clean::httpUrl($entry['url'] ?? null, self::URL_MAX);
            if ($network === null || $url === '') {
                continue;
            }
            $social[] = ['network' => $network, 'url' => $url];
        }

        return [
            'website' => $website,
            'social' => $social,
            'position' => in_array($input['position'] ?? null, self::POSITIONS, true) ? $input['position'] : $d['position'],
            'align' => in_array($input['align'] ?? null, self::ALIGN, true) ? $input['align'] : $d['align'],
        ];
    }

    /** @return array<string, mixed> */
    public static function fromStored(mixed $stored): array
    {
        $decoded = is_string($stored) ? json_decode($stored, true) : null;

        return self::sanitize(is_array($decoded) ? $decoded : []);
    }

    /**
     * Fuer das Dashboard: null, wenn es keine Links gibt.
     *
     * @param array<string, mixed> $links
     * @return array<string, mixed>|null
     */
    public static function forClient(array $links): ?array
    {
        $links = self::sanitize($links);

        return $links['website'] === [] && $links['social'] === [] ? null : $links;
    }
}
