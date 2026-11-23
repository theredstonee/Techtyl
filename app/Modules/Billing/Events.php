<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Billing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Verarbeitet ein bereits signaturgeprüftes Stripe-Ereignis genau einmal.
 *
 * Ablauf: Ereignis-ID eintragen und alle Buchungen/Statuswechsel in EINER Transaktion; schlägt
 * etwas fehl, wird alles zurückgerollt und Stripe stellt erneut zu. Server anlegen, sperren und
 * entsperren passiert erst nach dem Commit.
 *
 * Beträge und Zuordnung kommen nie vom Browser: jede Zahlung wird gegen die Zeile geprüft, die
 * der Server beim Anlegen des Checkouts selbst geschrieben hat.
 */
class Events
{
    public const HANDLED = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'checkout.session.async_payment_failed',
        'checkout.session.expired',
        'invoice.paid',
        'invoice.payment_failed',
        'customer.subscription.updated',
        'customer.subscription.deleted',
        'charge.refunded',
    ];

    /** @return string "processed", "duplicate" oder "ignored" */
    public static function process(array $event): string
    {
        $id = $event['id'] ?? null;
        $type = $event['type'] ?? null;
        $object = data_get($event, 'data.object');
        if (!is_string($id) || preg_match('/^evt_[A-Za-z0-9_]{1,90}$/', $id) !== 1 || !is_string($type) || strlen($type) > 80 || !is_array($object)) {
            return 'ignored';
        }

        // Test-Ereignisse nie im Live-Betrieb verbuchen (und umgekehrt).
        if ((bool) ($event['livemode'] ?? false) !== (Config::mode() === 'live')) {
            Log::warning('techtyl billing: Ereignis aus dem anderen Stripe-Modus ignoriert', ['event' => $id, 'type' => $type]);

            return 'ignored';
        }

        $after = [];
        $outcome = DB::transaction(function () use ($id, $type, $object, $event, &$after) {
            // Eindeutige ID: eine zweite (auch gleichzeitige) Zustellung trägt nichts mehr ein.
            if (DB::table(Billing::EVENTS)->insertOrIgnore(['id' => $id, 'type' => $type, 'created_at' => now()]) === 0) {
                return 'duplicate';
            }

            switch ($type) {
                case 'checkout.session.completed':
                case 'checkout.session.async_payment_succeeded':
                    self::checkoutCompleted($object, $after);
                    break;
                case 'checkout.session.async_payment_failed':
                case 'checkout.session.expired':
                    self::checkoutClosed($object);
                    break;
                case 'invoice.paid':
                    self::invoicePaid($object, $after);
                    break;
                case 'invoice.payment_failed':
                    self::invoiceFailed($object, $after);
                    break;
                case 'customer.subscription.updated':
                case 'customer.subscription.deleted':
                    self::subscriptionChanged($object, $type === 'customer.subscription.deleted', (int) ($event['created'] ?? 0), $after);
                    break;
                case 'charge.refunded':
                    self::chargeRefunded($object);
                    break;
                default:
                    return 'ignored';
            }

            return 'processed';
        }, 3);

        // Nach dem Commit. Fehler hier machen die Zahlung nicht ungeschehen; der Sperr-/Löschlauf
        // und der Admin-Hinweis fangen sie auf.
        foreach ($after as $task) {
            try {
                $task();
            } catch (\Throwable $e) {
                Log::error('techtyl billing: Folgeschritt nach Ereignis fehlgeschlagen', ['event' => $id, 'error' => $e->getMessage()]);
            }
        }

        return $outcome;
    }

    private static function str(array $object, string $path): ?string
    {
        $value = data_get($object, $path);
        // Felder können je nach Abruf als ID oder als eingebettetes Objekt kommen.
        if (is_array($value)) {
            $value = $value['id'] ?? null;
        }

        return is_string($value) && $value !== '' && strlen($value) <= 120 ? $value : null;
    }

    private static function customerOf(int $userId): ?string
    {
        return DB::table(Ledger::ACCOUNTS)->where('user_id', $userId)->value('stripe_customer_id');
    }

    /** @param array<int, \Closure> $after */
    private static function checkoutCompleted(array $session, array &$after): void
    {
        $sessionId = self::str($session, 'id');
        $mode = $session['mode'] ?? '';
        $status = $session['payment_status'] ?? '';
        $currency = strtolower((string) ($session['currency'] ?? ''));
        $amount = $session['amount_subtotal'] ?? ($session['amount_total'] ?? null);
        if ($sessionId === null) {
            return;
        }

        if ($mode === 'payment') {
            $payment = DB::table(Billing::PAYMENTS)->where('stripe_ref', $sessionId)->where('kind', 'topup')->lockForUpdate()->first();
            if ($payment === null) {
                Log::warning('techtyl billing: Checkout ohne passende Aufladung', ['session' => $sessionId]);

                return;
            }
            if ($status !== 'paid' || $payment->status === 'paid') {
                return; // noch nicht bezahlt (verzögerte Zahlungsart) oder schon gutgeschrieben
            }
            if ($currency !== $payment->currency || !is_int($amount) || $amount !== (int) $payment->amount
                || self::str($session, 'customer') !== self::customerOf((int) $payment->user_id)) {
                DB::table(Billing::PAYMENTS)->where('id', $payment->id)->update(['status' => 'mismatch', 'updated_at' => now()]);
                Billing::log('payment-mismatch', 'Top-up ' . $payment->id . ': amount, currency or customer differ. Nothing credited.', null, (int) $payment->user_id);

                return;
            }

            Ledger::post((int) $payment->user_id, (int) $payment->amount, 'topup', 'topup:' . $payment->id);
            DB::table(Billing::PAYMENTS)->where('id', $payment->id)->update([
                'status' => 'paid',
                'stripe_payment_intent' => self::str($session, 'payment_intent'),
                'updated_at' => now(),
            ]);
            $userId = (int) $payment->user_id;
            $after[] = fn () => Jobs::resumeFor($userId);

            return;
        }

        if ($mode !== 'subscription') {
            return;
        }
        $subscription = DB::table(Billing::SUBSCRIPTIONS)->where('stripe_checkout_id', $sessionId)->where('kind', 'stripe')->lockForUpdate()->first();
        if ($subscription === null) {
            Log::warning('techtyl billing: Checkout ohne passende Bestellung', ['session' => $sessionId]);

            return;
        }
        $stripeSubscription = self::str($session, 'subscription');
        if ((string) ($session['client_reference_id'] ?? '') !== (string) $subscription->id
            || $currency !== $subscription->currency || !is_int($amount) || $amount !== (int) $subscription->price
            || $stripeSubscription === null
            || self::str($session, 'customer') !== self::customerOf((int) $subscription->user_id)) {
            Billing::log('payment-mismatch', 'Order: amount, currency, customer or reference differ. Not activated.', (int) $subscription->id, (int) $subscription->user_id);

            return;
        }

        $changes = ['updated_at' => now()];
        if ($subscription->stripe_subscription_id === null) {
            $changes['stripe_subscription_id'] = $stripeSubscription;
        } elseif ($subscription->stripe_subscription_id !== $stripeSubscription) {
            Billing::log('payment-mismatch', 'Order is already linked to another Stripe subscription.', (int) $subscription->id, (int) $subscription->user_id);

            return;
        }
        $paid = in_array($status, ['paid', 'no_payment_required'], true);
        if ($paid && $subscription->status === 'pending') {
            $changes['status'] = 'active';
        }
        DB::table(Billing::SUBSCRIPTIONS)->where('id', $subscription->id)->update($changes);

        if ($paid) {
            $subscriptionId = (int) $subscription->id;
            $after[] = fn () => Provisioner::provision($subscriptionId);
        }
    }

    /** Checkout abgelaufen oder verzögerte Zahlung gescheitert: offene Zeile schließen. */
    private static function checkoutClosed(array $session): void
    {
        $sessionId = self::str($session, 'id');
        if ($sessionId === null) {
            return;
        }
        DB::table(Billing::PAYMENTS)->where('stripe_ref', $sessionId)->where('status', 'open')
            ->update(['status' => 'expired', 'updated_at' => now()]);
        DB::table(Billing::SUBSCRIPTIONS)->where('stripe_checkout_id', $sessionId)->where('status', 'pending')
            ->update(['status' => 'expired', 'ended_at' => now(), 'updated_at' => now()]);
    }

    /** Stripe-Abo einer Rechnung; je nach API-Version an unterschiedlicher Stelle. */
    private static function invoiceSubscription(array $invoice): ?string
    {
        return self::str($invoice, 'parent.subscription_details.subscription') ?? self::str($invoice, 'subscription');
    }

    private static function findByInvoice(array $invoice): ?object
    {
        $stripeSubscription = self::invoiceSubscription($invoice);
        if ($stripeSubscription === null) {
            return null;
        }
        $subscription = DB::table(Billing::SUBSCRIPTIONS)->where('stripe_subscription_id', $stripeSubscription)->lockForUpdate()->first();
        if ($subscription !== null) {
            return $subscription;
        }

        // Die Rechnung kann vor "checkout.session.completed" eintreffen. Dann über die Kennung
        // zuordnen, die der Server beim Checkout am Stripe-Abo hinterlegt hat – aber nur, wenn
        // auch der Stripe-Kunde zum Nutzer der Bestellung gehört.
        $localId = data_get($invoice, 'parent.subscription_details.metadata.techtyl_subscription')
            ?? data_get($invoice, 'subscription_details.metadata.techtyl_subscription');
        if (!is_string($localId) || !ctype_digit($localId) || strlen($localId) > 10) {
            return null;
        }
        $subscription = DB::table(Billing::SUBSCRIPTIONS)->where('id', (int) $localId)->where('kind', 'stripe')
            ->where('status', 'pending')->whereNull('stripe_subscription_id')->lockForUpdate()->first();
        if ($subscription === null || self::str($invoice, 'customer') !== self::customerOf((int) $subscription->user_id)
            || strtolower((string) ($invoice['currency'] ?? '')) !== $subscription->currency) {
            return null;
        }
        DB::table(Billing::SUBSCRIPTIONS)->where('id', $subscription->id)->update(['stripe_subscription_id' => $stripeSubscription, 'updated_at' => now()]);
        $subscription->stripe_subscription_id = $stripeSubscription;

        return $subscription;
    }

    private static function recordInvoice(object $subscription, array $invoice, string $status): void
    {
        $invoiceId = self::str($invoice, 'id');
        if ($invoiceId === null) {
            return;
        }
        $amount = $status === 'paid' ? ($invoice['amount_paid'] ?? 0) : ($invoice['amount_due'] ?? 0);
        $values = [
            'user_id' => (int) $subscription->user_id,
            'subscription_id' => (int) $subscription->id,
            'kind' => 'invoice',
            'status' => $status,
            'amount' => is_int($amount) && $amount >= 0 ? $amount : 0,
            'currency' => substr(strtolower((string) ($invoice['currency'] ?? $subscription->currency)), 0, 3),
            'updated_at' => now(),
        ];
        $existing = DB::table(Billing::PAYMENTS)->where('stripe_ref', $invoiceId)->lockForUpdate()->first();
        if ($existing === null) {
            DB::table(Billing::PAYMENTS)->insert($values + ['stripe_ref' => $invoiceId, 'created_at' => now()]);
        } elseif ($existing->status !== 'paid' && (int) $existing->subscription_id === (int) $subscription->id) {
            DB::table(Billing::PAYMENTS)->where('id', $existing->id)->update($values);
        }
    }

    /** @param array<int, \Closure> $after */
    private static function invoicePaid(array $invoice, array &$after): void
    {
        $subscription = self::findByInvoice($invoice);
        if ($subscription === null) {
            return;
        }
        self::recordInvoice($subscription, $invoice, 'paid');

        $periodEnd = 0;
        foreach ((array) data_get($invoice, 'lines.data', []) as $line) {
            $end = data_get($line, 'period.end');
            if (is_int($end) && $end > $periodEnd) {
                $periodEnd = $end;
            }
        }

        if (in_array($subscription->status, ['pending', 'active', 'past_due'], true)) {
            $changes = ['status' => 'active', 'past_due_since' => null, 'updated_at' => now()];
            if ($periodEnd > 0) {
                $changes['current_period_end'] = \Carbon\Carbon::createFromTimestamp($periodEnd);
            }
            DB::table(Billing::SUBSCRIPTIONS)->where('id', $subscription->id)->update($changes);
            $subscriptionId = (int) $subscription->id;
            $after[] = fn () => Provisioner::provision($subscriptionId);
            $after[] = fn () => Enforcer::sync($subscriptionId);
        }
    }

    /** @param array<int, \Closure> $after */
    private static function invoiceFailed(array $invoice, array &$after): void
    {
        $subscription = self::findByInvoice($invoice);
        if ($subscription === null) {
            return;
        }
        self::recordInvoice($subscription, $invoice, 'failed');

        if ($subscription->status === 'active') {
            DB::table(Billing::SUBSCRIPTIONS)->where('id', $subscription->id)->update([
                'status' => 'past_due',
                'past_due_since' => $subscription->past_due_since ?? now(),
                'updated_at' => now(),
            ]);
            Billing::log('payment-failed', 'Subscription payment failed.', (int) $subscription->id, (int) $subscription->user_id);
            $subscriptionId = (int) $subscription->id;
            $after[] = fn () => Enforcer::sync($subscriptionId);
        }
    }

    /** @param array<int, \Closure> $after */
    private static function subscriptionChanged(array $object, bool $deleted, int $eventTime, array &$after): void
    {
        $stripeSubscription = self::str($object, 'id');
        if ($stripeSubscription === null) {
            return;
        }
        $subscription = DB::table(Billing::SUBSCRIPTIONS)->where('stripe_subscription_id', $stripeSubscription)->lockForUpdate()->first();
        if ($subscription === null) {
            return;
        }
        // Ältere Ereignisse, die verspätet eintreffen, überschreiben keinen neueren Stand.
        if ($eventTime < (int) $subscription->stripe_event_time) {
            return;
        }
        if (in_array($subscription->status, ['canceled', 'ended', 'expired'], true)) {
            return;
        }

        $changes = ['stripe_event_time' => $eventTime, 'updated_at' => now()];
        $stripeStatus = $deleted ? 'canceled' : (string) ($object['status'] ?? '');
        $periodEnd = $object['current_period_end'] ?? data_get($object, 'items.data.0.current_period_end');
        if (is_int($periodEnd) && $periodEnd > 0) {
            $changes['current_period_end'] = \Carbon\Carbon::createFromTimestamp($periodEnd);
        }
        $changes['cancel_at_period_end'] = !$deleted && (($object['cancel_at_period_end'] ?? false) === true || is_int($object['cancel_at'] ?? null));

        if (in_array($stripeStatus, ['canceled', 'incomplete_expired'], true)) {
            $changes['status'] = $subscription->status === 'pending' ? 'expired' : 'canceled';
            $changes['ended_at'] = now();
            $changes['cancel_at_period_end'] = false;
            Billing::log('canceled', 'Stripe subscription ended.', (int) $subscription->id, (int) $subscription->user_id);
        } elseif (in_array($stripeStatus, ['past_due', 'unpaid'], true) && $subscription->status === 'active') {
            $changes['status'] = 'past_due';
            $changes['past_due_since'] = $subscription->past_due_since ?? now();
        } elseif (in_array($stripeStatus, ['active', 'trialing'], true) && $subscription->status === 'past_due') {
            $changes['status'] = 'active';
            $changes['past_due_since'] = null;
        }
        DB::table(Billing::SUBSCRIPTIONS)->where('id', $subscription->id)->update($changes);

        $subscriptionId = (int) $subscription->id;
        $after[] = fn () => Enforcer::sync($subscriptionId);
    }

    /** Erstattung einer Aufladung: gutgeschriebenes Guthaben wieder abziehen (höchstens bis null). */
    private static function chargeRefunded(array $charge): void
    {
        $intent = self::str($charge, 'payment_intent');
        $refunded = $charge['amount_refunded'] ?? null;
        if ($intent === null || !is_int($refunded) || $refunded <= 0) {
            return;
        }
        $payment = DB::table(Billing::PAYMENTS)->where('stripe_payment_intent', $intent)->where('kind', 'topup')->lockForUpdate()->first();
        if ($payment === null || $payment->status !== 'paid') {
            return;
        }
        $refunded = min($refunded, (int) $payment->amount);
        $delta = $refunded - (int) $payment->refunded;
        if ($delta <= 0) {
            return;
        }

        $taken = Ledger::debitUpTo((int) $payment->user_id, $delta, 'refund', 'refund:' . $payment->id . ':' . $refunded);
        DB::table(Billing::PAYMENTS)->where('id', $payment->id)->update(['refunded' => $refunded, 'updated_at' => now()]);
        $detail = 'Top-up ' . $payment->id . ': ' . Money::format($delta, $payment->currency, 'en') . ' refunded, ' . Money::format($taken, $payment->currency, 'en') . ' taken from the credit balance.';
        if ($taken < $delta) {
            $detail .= ' The rest had already been spent.';
        }
        Billing::log('refund', $detail, null, (int) $payment->user_id);
    }
}
