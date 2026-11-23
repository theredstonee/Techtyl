<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Billing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules;

/**
 * Zeitgesteuerte Läufe. Beide dürfen beliebig oft laufen: die Tagesabbuchung bucht je Abo und
 * Kalendertag höchstens einmal (eindeutige Buchungsreferenz), der Sperr-/Löschlauf stellt nur
 * den Soll-Zustand her.
 */
class Jobs
{
    private static function runnable(): bool
    {
        return Modules::enabled('billing') && Schema::hasTable(Billing::SUBSCRIPTIONS);
    }

    /**
     * Bucht den Tagespreis für ein Guthaben-Abo ab, falls heute noch nicht geschehen. Reicht das
     * Guthaben nicht, wird nichts gebucht und das Abo als "past_due" markiert (Server wird gesperrt).
     *
     * @return string "charged", "already", "insufficient" oder "skipped"
     */
    public static function chargeDay(int $subscriptionId): string
    {
        $subscription = DB::table(Billing::SUBSCRIPTIONS)->where('id', $subscriptionId)->first();
        if ($subscription === null || $subscription->kind !== 'balance' || $subscription->provision_state !== 'done'
            || $subscription->server_id === null || !in_array($subscription->status, ['active', 'past_due'], true)) {
            return 'skipped';
        }

        $today = now()->toDateString();
        if ($subscription->last_charged_on === $today) {
            return 'already';
        }

        try {
            $booked = Ledger::post(
                (int) $subscription->user_id,
                -(int) $subscription->price,
                'daily',
                'daily:' . $subscriptionId . ':' . $today,
                (string) $subscription->server_name
            );
        } catch (InsufficientFunds $e) {
            DB::table(Billing::SUBSCRIPTIONS)->where('id', $subscriptionId)->whereIn('status', ['active', 'past_due'])->update([
                'status' => 'past_due',
                'past_due_since' => $subscription->past_due_since ?? now(),
                'updated_at' => now(),
            ]);
            Enforcer::sync($subscriptionId);

            return 'insufficient';
        }

        DB::table(Billing::SUBSCRIPTIONS)->where('id', $subscriptionId)->whereIn('status', ['active', 'past_due'])->update([
            'status' => 'active',
            'past_due_since' => null,
            'last_charged_on' => $today,
            'updated_at' => now(),
        ]);
        Enforcer::sync($subscriptionId);

        return $booked ? 'charged' : 'already';
    }

    /** Nach einer Gutschrift: gesperrte Guthaben-Abos des Nutzers sofort wieder bezahlen und entsperren. */
    public static function resumeFor(int $userId): void
    {
        $ids = DB::table(Billing::SUBSCRIPTIONS)->where('user_id', $userId)->where('kind', 'balance')
            ->where('status', 'past_due')->orderBy('id')->pluck('id');
        foreach ($ids as $id) {
            try {
                self::chargeDay((int) $id);
            } catch (\Throwable $e) {
                Log::error('techtyl billing: Wiederaufnahme fehlgeschlagen', ['subscription' => $id, 'error' => $e->getMessage()]);
            }
        }
    }

    /** Tägliche Abbuchung aller Guthaben-Abos. @return array<string, int> Zähler je Ergebnis */
    public static function daily(): array
    {
        $result = ['charged' => 0, 'already' => 0, 'insufficient' => 0, 'skipped' => 0, 'ended' => 0, 'errors' => 0];
        if (!self::runnable()) {
            return $result;
        }

        // Guthaben-Abos, deren Server außerhalb des Moduls entfernt wurde, enden (nichts mehr abbuchen).
        $result['ended'] = DB::table(Billing::SUBSCRIPTIONS)->where('kind', 'balance')->where('provision_state', 'done')
            ->whereNull('server_id')->whereIn('status', ['active', 'past_due'])
            ->update(['status' => 'ended', 'ended_at' => now(), 'updated_at' => now()]);

        $today = now()->toDateString();
        DB::table(Billing::SUBSCRIPTIONS)->where('kind', 'balance')->where('provision_state', 'done')
            ->whereNotNull('server_id')->whereIn('status', ['active', 'past_due'])
            ->where(function ($query) use ($today) {
                $query->whereNull('last_charged_on')->orWhere('last_charged_on', '<', $today);
            })
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$result) {
                foreach ($rows as $row) {
                    try {
                        $result[self::chargeDay((int) $row->id)]++;
                    } catch (\Throwable $e) {
                        $result['errors']++;
                        Log::error('techtyl billing: Tagesabbuchung fehlgeschlagen', ['subscription' => $row->id, 'error' => $e->getMessage()]);
                    }
                }
            });

        return $result;
    }

    /** Sperr-/Löschlauf. @return array<string, int> */
    public static function enforce(): array
    {
        $result = ['expired' => 0, 'stuck' => 0, 'synced' => 0, 'deleted' => 0];
        if (!self::runnable()) {
            return $result;
        }

        // Nie bezahlte Bestellungen (Checkout läuft bei Stripe nach 24 Stunden ab).
        // Bestellungen mit verknüpftem Stripe-Abo warten ggf. noch auf eine verzögerte Zahlung.
        $result['expired'] = DB::table(Billing::SUBSCRIPTIONS)->where('status', 'pending')
            ->whereNull('stripe_subscription_id')
            ->where('created_at', '<', now()->subHours(25))
            ->update(['status' => 'expired', 'ended_at' => now(), 'updated_at' => now()]);

        // Abgebrochene Bereitstellungen sichtbar machen (der Admin kann sie erneut anstoßen).
        $stuck = DB::table(Billing::SUBSCRIPTIONS)->where('provision_state', 'provisioning')
            ->where('provision_started_at', '<', now()->subMinutes(Provisioner::STALE_MINUTES))->get(['id', 'user_id']);
        foreach ($stuck as $row) {
            $changed = DB::table(Billing::SUBSCRIPTIONS)->where('id', $row->id)->where('provision_state', 'provisioning')
                ->update(['provision_state' => 'failed', 'provision_error' => 'interrupted', 'updated_at' => now()]);
            if ($changed === 1) {
                $result['stuck']++;
                Billing::log('provision-failed', 'Provisioning was interrupted.', (int) $row->id, (int) $row->user_id);
            }
        }

        // Soll-Zustand herstellen: sperren nach Frist, entsperren nach Zahlung.
        $ids = DB::table(Billing::SUBSCRIPTIONS)->whereNotNull('server_id')
            ->where(function ($query) {
                $query->whereIn('status', ['past_due', 'canceled'])->orWhereNotNull('suspended_at');
            })
            ->orderBy('id')->limit(1000)->pluck('id');
        foreach ($ids as $id) {
            Enforcer::sync((int) $id);
            $result['synced']++;
        }

        $result['deleted'] = Enforcer::deleteDue();

        // Alte Ereignis-IDs (Stripe stellt höchstens einige Tage lang erneut zu).
        DB::table(Billing::EVENTS)->where('created_at', '<', now()->subDays(90))->delete();

        return $result;
    }
}
