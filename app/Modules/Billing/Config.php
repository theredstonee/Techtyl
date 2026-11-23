<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Billing;

use Illuminate\Support\Facades\Log;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Client\BlueprintClientLibrary;

/**
 * Admin-Einstellungen der Abrechnung (ein JSON-Datensatz). Die beiden Stripe-Geheimnisse liegen
 * verschlüsselt darin und verlassen den Server nie.
 */
class Config
{
    public const TABLE = 'techtyl';
    public const RECORD = 'billing_settings';

    public const DEFAULTS = [
        'secret_key' => '',
        'webhook_secret' => '',
        'currency' => 'eur',
        'suspend_days' => 3,
        'delete_days' => 14,
        'topup_amounts' => [500, 1000, 2500, 5000],
        'min_balance' => 100,
        'terms_url' => '',
        'withdrawal_url' => '',
    ];

    /** @var array<string, mixed>|null */
    private static ?array $loaded = null;

    /** @return array<string, mixed> */
    public static function all(): array
    {
        if (self::$loaded === null) {
            $settings = self::DEFAULTS;
            $stored = app(BlueprintClientLibrary::class)->dbGet(self::TABLE, self::RECORD, '{}');
            $decoded = is_string($stored) ? json_decode($stored, true) : null;
            if (is_array($decoded)) {
                foreach (['secret_key', 'webhook_secret', 'terms_url', 'withdrawal_url'] as $key) {
                    if (is_string($decoded[$key] ?? null)) {
                        $settings[$key] = $decoded[$key];
                    }
                }
                if (isset(Money::CURRENCIES[$decoded['currency'] ?? ''])) {
                    $settings['currency'] = $decoded['currency'];
                }
                foreach (['suspend_days' => [0, 60], 'delete_days' => [1, 365], 'min_balance' => [0, Money::MAX_CENTS]] as $key => [$min, $max]) {
                    if (is_int($decoded[$key] ?? null)) {
                        $settings[$key] = max($min, min($max, $decoded[$key]));
                    }
                }
                if (is_array($decoded['topup_amounts'] ?? null)) {
                    $amounts = array_values(array_unique(array_filter(
                        $decoded['topup_amounts'],
                        fn ($value) => is_int($value) && $value >= 100 && $value <= Money::MAX_CENTS
                    )));
                    sort($amounts);
                    $settings['topup_amounts'] = array_slice($amounts, 0, 8);
                }
            }
            self::$loaded = $settings;
        }

        return self::$loaded;
    }

    /** @param array<string, mixed> $changes Nur bekannte Schlüssel; Geheimnisse bereits verschlüsselt. */
    public static function save(array $changes): void
    {
        $settings = array_merge(self::all(), array_intersect_key($changes, self::DEFAULTS));
        app(BlueprintClientLibrary::class)->dbSet(self::TABLE, self::RECORD, json_encode($settings));
        self::$loaded = null;
    }

    public static function flush(): void
    {
        self::$loaded = null;
    }

    public static function currency(): string
    {
        return self::all()['currency'];
    }

    public static function secretKey(): string
    {
        return self::decrypt(self::all()['secret_key']);
    }

    public static function webhookSecret(): string
    {
        return self::decrypt(self::all()['webhook_secret']);
    }

    /** Zahlungen sind möglich, sobald beide Geheimnisse hinterlegt sind. */
    public static function ready(): bool
    {
        return self::secretKey() !== '' && self::webhookSecret() !== '';
    }

    /** "test", "live" oder "" (kein Schlüssel). Abgeleitet aus dem Präfix des Secret Keys. */
    public static function mode(): string
    {
        $key = self::secretKey();
        if ($key === '') {
            return '';
        }

        return preg_match('/^(sk|rk)_live_/', $key) === 1 ? 'live' : 'test';
    }

    /** Panel-Adresse der Abrechnungsseite; Rückkehr-Ziel für Stripe. Nie aus Browser-Eingaben. */
    public static function returnUrl(string $state): string
    {
        return rtrim((string) config('app.url'), '/') . '/account/billing?zahlung=' . $state;
    }

    private static function decrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }
        try {
            $plain = decrypt($value);

            return is_string($plain) ? $plain : '';
        } catch (\Throwable $e) {
            Log::error('techtyl billing: gespeichertes Stripe-Geheimnis nicht entschlüsselbar');

            return '';
        }
    }
}
