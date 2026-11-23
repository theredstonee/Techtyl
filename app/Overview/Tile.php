<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Overview;

/**
 * Inhalt einer Modul-Kachel auf der Admin-Uebersicht. Alle Texte werden als Text ausgegeben (escaped).
 */
final class Tile
{
    public const TONES = ['neutral', 'ok', 'warn', 'bad'];

    /**
     * @param string $title Kurzer Titel, z. B. "Offene Tickets" (max. 40 Zeichen)
     * @param string $value Fertig formatierter Hauptwert, z. B. "12" oder "1.240,50 EUR" (max. 24 Zeichen)
     * @param string|null $link Ziel beim Anklicken: Pfad im Panel ("/admin/...") oder eine URL dieses Panels
     * @param string|null $hint Eine Zeile Zusatz, z. B. "3 warten auf Antwort" (max. 60 Zeichen)
     * @param string $tone neutral | ok | warn | bad, zeigt einen Zustandspunkt vor dem Zusatz
     * @param string|null $icon Font-Awesome-4-Name ohne "fa-", z. B. "life-ring"
     */
    public function __construct(
        public readonly string $title,
        public readonly string $value,
        public readonly ?string $link = null,
        public readonly ?string $hint = null,
        public readonly string $tone = 'neutral',
        public readonly ?string $icon = null,
    ) {
    }

    /**
     * Geprueft und gekuerzt, fertig fuer Cache und Ansicht.
     *
     * @return array{title: string, value: string, link: ?string, hint: ?string, tone: string, icon: string}
     */
    public function toArray(): array
    {
        $hint = $this->hint !== null ? trim($this->hint) : '';

        return [
            'title' => mb_substr(trim($this->title), 0, 40),
            'value' => mb_substr(trim($this->value), 0, 24),
            'link' => self::safeLink($this->link),
            'hint' => $hint === '' ? null : mb_substr($hint, 0, 60),
            'tone' => in_array($this->tone, self::TONES, true) ? $this->tone : 'neutral',
            'icon' => is_string($this->icon) && preg_match('/^[a-z0-9-]{1,32}$/', $this->icon) === 1 ? $this->icon : 'puzzle-piece',
        ];
    }

    /** Nur Pfade im Panel und URLs mit dem Host des Panels; alles andere entfaellt. */
    private static function safeLink(?string $link): ?string
    {
        if ($link === null || $link === '' || strlen($link) > 300) {
            return null;
        }
        if (str_starts_with($link, '/') && !str_starts_with($link, '//') && !str_contains($link, '\\')) {
            return $link;
        }
        $parts = parse_url($link);
        $panel = parse_url((string) config('app.url'));
        if (!is_array($parts) || !is_array($panel)) {
            return null;
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        $sameHost = isset($parts['host'], $panel['host']) && strcasecmp($parts['host'], $panel['host']) === 0;
        if (!in_array($scheme, ['http', 'https'], true) || !$sameHost) {
            return null;
        }
        $path = $parts['path'] ?? '/';

        return $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }
}
