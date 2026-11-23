<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Designer;

/**
 * Kleine, strenge Helfer fuer Texte und Adressen aus dem Designer. Alles, was hier herauskommt,
 * ist entweder reiner Text (wird im Browser nur als Text ausgegeben) oder eine gepruefte Adresse.
 */
class Clean
{
    /**
     * Einzeiliger Text: Steuer- und Formatzeichen fallen weg, Leerraum wird zusammengefasst,
     * die Laenge in Zeichen begrenzt.
     */
    public static function line(mixed $value, int $max): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }
        $text = (string) $value;
        if (!mb_check_encoding($text, 'UTF-8')) {
            return '';
        }
        $text = preg_replace('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]+/u', ' ', $text) ?? '';
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return mb_substr($text, 0, $max);
    }

    /**
     * Mehrzeiliger Text: wie line(), aber Zeilenumbrueche bleiben (hoechstens $maxLines Zeilen,
     * keine Leerzeilen-Ketten).
     */
    public static function block(mixed $value, int $max, int $maxLines): string
    {
        if (!is_string($value)) {
            return '';
        }
        if (!mb_check_encoding($value, 'UTF-8')) {
            return '';
        }
        $text = str_replace(["\r\n", "\r"], "\n", $value);
        $lines = [];
        foreach (explode("\n", $text) as $line) {
            $line = self::line($line, $max);
            if ($line === '' && ($lines === [] || end($lines) === '')) {
                continue;
            }
            $lines[] = $line;
        }
        while ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        return mb_substr(implode("\n", array_slice($lines, 0, $maxLines)), 0, $max);
    }

    /**
     * Nur http- und https-Adressen mit Rechnername, ohne Zugangsdaten und ohne Zeichen, die in
     * Attributen oder CSS ausbrechen koennten. Ungueltig ergibt einen leeren Text.
     */
    public static function httpUrl(mixed $value, int $max = 300): string
    {
        if (!is_string($value)) {
            return '';
        }
        $url = trim($value);
        if ($url === '' || strlen($url) > $max || preg_match('/[\s"\'<>\\\\`\x00-\x1f\x7f]/', $url)) {
            return '';
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return '';
        }
        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }

        return $url;
    }

    /**
     * Text als CSS-Zeichenkette. Ausser Buchstaben A-Z, Ziffern und Leerzeichen wird jedes Zeichen
     * als sechsstellige Hex-Folge geschrieben; damit kann der Text weder die Zeichenkette noch den
     * style-Block verlassen.
     */
    public static function cssString(string $text): string
    {
        $out = '"';
        foreach (mb_str_split($text) as $char) {
            if (preg_match('/^[A-Za-z0-9 ]$/', $char) === 1) {
                $out .= $char;
            } else {
                // The trailing space ends the escape; CSS swallows it, so a real space after it survives.
                $out .= '\\' . str_pad(dechex(mb_ord($char)), 6, '0', STR_PAD_LEFT) . ' ';
            }
        }

        return $out . '"';
    }
}
