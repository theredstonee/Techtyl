<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Billing;

use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Client-API der Abrechnung unter /api/client/extensions/techtyl/billing.
 * Alles ist an den angemeldeten Nutzer gebunden; fremde Abos ergeben 404. Preise, Währung und
 * Paketdaten kommen immer aus der Datenbank, nie aus der Anfrage.
 */
class ClientController extends Controller
{
    private const MAX_PENDING = 5;
    private const MAX_SUBSCRIPTIONS = 25;
    private const LEDGER_LIMIT = 50;
    private const SERVER_NAME_RULES = ['required', 'string', 'min:1', 'max:60', 'regex:/^[^\p{Cc}\p{Cf}<>]+$/u'];

    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = Config::all();
        $currency = $settings['currency'];

        $ledger = DB::table(Ledger::TABLE)->where('user_id', $user->id)->orderByDesc('id')->limit(self::LEDGER_LIMIT)
            ->get(['id', 'amount', 'balance_after', 'reason', 'note', 'created_at'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'amount' => (int) $row->amount,
                'balance_after' => (int) $row->balance_after,
                'reason' => $row->reason,
                'note' => $row->note,
                'created_at' => \Carbon\Carbon::parse($row->created_at)->toIso8601String(),
            ])->all();

        $subscriptions = DB::table(Billing::SUBSCRIPTIONS . ' as s')
            ->leftJoin('servers', 'servers.id', '=', 's.server_id')
            ->where('s.user_id', $user->id)
            ->whereNotIn('s.status', ['expired'])
            ->orderByRaw("FIELD(s.status, 'past_due', 'active', 'pending', 'canceled', 'ended')")
            ->orderByDesc('s.id')
            ->limit(100)
            ->get(['s.*', 'servers.name as live_name', 'servers.uuidShort as server_identifier'])
            ->map(fn ($row) => $this->subscriptionAttributes($row, $settings))->all();

        $dailyCost = 0;
        foreach ($subscriptions as $subscription) {
            if ($subscription['kind'] === 'balance' && in_array($subscription['status'], ['active', 'past_due'], true)) {
                $dailyCost += $subscription['price'];
            }
        }
        $balance = Ledger::balance($user->id);

