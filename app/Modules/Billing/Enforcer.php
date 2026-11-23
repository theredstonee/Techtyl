<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Billing;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Services\Servers\SuspensionService;
use Pterodactyl\Services\Servers\ServerDeletionService;

/**
 * Bringt den Server eines Abos in den Zustand, den der Zahlungsstand verlangt: sperren,
 * entsperren und (nur über den zeitgesteuerten Lauf) löschen. Fasst ausschließlich Server an,
 * die dieses Modul selbst angelegt hat (external_id "techtyl-billing-<Abo>").
 */
class Enforcer
{
    /** Der Server des Abos, aber nur, wenn er nachweislich von diesem Modul stammt. */
    public static function ownServer(object $subscription): ?Server
    {
        if ($subscription->server_id === null) {
            return null;
        }
        $server = Server::query()->find((int) $subscription->server_id);
        if ($server === null || $server->external_id !== Billing::externalId((int) $subscription->id)) {
            return null;
        }

        return $server;
    }

    /** Soll der Server laut Zahlungsstand gesperrt sein? */
    public static function shouldBeSuspended(object $subscription): bool
    {
        if ($subscription->status === 'active') {
            return false;
        }
        if ($subscription->status === 'past_due' && $subscription->kind === 'stripe') {
            // Zahlungsausfall beim Stripe-Abo: erst nach der eingestellten Frist sperren.
            $since = $subscription->past_due_since !== null ? \Carbon\Carbon::parse($subscription->past_due_since) : now();

            return $since->copy()->addDays((int) Config::all()['suspend_days'])->lte(now());
        }

        // Guthaben reicht nicht (kind "balance") oder Abo beendet.
        return in_array($subscription->status, ['past_due', 'canceled'], true);
    }

    /** Sperrt oder entsperrt den Server passend zum Abo. Fehler werden protokolliert, nicht geworfen. */
    public static function sync(int $subscriptionId): void
    {
        $subscription = DB::table(Billing::SUBSCRIPTIONS)->where('id', $subscriptionId)->first();
        if ($subscription === null) {
            return;
        }
        $server = self::ownServer($subscription);
        if ($server === null) {
            return;
        }

        $want = self::shouldBeSuspended($subscription);
        try {
            if ($want && $subscription->suspended_at === null) {
                app(SuspensionService::class)->toggle($server, SuspensionService::ACTION_SUSPEND);
                DB::table(Billing::SUBSCRIPTIONS)->where('id', $subscriptionId)->update(['suspended_at' => now(), 'updated_at' => now()]);
                Billing::log('suspend', 'Server ' . $server->uuidShort . ' suspended (subscription status ' . $subscription->status . ').', $subscriptionId, (int) $subscription->user_id, (int) $server->id);
            } elseif (!$want && $subscription->suspended_at !== null) {
                // Nur entsperren, was dieses Modul selbst gesperrt hat (suspended_at ist gesetzt).
                app(SuspensionService::class)->toggle($server, SuspensionService::ACTION_UNSUSPEND);
                DB::table(Billing::SUBSCRIPTIONS)->where('id', $subscriptionId)->update(['suspended_at' => null, 'updated_at' => now()]);
                Billing::log('unsuspend', 'Server ' . $server->uuidShort . ' unsuspended.', $subscriptionId, (int) $subscription->user_id, (int) $server->id);
            }
        } catch (\Throwable $e) {
            Log::warning('techtyl billing: Sperren/Entsperren fehlgeschlagen, wird beim nächsten Lauf wiederholt', [
                'subscription' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Löscht Server, die länger als die Lösch-Frist gesperrt sind. Unumkehrbar – wird nur vom
     * zeitgesteuerten Lauf aufgerufen (Jobs::enforce).
     *
     * @return int Anzahl gelöschter Server
     */
    public static function deleteDue(): int
    {
        $limit = now()->subDays((int) Config::all()['delete_days']);
        $due = DB::table(Billing::SUBSCRIPTIONS)
            ->whereNotNull('server_id')
            ->whereNotNull('suspended_at')
            ->where('suspended_at', '<=', $limit)
            ->whereIn('status', ['past_due', 'canceled'])
            ->orderBy('id')
            ->limit(50)
            ->get();

        $deleted = 0;
        foreach ($due as $subscription) {
            $id = (int) $subscription->id;
            try {
                $server = self::ownServer($subscription);
                if ($server === null) {
                    Billing::log('delete-skipped', 'Server was not created by this module or no longer exists.', $id, (int) $subscription->user_id, (int) $subscription->server_id);
                    DB::table(Billing::SUBSCRIPTIONS)->where('id', $id)->update(['suspended_at' => null, 'updated_at' => now()]);
                    continue;
                }
                if (!$server->isSuspended()) {
                    // Jemand hat den Server von Hand entsperrt: nicht löschen, erneut einordnen.
                    DB::table(Billing::SUBSCRIPTIONS)->where('id', $id)->update(['suspended_at' => null, 'updated_at' => now()]);
                    Billing::log('delete-skipped', 'Server is no longer suspended.', $id, (int) $subscription->user_id, (int) $server->id);
                    continue;
                }

                // Ein noch laufendes Stripe-Abo wird zuerst beendet, damit nichts mehr abgebucht wird.
                if ($subscription->kind === 'stripe' && $subscription->status !== 'canceled' && $subscription->stripe_subscription_id !== null) {
                    Billing::stripe()->cancelSubscription((string) $subscription->stripe_subscription_id, 'techtyl-end-' . $id);
                }

                $serverId = (int) $server->id;
                $label = $server->uuidShort . ' "' . $server->name . '"';
                app(ServerDeletionService::class)->handle($server);

                DB::table(Billing::SUBSCRIPTIONS)->where('id', $id)->update([
                    'status' => 'ended',
                    'server_id' => null,
                    'server_deleted_at' => now(),
                    'ended_at' => $subscription->ended_at ?? now(),
                    'updated_at' => now(),
                ]);
                Billing::log('delete', 'Server ' . $label . ' deleted after the deletion period.', $id, (int) $subscription->user_id, $serverId);
                $deleted++;
            } catch (\Throwable $e) {
                Log::error('techtyl billing: Löschen fehlgeschlagen', ['subscription' => $id, 'error' => $e->getMessage()]);
                Billing::log('delete-failed', 'Deletion failed, will be retried on the next run.', $id, (int) $subscription->user_id, (int) $subscription->server_id);
            }
        }

        return $deleted;
    }
}
