<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Billing;

/**
 * Prüft den Header "Stripe-Signature" (Schema v1): HMAC-SHA256 über "<t>.<roher Body>" mit dem
 * Webhook-Secret, Vergleich zeitkonstant, Zeitstempel höchstens 5 Minuten alt.
 */
class Signature
{
    public const TOLERANCE = 300;

    public static function valid(string $payload, ?string $header, string $secret, ?int $now = null): bool
    {
        if ($secret === '' || $header === null || $header === '' || strlen($header) > 2000) {
            return false;
        }

        $timestamp = null;
        $signatures = [];
        // Reihenfolge und Anzahl der Einträge sind nicht festgelegt; unbekannte werden ignoriert.
        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            if ($pair[0] === 't' && ctype_digit($pair[1]) && strlen($pair[1]) <= 12) {
                $timestamp = (int) $pair[1];
            } elseif ($pair[0] === 'v1' && preg_match('/^[a-f0-9]{64}$/', $pair[1]) === 1) {
                $signatures[] = $pair[1];
            }
        }
        if ($timestamp === null || $signatures === []) {
            return false;
        }
        if (abs(($now ?? time()) - $timestamp) > self::TOLERANCE) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        $match = false;
        foreach ($signatures as $signature) {
            // Kein vorzeitiger Abbruch: jede Signatur wird zeitkonstant verglichen.
            $match = hash_equals($expected, $signature) || $match;
        }

        return $match;
    }

    /** Erzeugt einen gültigen Header (für Tests mit selbst signierten Ereignissen). */
    public static function header(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    }
}
