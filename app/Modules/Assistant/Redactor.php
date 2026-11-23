<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant;

/**
 * Maskiert Server-Daten, bevor sie an einen KI-Anbieter gehen: IP-Adressen, E-Mail-Adressen,
 * Zugangsdaten und alles, was wie ein Token aussieht. Lieber einmal zu viel als zu wenig.
 */
class Redactor
{
    public const IP = '[IP]';
    public const MAIL = '[EMAIL]';
    public const TOKEN = '[TOKEN]';
    public const HIDDEN = '[HIDDEN]';

    public static function mask(string $text): string
    {
        // Ungueltiges UTF-8, Farbcodes der Konsole und Steuerzeichen entfernen.
        $text = mb_scrub($text, 'UTF-8');
        $text = preg_replace('/\x1b\[[0-9;?]*[ -\/]*[@-~]|\x1b[@-Z\\\\-_]/', '', $text) ?? $text;
        $text = preg_replace('/[^\P{C}\n\t]+/u', '', $text) ?? $text;

        $rules = [
            // Zugangsdaten in Adressen: schema://nutzer:passwort@host
            '/(?<=:\/\/)[^\s\/@:]+:[^\s\/@]+@/' => '[CREDENTIALS]@',
            // E-Mail-Adressen
            '/[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+/' => self::MAIL,
            // Bekannte Token-Formen: JWT, Discord, API-Schluessel mit Praefix
            '/\beyJ[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{4,}/' => self::TOKEN,
            '/\b[MNO][A-Za-z\d_-]{23,27}\.[A-Za-z\d_-]{6}\.[A-Za-z\d_-]{27,}/' => self::TOKEN,
            '/\b(?:sk|pk|rk|xox[abprs]|gh[pousr]|glpat|AKIA|AIza)[-_]?[A-Za-z0-9_-]{16,}/' => self::TOKEN,
            '/\bBearer\s+[A-Za-z0-9._~+\/=-]{8,}/i' => 'Bearer ' . self::TOKEN,
            // schluessel=wert und schluessel: wert
            '/\b((?:[a-z0-9]+[._-])*(?:pass(?:word|wd|wort)?|secret|token|api[_-]?key|apikey|auth(?:orization)?|access[_-]?key|private[_-]?key|credentials?)(?:[._-][a-z0-9]+)*)(\s*[=:]\s*)("[^"\n]*"|\'[^\'\n]*\'|[^\s,;]+)/i' => '$1$2' . self::HIDDEN,
            // IPv4, auch mit Port
            // (vierteilige Versionsnummern wie bei Java bleiben stehen, sie sind fuer die Diagnose wichtig)
            '/(?<![\d.])(?<!version )(?<!version ")(?<!version: )(?<!build )(?<!jdk-)(?<!jre-)(?<!temurin-)(?:(?:25[0-5]|2[0-4]\d|1?\d?\d)\.){3}(?:25[0-5]|2[0-4]\d|1?\d?\d)(?![\d.])(?!\+\d)(?::\d{1,5}\b)?/i' => self::IP,
            // IPv6: ausgeschrieben oder mit "::"
            '/(?<![0-9A-Fa-f:.])(?:[0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4}(?![0-9A-Fa-f:])/' => self::IP,
            '/(?<![0-9A-Za-z:.])(?:[0-9A-Fa-f]{1,4}(?::[0-9A-Fa-f]{1,4}){0,6})?::(?:[0-9A-Fa-f]{1,4}(?::[0-9A-Fa-f]{1,4}){0,6})(?![0-9A-Za-z:(])/' => self::IP,
        ];
        foreach ($rules as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        // Lange Zeichenketten ohne Leerzeichen, die Buchstaben und Ziffern mischen (Schluessel, Hashes).
        $text = preg_replace_callback('/(?<![A-Za-z0-9_-])[A-Za-z0-9_-]{32,}(?![A-Za-z0-9_-])/', static function (array $match): string {
            $value = $match[0];

            return preg_match('/\d/', $value) === 1 && preg_match('/[A-Za-z]/', $value) === 1 ? self::TOKEN : $value;
        }, $text) ?? $text;

        return $text;
    }
}
