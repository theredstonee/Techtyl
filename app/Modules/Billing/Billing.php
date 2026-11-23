<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Billing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Gemeinsame Bausteine des Moduls: Tabellennamen, Stripe-Zugang, Protokoll. */
class Billing
{
    public const PACKAGES = 'techtyl_billing_packages';
    public const SUBSCRIPTIONS = 'techtyl_billing_subscriptions';
    public const PAYMENTS = 'techtyl_billing_payments';
    public const EVENTS = 'techtyl_billing_events';
    public const LOG = 'techtyl_billing_log';

    /** Kennung, die jeder von diesem Modul angelegte Server als external_id trägt. */
    public const EXTERNAL_PREFIX = 'techtyl-billing-';

    /**
     * Stripe-Zugang. Ist im Container eine Umsetzung von StripeGateway gebunden (Tests), wird sie
     * verwendet, sonst die echte HTTP-Umsetzung mit dem hinterlegten Schlüssel.
     */
    public static function stripe(): StripeGateway
    {
        if (app()->bound(StripeGateway::class)) {
            return app(StripeGateway::class);
        }

        return new HttpStripeGateway(Config::secretKey());
    }

    /** Text of this module in the current language (lang/billing.php). */
    public static function t(string $key, array $replace = []): string
    {
        return \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::t('billing.' . $key, $replace);
    }

    public static function externalId(int $subscriptionId): string
    {
        return self::EXTERNAL_PREFIX . $subscriptionId;
    }

    /** Schreibt eine Zeile ins Abrechnungs-Protokoll (und ins Panel-Log). Scheitert nie laut. */
    public static function log(string $action, string $detail = '', ?int $subscriptionId = null, ?int $userId = null, ?int $serverId = null, ?int $actorId = null): void
    {
        try {
            DB::table(self::LOG)->insert([
                'action' => substr($action, 0, 32),
                'subscription_id' => $subscriptionId,
                'user_id' => $userId,
                'server_id' => $serverId,
                'actor_id' => $actorId,
                'detail' => mb_substr($detail, 0, 400),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('techtyl billing: Protokoll nicht schreibbar', ['action' => $action, 'error' => $e->getMessage()]);
        }
        Log::info('techtyl billing: ' . $action, ['subscription' => $subscriptionId, 'user' => $userId, 'server' => $serverId, 'detail' => $detail]);
    }

    /** @return array<string, mixed> Technische Daten eines Pakets, wie sie am Abo festgehalten werden. */
    public static function specOf(object $package): array
    {
        return [
            'memory' => (int) $package->memory,
            'cpu' => (int) $package->cpu,
            'disk' => (int) $package->disk,
            'databases' => (int) $package->databases,
            'backups' => (int) $package->backups,
            'allocations' => (int) $package->allocations,
            'node_ids' => self::ids($package->node_ids),
            'location_ids' => self::ids($package->location_ids),
        ];
    }

    /** @return int[] JSON-Liste aus der Datenbank als Liste positiver Ganzzahlen. */
    public static function ids(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : $json;
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $decoded), fn ($id) => $id > 0)));
    }
}