        return new JsonResponse([
            'object' => 'techtyl_billing_overview',
            'attributes' => [
                'ready' => Config::ready(),
                'test_mode' => Config::mode() === 'test',
                'currency' => $currency,
                'balance' => $balance,
                'daily_cost' => $dailyCost,
                'days_left' => $dailyCost > 0 ? intdiv($balance, $dailyCost) : null,
                'topup_amounts' => $settings['topup_amounts'],
                'min_balance' => $settings['min_balance'],
                'suspend_days' => $settings['suspend_days'],
                'delete_days' => $settings['delete_days'],
                'terms_url' => $settings['terms_url'],
                'withdrawal_url' => $settings['withdrawal_url'],
                'has_customer' => DB::table(Ledger::ACCOUNTS)->where('user_id', $user->id)->whereNotNull('stripe_customer_id')->exists(),
                'ledger' => $ledger,
                'subscriptions' => $subscriptions,
                'packages' => $this->packages(),
            ],
        ]);
    }

    /** Aufladen: legt einen Stripe-Checkout über einen der erlaubten Beträge an. */
    public function topup(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = Config::all();
        $amount = $request->validate(['amount' => ['required', 'integer']], ['amount.*' => Billing::t('amount_not_offered')])['amount'];
        if (!in_array((int) $amount, $settings['topup_amounts'], true)) {
            throw ValidationException::withMessages(['amount' => [Billing::t('amount_not_offered')]]);
        }
        $amount = (int) $amount;
        $this->requireReady();
        $this->limitPending($user->id);

        $customer = $this->customer($user);
        $paymentId = DB::table(Billing::PAYMENTS)->insertGetId([
            'user_id' => $user->id,
            'kind' => 'topup',
            'status' => 'open',
            'amount' => $amount,
            'currency' => $settings['currency'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $session = Billing::stripe()->createCheckoutSession([
                'mode' => 'payment',
                'customer' => $customer,
                'client_reference_id' => 'topup-' . $paymentId,
                'success_url' => Config::returnUrl('ok'),
                'cancel_url' => Config::returnUrl('abbruch'),
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => $settings['currency'],
                        'unit_amount' => $amount,
                        'product_data' => ['name' => Billing::t('product_credit', ['amount' => Money::format($amount, $settings['currency'])])],
                    ],
                ]],
                'metadata' => ['techtyl_kind' => 'topup', 'techtyl_payment' => (string) $paymentId],
                'payment_intent_data' => ['metadata' => ['techtyl_kind' => 'topup', 'techtyl_payment' => (string) $paymentId]],
            ], 'techtyl-topup-' . $paymentId);
            $url = $this->checkoutUrl($session);
            DB::table(Billing::PAYMENTS)->where('id', $paymentId)->update(['stripe_ref' => (string) $session['id'], 'updated_at' => now()]);
        } catch (\Throwable $e) {
            DB::table(Billing::PAYMENTS)->where('id', $paymentId)->where('status', 'open')->whereNull('stripe_ref')->delete();

            return $this->stripeFailed($e, 'topup');
        }

        return new JsonResponse(['object' => 'techtyl_billing_redirect', 'attributes' => ['url' => $url]]);
    }

    /** Paket bestellen: als Stripe-Abo (Weiterleitung zum Checkout) oder aus dem Guthaben (sofort). */
    public function order(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'package_id' => ['required', 'integer', 'min:1'],
            'egg_id' => ['required', 'integer', 'min:1'],
            'server_name' => self::SERVER_NAME_RULES,
            'method' => ['required', 'string', 'in:stripe,balance'],
            'confirmed' => ['required', 'accepted'],
        ], [
            'confirmed.*' => Billing::t('confirm_required'),
            'server_name.regex' => Billing::t('name_chars'),
            'server_name.required' => Billing::t('name_required'),
            'server_name.*' => Billing::t('name_long'),
            'package_id.*' => Billing::t('package_gone'),
            'egg_id.*' => Billing::t('egg_unavailable'),
            'method.*' => Billing::t('method_invalid'),
        ]);
        $name = trim((string) $data['server_name']);
        if ($name === '') {
            throw ValidationException::withMessages(['server_name' => [Billing::t('name_required')]]);
        }

        // Paket und Preis ausschließlich aus der Datenbank; inaktive Pakete sind nicht bestellbar.
        $package = DB::table(Billing::PACKAGES)->where('id', (int) $data['package_id'])->where('active', true)->first();
        if ($package === null) {
            throw ValidationException::withMessages(['package_id' => [Billing::t('package_gone')]]);
        }
        $eggId = (int) $data['egg_id'];
        if (!in_array($eggId, Billing::ids($package->egg_ids), true) || !DB::table('eggs')->where('id', $eggId)->exists()) {
            throw ValidationException::withMessages(['egg_id' => [Billing::t('egg_unavailable')]]);
        }
        $settings = Config::all();
        $method = $data['method'];
        if ($method === 'balance' && $package->price_day === null) {
            throw ValidationException::withMessages(['method' => [Billing::t('no_balance_price')]]);
        }

        $count = DB::table(Billing::SUBSCRIPTIONS)->where('user_id', $user->id)->whereIn('status', ['pending', 'active', 'past_due'])->count();
        if ($count >= self::MAX_SUBSCRIPTIONS) {
            throw ValidationException::withMessages(['package_id' => [Billing::t('max_subscriptions')]]);
        }

        $row = [
            'user_id' => $user->id,
            'package_id' => (int) $package->id,
            'package_name' => $package->name,
            'spec' => json_encode(Billing::specOf($package)),
            'kind' => $method,
            'server_name' => $name,
            'egg_id' => $eggId,
            'currency' => $settings['currency'],
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($method === 'balance') {
            return $this->orderFromBalance($user, $row, (int) $package->price_day, (int) $settings['min_balance'], $settings);
        }

        $this->requireReady();
        $this->limitPending($user->id);
        $customer = $this->customer($user);
        $price = (int) $package->price_month;
        $subscriptionId = DB::table(Billing::SUBSCRIPTIONS)->insertGetId($row + ['status' => 'pending', 'price' => $price]);

        try {
            $session = Billing::stripe()->createCheckoutSession([
                'mode' => 'subscription',
                'customer' => $customer,
                'client_reference_id' => (string) $subscriptionId,
                'success_url' => Config::returnUrl('ok'),
                'cancel_url' => Config::returnUrl('abbruch'),
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => $settings['currency'],
                        'unit_amount' => $price,
                        'recurring' => ['interval' => 'month'],
                        'product_data' => ['name' => Billing::t('product_package', ['package' => $package->name])],
                    ],
                ]],
                'metadata' => ['techtyl_kind' => 'subscription', 'techtyl_subscription' => (string) $subscriptionId],
                'subscription_data' => ['metadata' => ['techtyl_subscription' => (string) $subscriptionId]],
            ], 'techtyl-order-' . $subscriptionId);
            $url = $this->checkoutUrl($session);
            DB::table(Billing::SUBSCRIPTIONS)->where('id', $subscriptionId)->update(['stripe_checkout_id' => (string) $session['id'], 'updated_at' => now()]);
        } catch (\Throwable $e) {
            DB::table(Billing::SUBSCRIPTIONS)->where('id', $subscriptionId)->where('status', 'pending')->whereNull('stripe_checkout_id')->delete();

            return $this->stripeFailed($e, 'order');
        }

        return new JsonResponse(['object' => 'techtyl_billing_redirect', 'attributes' => ['url' => $url]]);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $settings
     */
    private function orderFromBalance(User $user, array $row, int $priceDay, int $minBalance, array $settings): JsonResponse
    {
        Ledger::ensureAccount($user->id);

        // Unter der Zeilensperre des Kontos prüfen: das Guthaben muss den ersten Tag decken –
        // zusätzlich zu Bestellungen, deren erster Tag noch nicht abgebucht ist.
        $subscriptionId = DB::transaction(function () use ($user, $row, $priceDay, $minBalance) {
            $account = DB::table(Ledger::ACCOUNTS)->where('user_id', $user->id)->lockForUpdate()->first();
            $reserved = (int) DB::table(Billing::SUBSCRIPTIONS)->where('user_id', $user->id)->where('kind', 'balance')
                ->where('status', 'active')->whereNull('last_charged_on')->whereIn('provision_state', ['none', 'provisioning'])->sum('price');
            if ((int) $account->balance < max($minBalance, $priceDay) + $reserved) {
                return null;
            }

            return DB::table(Billing::SUBSCRIPTIONS)->insertGetId($row + ['status' => 'active', 'price' => $priceDay]);
        }, 3);

        if ($subscriptionId === null) {
            throw ValidationException::withMessages(['method' => [
                Billing::t('balance_short', ['amount' => Money::format(max($minBalance, $priceDay), $settings['currency'])]),
            ]]);
        }

        // Erst anlegen, dann den ersten Tag abbuchen: scheitert die Bereitstellung, kostet es nichts.
        $state = Provisioner::provision((int) $subscriptionId);
        if ($state === 'done') {
            Jobs::chargeDay((int) $subscriptionId);
        }

        $fresh = DB::table(Billing::SUBSCRIPTIONS . ' as s')->leftJoin('servers', 'servers.id', '=', 's.server_id')
            ->where('s.id', $subscriptionId)->first(['s.*', 'servers.name as live_name', 'servers.uuidShort as server_identifier']);

        return new JsonResponse([
            'object' => 'techtyl_billing_subscription',
            'attributes' => $this->subscriptionAttributes($fresh, $settings),
        ]);
    }

    /** Kündigen: Stripe-Abo zum Periodenende, Guthaben-Abo sofort. */
    public function cancel(Request $request, string $subscription): JsonResponse
    {
        $row = $this->own($request->user(), $subscription);

        if ($row->kind === 'stripe') {
            if (!in_array($row->status, ['active', 'past_due'], true) || $row->stripe_subscription_id === null) {
                throw ValidationException::withMessages(['subscription' => [Billing::t('cannot_cancel')]]);
            }
            if (!$row->cancel_at_period_end) {
                try {
                    Billing::stripe()->updateSubscription((string) $row->stripe_subscription_id, ['cancel_at_period_end' => true], 'techtyl-cancel-' . $row->id . '-' . time());
                } catch (\Throwable $e) {
                    return $this->stripeFailed($e, 'cancel');
                }
                DB::table(Billing::SUBSCRIPTIONS)->where('id', $row->id)->update(['cancel_at_period_end' => true, 'updated_at' => now()]);
                Billing::log('cancel-requested', 'Cancellation at period end requested by the user.', (int) $row->id, (int) $row->user_id, null, (int) $row->user_id);
            }
        } else {
            if (!in_array($row->status, ['active', 'past_due'], true)) {
                throw ValidationException::withMessages(['subscription' => [Billing::t('already_ended')]]);
            }
            DB::table(Billing::SUBSCRIPTIONS)->where('id', $row->id)->whereIn('status', ['active', 'past_due'])
                ->update(['status' => 'canceled', 'ended_at' => now(), 'updated_at' => now()]);
            Billing::log('canceled', 'Credit-funded subscription ended by the user.', (int) $row->id, (int) $row->user_id, null, (int) $row->user_id);
            Enforcer::sync((int) $row->id);
        }

        return $this->subscriptionResponse((int) $row->id);
    }

    /** Kündigung eines Stripe-Abos zurücknehmen, solange die Periode noch läuft. */
    public function resume(Request $request, string $subscription): JsonResponse
    {
        $row = $this->own($request->user(), $subscription);
        if ($row->kind !== 'stripe' || $row->status !== 'active' || !$row->cancel_at_period_end || $row->stripe_subscription_id === null) {
            throw ValidationException::withMessages(['subscription' => [Billing::t('nothing_to_resume')]]);
        }
        try {
            Billing::stripe()->updateSubscription((string) $row->stripe_subscription_id, ['cancel_at_period_end' => false], 'techtyl-resume-' . $row->id . '-' . time());
        } catch (\Throwable $e) {
            return $this->stripeFailed($e, 'resume');
        }
        DB::table(Billing::SUBSCRIPTIONS)->where('id', $row->id)->update(['cancel_at_period_end' => false, 'updated_at' => now()]);
        Billing::log('cancel-withdrawn', 'Cancellation withdrawn by the user.', (int) $row->id, (int) $row->user_id, null, (int) $row->user_id);

        return $this->subscriptionResponse((int) $row->id);
    }

    /** Stripe-Kundenportal (Zahlungsmittel, Rechnungen). */
    public function portal(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->requireReady();
        $customer = DB::table(Ledger::ACCOUNTS)->where('user_id', $user->id)->value('stripe_customer_id');
        if ($customer === null) {
            throw ValidationException::withMessages(['portal' => [Billing::t('portal_later')]]);
        }
        try {
            $session = Billing::stripe()->createPortalSession((string) $customer, Config::returnUrl('portal'));
            $url = $this->stripeUrl($session, 'billing.stripe.com');
        } catch (\Throwable $e) {
            return $this->stripeFailed($e, 'portal');
        }

        return new JsonResponse(['object' => 'techtyl_billing_redirect', 'attributes' => ['url' => $url]]);
    }

    // ------------------------------------------------------------------------------------------

    private function own(User $user, string $subscription): object
    {
        // Fremde und unbekannte Abos sind nicht unterscheidbar (404).
        $row = ctype_digit($subscription) && strlen($subscription) <= 10
            ? DB::table(Billing::SUBSCRIPTIONS)->where('id', (int) $subscription)->where('user_id', $user->id)->first()
            : null;
        if ($row === null) {
            throw new NotFoundHttpException();
        }

        return $row;
    }

    private function subscriptionResponse(int $id): JsonResponse
    {
        $row = DB::table(Billing::SUBSCRIPTIONS . ' as s')->leftJoin('servers', 'servers.id', '=', 's.server_id')
            ->where('s.id', $id)->first(['s.*', 'servers.name as live_name', 'servers.uuidShort as server_identifier']);

        return new JsonResponse([
            'object' => 'techtyl_billing_subscription',
            'attributes' => $this->subscriptionAttributes($row, Config::all()),
        ]);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed> Ohne Stripe-Kennungen.
     */
    private function subscriptionAttributes(object $row, array $settings): array
    {
        $iso = fn ($value) => $value !== null ? \Carbon\Carbon::parse($value)->toIso8601String() : null;
        $deleteOn = null;
        if ($row->suspended_at !== null && $row->server_id !== null) {
            $deleteOn = \Carbon\Carbon::parse($row->suspended_at)->addDays((int) $settings['delete_days'])->toIso8601String();
        }
        $suspendOn = null;
        if ($row->kind === 'stripe' && $row->status === 'past_due' && $row->suspended_at === null && $row->past_due_since !== null) {
            $suspendOn = \Carbon\Carbon::parse($row->past_due_since)->addDays((int) $settings['suspend_days'])->toIso8601String();
        }

        return [
            'id' => (int) $row->id,
            'kind' => $row->kind,
            'status' => $row->status,
            'package' => $row->package_name,
            'server_name' => $row->live_name ?? $row->server_name,
            'server_identifier' => $row->server_identifier,
            'price' => (int) $row->price,
            'currency' => $row->currency,
            'provision' => $row->provision_state,
            'current_period_end' => $iso($row->current_period_end),
            'cancel_at_period_end' => (bool) $row->cancel_at_period_end,
            'suspended' => $row->suspended_at !== null,
            'suspend_on' => $suspendOn,
            'delete_on' => $deleteOn,
            'server_deleted' => $row->server_deleted_at !== null,
            'created_at' => $iso($row->created_at),
        ];
    }

    /** @return array<int, array<string, mixed>> Aktive Pakete mit ihren wählbaren Server-Arten. */
    private function packages(): array
    {
        $packages = DB::table(Billing::PACKAGES)->where('active', true)->orderBy('position')->orderBy('id')->limit(50)->get();
        $eggIds = [];
        foreach ($packages as $package) {
            $eggIds = array_merge($eggIds, Billing::ids($package->egg_ids));
        }
        $eggs = DB::table('eggs')->join('nests', 'nests.id', '=', 'eggs.nest_id')->whereIn('eggs.id', array_unique($eggIds))
            ->orderBy('nests.name')->orderBy('eggs.name')->get(['eggs.id', 'eggs.name', 'nests.name as nest'])->keyBy('id');

        $result = [];
        foreach ($packages as $package) {
            $choices = [];
            foreach (Billing::ids($package->egg_ids) as $eggId) {
                if (isset($eggs[$eggId])) {
                    $choices[] = ['id' => $eggId, 'label' => $eggs[$eggId]->name, 'group' => $eggs[$eggId]->nest];
                }
            }
            if ($choices === []) {
                continue; // ohne wählbare Server-Art nicht bestellbar
            }
            $result[] = [
                'id' => (int) $package->id,
                'title' => $package->name,
                'description' => $package->description,
                'memory' => (int) $package->memory,
                'cpu' => (int) $package->cpu,
                'disk' => (int) $package->disk,
                'databases' => (int) $package->databases,
                'backups' => (int) $package->backups,
                'allocations' => (int) $package->allocations,
                'price_month' => (int) $package->price_month,
                'price_day' => $package->price_day !== null ? (int) $package->price_day : null,
                'eggs' => $choices,
            ];
        }

        return $result;
    }

    private function requireReady(): void
    {
        if (!Config::ready()) {
            throw ValidationException::withMessages(['billing' => [Billing::t('not_ready')]]);
        }
    }

    /** Begrenzt offene, nie bezahlte Checkouts je Nutzer (Schutz vor Massenanlage bei Stripe). */
    private function limitPending(int $userId): void
    {
        $since = now()->subDay();
        $open = DB::table(Billing::PAYMENTS)->where('user_id', $userId)->where('status', 'open')->where('created_at', '>', $since)->count()
            + DB::table(Billing::SUBSCRIPTIONS)->where('user_id', $userId)->where('status', 'pending')->where('created_at', '>', $since)->count();
        if ($open >= self::MAX_PENDING) {
            throw ValidationException::withMessages(['billing' => [Billing::t('too_many_open')]]);
        }
    }

    /** Stripe-Kunde des Nutzers; wird beim ersten Bedarf angelegt. */
    private function customer(User $user): string
    {
        Ledger::ensureAccount($user->id);
        $existing = DB::table(Ledger::ACCOUNTS)->where('user_id', $user->id)->value('stripe_customer_id');
        if ($existing !== null) {
            return (string) $existing;
        }

        try {
            $customer = Billing::stripe()->createCustomer([
                'email' => $user->email,
                'metadata' => ['techtyl_user' => (string) $user->id],
            ], 'techtyl-customer-' . Config::mode() . '-' . $user->id);
        } catch (\Throwable $e) {
            Log::error('techtyl billing: Stripe-Kunde konnte nicht angelegt werden', ['user' => $user->id, 'error' => $e->getMessage()]);
            throw ValidationException::withMessages(['billing' => [Billing::t('unreachable')]]);
        }
        $customerId = $customer['id'] ?? null;
        if (!is_string($customerId) || preg_match('/^cus_[A-Za-z0-9]{1,70}$/', $customerId) !== 1) {
            Log::error('techtyl billing: unerwartete Antwort beim Anlegen des Stripe-Kunden', ['user' => $user->id]);
            throw ValidationException::withMessages(['billing' => [Billing::t('unreachable')]]);
        }
        DB::table(Ledger::ACCOUNTS)->where('user_id', $user->id)->whereNull('stripe_customer_id')->update(['stripe_customer_id' => $customerId, 'updated_at' => now()]);

        return (string) DB::table(Ledger::ACCOUNTS)->where('user_id', $user->id)->value('stripe_customer_id');
    }

    /** @param array<string, mixed> $session */
    private function checkoutUrl(array $session): string
    {
        if (!is_string($session['id'] ?? null) || preg_match('/^cs_[A-Za-z0-9_]{1,110}$/', $session['id']) !== 1) {
            throw new StripeException('Unerwartete Checkout-Antwort.');
        }

        return $this->stripeUrl($session, 'checkout.stripe.com');
    }

    /**
     * Weiterleitungen gehen nur zu Stripe (https, Host stripe.com oder eine Subdomain davon).
     *
     * @param array<string, mixed> $object
     */
    private function stripeUrl(array $object, string $expectedHost): string
    {
        $url = $object['url'] ?? null;
        $parts = is_string($url) && strlen($url) <= 2000 ? parse_url($url) : false;
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])
            || ($host !== $expectedHost && !str_ends_with($host, '.stripe.com'))) {
            throw new StripeException('Unerwartete Weiterleitungsadresse.');
        }

        return $url;
    }

    private function stripeFailed(\Throwable $e, string $step): JsonResponse
    {
        Log::error('techtyl billing: Stripe-Aufruf fehlgeschlagen', ['step' => $step, 'error' => $e->getMessage()]);

        return new JsonResponse(['errors' => [[
            'code' => 'BillingUnavailable',
            'status' => '502',
            'detail' => Billing::t('unreachable_nothing_charged'),
        ]]], 502);
    }
}
