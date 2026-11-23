<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Billing;

/**
 * Geldbeträge sind ganze Cent. Hier wird nur zwischen Text und Cent umgerechnet, ohne
 * Fließkommazahlen.
 */
class Money
{
    /** Währungen mit zwei Nachkommastellen, die das Modul unterstützt. */
    public const CURRENCIES = [
        'eur' => '€',
        'usd' => '$',
        'gbp' => '£',
        'chf' => 'CHF',
        'pln' => 'zł',
        'czk' => 'Kč',
        'sek' => 'kr',
        'nok' => 'kr',
        'dkk' => 'kr',
    ];

    /** Obergrenze für jeden einzelnen Betrag (99.999,99). */
    public const MAX_CENTS = 9999999;

    /** "7,99", "7.99" oder "7" -> 799. Liefert null bei ungültiger Eingabe. */
    public static function parse(mixed $input): ?int
    {
        if (!is_string($input) && !is_int($input)) {
            return null;
        }
        $text = trim((string) $input);
        if (preg_match('/^(\d{1,5})(?:[.,](\d{1,2}))?$/', $text, $m) !== 1) {
            return null;
        }
        $fraction = str_pad($m[2] ?? '', 2, '0');

        return ((int) $m[1]) * 100 + (int) $fraction;
    }

    /** Symbols written in front of the amount in English ("€7.99"); all others follow it ("7.99 CHF"). */
    private const PREFIXED = ['eur', 'usd', 'gbp'];

    /** 799 -> "€7.99" (en) or "7,99 €" (de). The language defaults to the current one. */
    public static function format(int $cents, string $currency, ?string $locale = null): string
    {
        $locale ??= \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::locale();
        $symbol = self::CURRENCIES[$currency] ?? strtoupper($currency);
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);
        $fraction = str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);

        if ($locale === 'de') {
            return $sign . number_format(intdiv($abs, 100), 0, ',', '.') . ',' . $fraction . ' ' . $symbol;
        }
        $number = number_format(intdiv($abs, 100), 0, '.', ',') . '.' . $fraction;

        return in_array($currency, self::PREFIXED, true) ? $sign . $symbol . $number : $sign . $number . ' ' . $symbol;
    }

    /** 799 -> "7.99" (en) or "7,99" (de) for form fields; parse() accepts both. */
    public static function plain(?int $cents, ?string $locale = null): string
    {
        if ($cents === null) {
            return '';
        }
        $locale ??= \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::locale();

        return intdiv($cents, 100) . ($locale === 'de' ? ',' : '.') . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
