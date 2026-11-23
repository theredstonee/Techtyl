<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Billing;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Admin-Reiter "Abrechnung": Daten für die Ansicht und alle Aktionen. */
class Admin
{
    public static function data(): array
    {
        $settings = Config::all();
        $currency = $settings['currency'];
        $ready = Schema::hasTable(Billing::SUBSCRIPTIONS);
        $dateFormat = Billing::t('date_format');
        $money = fn ($cents) => $cents === null ? '–' : Money::format((int) $cents, $currency);

        $data = [
            'bt' => fn (string $key, array $replace = []) => Billing::t($key, $replace),
            'billingTables' => $ready,
            'billingSettings' => [
                'currency' => $currency,
                'suspend_days' => $settings['suspend_days'],
                'delete_days' => $settings['delete_days'],
                'topup_amounts' => implode(' ; ', array_map(fn ($cents) => Money::plain($cents), $settings['topup_amounts'])),
                'min_balance' => Money::plain($settings['min_balance']),
                'terms_url' => $settings['terms_url'],
                'withdrawal_url' => $settings['withdrawal_url'],
            ],
            'billingSecretSet' => Config::secretKey() !== '',
            'billingWebhookSet' => Config::webhookSecret() !== '',
            'billingMode' => Config::mode(),
            'billingWebhookUrl' => rtrim((string) config('app.url'), '/') . '/extensions/techtyl/billing/webhook',
            'billingEvents' => Events::HANDLED,
            'billingApiVersion' => HttpStripeGateway::API_VERSION,
            'billingCurrencies' => array_keys(Money::CURRENCIES),
            'billingCurrencyLocked' => $ready && self::currencyLocked(),
            'billingPackages' => [],
            'billingEdit' => null,
            'billingEggs' => [],
            'billingNodes' => [],
            'billingLocations' => [],
            'billingSubscriptions' => [],
            'billingPayments' => [],
            'billingAccounts' => [],
            'billingLog' => [],
            'billingFailed' => [],
            'billingStats' => [],
        ];
        if (!$ready) {
            return $data;
        }

        $data['billingEggs'] = DB::table('eggs')->join('nests', 'nests.id', '=', 'eggs.nest_id')
            ->orderBy('nests.name')->orderBy('eggs.name')->get(['eggs.id', 'eggs.name', 'nests.name as nest'])->all();
        $data['billingNodes'] = DB::table('nodes')->orderBy('name')->get(['id', 'name'])->all();
        $data['billingLocations'] = DB::table('locations')->orderBy('short')->get(['id', 'short'])->all();

        $editId = request()->query('edit');
        foreach (DB::table(Billing::PACKAGES)->orderBy('position')->orderBy('id')->get() as $package) {
            $row = [
                'id' => (int) $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'memory' => (int) $package->memory,
                'cpu' => (int) $package->cpu,
                'disk' => (int) $package->disk,
                'databases' => (int) $package->databases,
                'backups' => (int) $package->backups,
                'allocations' => (int) $package->allocations,
                'egg_ids' => Billing::ids($package->egg_ids),
                'node_ids' => Billing::ids($package->node_ids),
                'location_ids' => Billing::ids($package->location_ids),
                'price_month' => Money::plain((int) $package->price_month),
                'price_day' => Money::plain($package->price_day !== null ? (int) $package->price_day : null),
                'price_month_text' => $money($package->price_month),
                'price_day_text' => $money($package->price_day),
                'active' => (bool) $package->active,
                'position' => (int) $package->position,
            ];
            $data['billingPackages'][] = $row;
            if (is_string($editId) && ctype_digit($editId) && (int) $editId === $row['id']) {
                $data['billingEdit'] = $row;
            }
        }

        $statusText = [
            'pending' => Billing::t('st_pending'), 'active' => Billing::t('st_active'), 'past_due' => Billing::t('st_past_due'),
            'canceled' => Billing::t('st_canceled'), 'ended' => Billing::t('st_ended'), 'expired' => Billing::t('st_expired'),
        ];
        $subscriptions = DB::table(Billing::SUBSCRIPTIONS . ' as s')
            ->join('users', 'users.id', '=', 's.user_id')
            ->leftJoin('servers', 'servers.id', '=', 's.server_id')
            ->where('s.status', '!=', 'expired')
            ->orderByDesc('s.id')->limit(100)
            ->get(['s.*', 'users.username', 'servers.uuidShort', 'servers.name as live_name']);
        foreach ($subscriptions as $row) {
            $entry = [
                'id' => (int) $row->id,
                'user' => $row->username,
                'package' => $row->package_name,
                'server' => $row->live_name ?? $row->server_name,
                'server_identifier' => $row->uuidShort,
                'server_id' => $row->server_id,
                'kind' => $row->kind === 'stripe' ? Billing::t('kind_stripe') : Billing::t('kind_balance'),
                'price' => Money::format((int) $row->price, $row->currency) . ' / ' . ($row->kind === 'stripe' ? Billing::t('per_month') : Billing::t('per_day')),
                'status' => $row->status,
                'status_text' => ($statusText[$row->status] ?? $row->status)
                    . ($row->suspended_at !== null ? ', ' . Billing::t('st_suspended') : '')
                    . ($row->cancel_at_period_end ? ', ' . Billing::t('st_cancel_at_end') : ''),
                'next' => $row->current_period_end !== null ? \Carbon\Carbon::parse($row->current_period_end)->format($dateFormat) : '–',
                'provision' => $row->provision_state,
                'provision_error' => $row->provision_error !== '' ? Billing::t('prov_' . (in_array($row->provision_error, ['no_space', 'interrupted'], true) ? $row->provision_error : 'error')) : '',
                'can_end' => in_array($row->status, ['active', 'past_due'], true),
                'missing_server' => $row->provision_state === 'done' && $row->server_id === null && in_array($row->status, ['active', 'past_due'], true),
            ];
            $data['billingSubscriptions'][] = $entry;
            if ($row->provision_state === 'failed' && $row->status === 'active') {
                $data['billingFailed'][] = $entry;
            }
        }

        $kindText = ['topup' => Billing::t('pay_topup'), 'invoice' => Billing::t('pay_invoice')];
        $paymentText = ['open' => Billing::t('pay_open'), 'paid' => Billing::t('pay_paid'), 'failed' => Billing::t('pay_failed'), 'expired' => Billing::t('pay_expired'), 'mismatch' => Billing::t('pay_mismatch')];
        foreach (DB::table(Billing::PAYMENTS . ' as p')->join('users', 'users.id', '=', 'p.user_id')
            ->orderByDesc('p.id')->limit(50)->get(['p.*', 'users.username']) as $row) {
            $data['billingPayments'][] = [
                'when' => \Carbon\Carbon::parse($row->created_at)->format($dateFormat . ' H:i'),
                'user' => $row->username,
                'kind' => $kindText[$row->kind] ?? $row->kind,
                'amount' => Money::format((int) $row->amount, $row->currency),
                'refunded' => (int) $row->refunded > 0 ? Money::format((int) $row->refunded, $row->currency) : '',
                'status' => $paymentText[$row->status] ?? $row->status,
                'reference' => (string) $row->stripe_ref,
            ];
        }

        foreach (DB::table(Ledger::ACCOUNTS . ' as a')->join('users', 'users.id', '=', 'a.user_id')
            ->orderByDesc('a.balance')->orderBy('users.username')->limit(200)->get(['a.user_id', 'a.balance', 'users.username', 'users.email']) as $row) {
            $data['billingAccounts'][] = [
                'user_id' => (int) $row->user_id,
                'user' => $row->username,
                'email' => $row->email,
                'balance' => $money($row->balance),
            ];
        }

        foreach (DB::table(Billing::LOG . ' as l')->leftJoin('users', 'users.id', '=', 'l.user_id')
            ->leftJoin('users as actors', 'actors.id', '=', 'l.actor_id')
            ->orderByDesc('l.id')->limit(60)->get(['l.*', 'users.username', 'actors.username as actor']) as $row) {
            $data['billingLog'][] = [
                'when' => \Carbon\Carbon::parse($row->created_at)->format($dateFormat . ' H:i'),
                'action' => $row->action,
                'user' => $row->username ?? '–',
                'actor' => $row->actor ?? Billing::t('system'),
                'subscription' => $row->subscription_id,
                'detail' => $row->detail,
            ];
        }

        $data['billingStats'] = [
            Billing::t('stat_active') => DB::table(Billing::SUBSCRIPTIONS)->where('status', 'active')->count(),
            Billing::t('stat_past_due') => DB::table(Billing::SUBSCRIPTIONS)->where('status', 'past_due')->count(),
            Billing::t('stat_balance') => $money(DB::table(Ledger::ACCOUNTS)->sum('balance')),
            Billing::t('stat_monthly') => $money(DB::table(Billing::SUBSCRIPTIONS)->where('kind', 'stripe')->where('status', 'active')->where('cancel_at_period_end', false)->sum('price')),
        ];

        return $data;
    }

    public static function handle(Request $request, string $action): string
    {
        return match ($action) {
            'settings' => self::saveSettings($request),
            'rules' => self::saveRules($request),
            'test' => self::testConnection(),
            'package-save' => self::savePackage($request),
            'package-delete' => self::deletePackage($request),
            'credit' => self::credit($request),
            'provision-retry' => self::retryProvision($request),
            'subscription-end' => self::endSubscription($request),
            default => abort(404),
        };
    }

    private static function currencyLocked(): bool
    {
        return DB::table(Ledger::TABLE)->exists()
            || DB::table(Billing::SUBSCRIPTIONS)->whereIn('status', ['pending', 'active', 'past_due'])->exists();
    }

    private static function saveSettings(Request $request): string
    {
        $data = $request->validate([
            'secret_key' => ['nullable', 'string', 'max:255', 'regex:/^(sk|rk)_(test|live)_[A-Za-z0-9]{10,}$/'],
            'webhook_secret' => ['nullable', 'string', 'max:255', 'regex:/^whsec_[A-Za-z0-9+\/=_-]{10,}$/'],
            'currency' => ['required', 'string', 'in:' . implode(',', array_keys(Money::CURRENCIES))],
        ], [
            'secret_key.*' => Billing::t('a_secret_format'),
            'webhook_secret.*' => Billing::t('a_webhook_format'),
            'currency.*' => Billing::t('a_currency_invalid'),
        ]);

        $changes = [];
        if ($data['currency'] !== Config::currency()) {
            if (self::currencyLocked()) {
                throw ValidationException::withMessages(['currency' => [Billing::t('a_currency_locked')]]);
            }
            $changes['currency'] = $data['currency'];
        }
        // Leere Felder lassen das gespeicherte Geheimnis unverändert.
        if (!empty($data['secret_key'])) {
            $changes['secret_key'] = encrypt($data['secret_key']);
        }
        if (!empty($data['webhook_secret'])) {
            $changes['webhook_secret'] = encrypt($data['webhook_secret']);
        }
        Config::save($changes);
        Billing::log('settings', 'Stripe settings changed' . (isset($changes['secret_key']) ? ' (new secret key)' : '') . (isset($changes['webhook_secret']) ? ' (new webhook secret)' : '') . '.', null, null, null, $request->user()?->id);

        return Billing::t('a_stripe_saved');
    }

    private static function saveRules(Request $request): string
    {
        $url = ['nullable', 'string', 'max:300', 'regex:/^https?:\/\/[^\s<>"\']+$/i'];
        $data = $request->validate([
            'suspend_days' => ['required', 'integer', 'min:0', 'max:60'],
            'delete_days' => ['required', 'integer', 'min:1', 'max:365'],
            'topup_amounts' => ['required', 'string', 'max:120'],
            'min_balance' => ['required', 'string', 'max:12'],
            'terms_url' => $url,
            'withdrawal_url' => $url,
        ], [
            'terms_url.*' => Billing::t('a_url_format'),
            'withdrawal_url.*' => Billing::t('a_url_format'),
            'suspend_days.*' => Billing::t('a_suspend_days_range'),
            'delete_days.*' => Billing::t('a_delete_days_range'),
            'topup_amounts.*' => Billing::t('a_topups_format'),
            'min_balance.*' => Billing::t('a_amount_format'),
        ]);

        $amounts = [];
        foreach (preg_split('/\s*;\s*/', trim($data['topup_amounts']), -1, PREG_SPLIT_NO_EMPTY) as $part) {
            $cents = Money::parse($part);
            if ($cents === null || $cents < 100) {
                throw ValidationException::withMessages(['topup_amounts' => [Billing::t('a_topups_format')]]);
            }
            $amounts[] = $cents;
        }
        $amounts = array_values(array_unique($amounts));
        sort($amounts);
        if ($amounts === [] || count($amounts) > 8) {
            throw ValidationException::withMessages(['topup_amounts' => [Billing::t('a_topups_count')]]);
        }
        $minBalance = Money::parse($data['min_balance']);
        if ($minBalance === null) {
            throw ValidationException::withMessages(['min_balance' => [Billing::t('a_amount_format')]]);
        }

        Config::save([
            'suspend_days' => (int) $data['suspend_days'],
            'delete_days' => (int) $data['delete_days'],
            'topup_amounts' => $amounts,
            'min_balance' => $minBalance,
            'terms_url' => (string) ($data['terms_url'] ?? ''),
            'withdrawal_url' => (string) ($data['withdrawal_url'] ?? ''),
        ]);
        Billing::log('settings', 'Rules changed: suspend after ' . (int) $data['suspend_days'] . ' days, delete after ' . (int) $data['delete_days'] . ' days.', null, null, null, $request->user()?->id);

        return Billing::t('a_rules_saved');
    }

    private static function testConnection(): string
    {
        if (Config::secretKey() === '') {
            throw ValidationException::withMessages(['secret_key' => [Billing::t('a_no_key')]]);
        }
        try {
            $balance = Billing::stripe()->ping();
        } catch (StripeException $e) {
            Log::warning('techtyl billing: Verbindungstest fehlgeschlagen', ['error' => $e->getMessage()]);
            $hint = match (true) {
                $e->status === 401 => Billing::t('a_test_401'),
                $e->status === 403 => Billing::t('a_test_403'),
                $e->status === 0 => Billing::t('a_test_0'),
                default => Billing::t('a_test_status', ['status' => $e->status]),
            };
            throw ValidationException::withMessages(['secret_key' => [Billing::t('a_test_failed', ['hint' => $hint])]]);
        }
        $live = ($balance['livemode'] ?? false) === true;

        return Billing::t($live ? 'a_test_ok_live' : 'a_test_ok_test');
    }

    private static function savePackage(Request $request): string
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'min:1', 'max:60', 'regex:/^[^\p{Cc}\p{Cf}<>]+$/u'],
            'description' => ['nullable', 'string', 'max:300', 'regex:/^[^\p{Cc}\p{Cf}<>]*$/u'],
            'memory' => ['required', 'integer', 'min:64', 'max:1048576'],
            'cpu' => ['required', 'integer', 'min:10', 'max:12800'],
            'disk' => ['required', 'integer', 'min:128', 'max:10485760'],
            'databases' => ['required', 'integer', 'min:0', 'max:100'],
            'backups' => ['required', 'integer', 'min:0', 'max:100'],
            'allocations' => ['required', 'integer', 'min:0', 'max:100'],
            'egg_ids' => ['required', 'array', 'min:1', 'max:200'],
            'egg_ids.*' => ['integer', 'exists:eggs,id'],
            'node_ids' => ['nullable', 'array', 'max:200'],
            'node_ids.*' => ['integer', 'exists:nodes,id'],
            'location_ids' => ['nullable', 'array', 'max:200'],
            'location_ids.*' => ['integer', 'exists:locations,id'],
            'price_month' => ['required', 'string', 'max:12'],
            'price_day' => ['nullable', 'string', 'max:12'],
            'active' => ['nullable', 'boolean'],
            'position' => ['required', 'integer', 'min:-9999', 'max:9999'],
        ], [
            'egg_ids.required' => Billing::t('a_eggs_required'),
            'egg_ids.*' => Billing::t('a_eggs_invalid'),
            'egg_ids.*.*' => Billing::t('a_eggs_invalid'),
            'name.*' => Billing::t('a_name_invalid'),
            'description.*' => Billing::t('a_description_invalid'),
            'memory.*' => Billing::t('a_number_range', ['field' => 'RAM']),
            'cpu.*' => Billing::t('a_number_range', ['field' => 'CPU']),
            'disk.*' => Billing::t('a_number_range', ['field' => Billing::t('f_disk')]),
            'databases.*' => Billing::t('a_number_range', ['field' => Billing::t('f_databases')]),
            'backups.*' => Billing::t('a_number_range', ['field' => Billing::t('f_backups')]),
            'allocations.*' => Billing::t('a_number_range', ['field' => Billing::t('f_allocations')]),
            'position.*' => Billing::t('a_number_range', ['field' => Billing::t('f_position')]),
            'node_ids.*' => Billing::t('a_selection_invalid'),
            'node_ids.*.*' => Billing::t('a_selection_invalid'),
            'location_ids.*' => Billing::t('a_selection_invalid'),
            'location_ids.*.*' => Billing::t('a_selection_invalid'),
            'price_month.*' => Billing::t('a_price_month_format'),
            'price_day.*' => Billing::t('a_price_day_format'),
        ]);

        $priceMonth = Money::parse($data['price_month']);
        if ($priceMonth === null || $priceMonth < 50) {
            throw ValidationException::withMessages(['price_month' => [Billing::t('a_price_month_format')]]);
        }
        $priceDay = null;
        if (($data['price_day'] ?? '') !== '' && $data['price_day'] !== null) {
            $priceDay = Money::parse($data['price_day']);
            if ($priceDay === null || $priceDay < 1) {
                throw ValidationException::withMessages(['price_day' => [Billing::t('a_price_day_format')]]);
            }
        }

        $row = [
            'name' => trim($data['name']),
            'description' => trim((string) ($data['description'] ?? '')),
            'memory' => (int) $data['memory'],
            'cpu' => (int) $data['cpu'],
            'disk' => (int) $data['disk'],
            'databases' => (int) $data['databases'],
            'backups' => (int) $data['backups'],
            'allocations' => (int) $data['allocations'],
            'egg_ids' => json_encode(Billing::ids($data['egg_ids'])),
            'node_ids' => json_encode(Billing::ids($data['node_ids'] ?? [])),
            'location_ids' => json_encode(Billing::ids($data['location_ids'] ?? [])),
            'price_month' => $priceMonth,
            'price_day' => $priceDay,
            'active' => (bool) ($data['active'] ?? false),
            'position' => (int) $data['position'],
            'updated_at' => now(),
        ];

        if (!empty($data['id'])) {
            if (DB::table(Billing::PACKAGES)->where('id', (int) $data['id'])->update($row) === 0
                && !DB::table(Billing::PACKAGES)->where('id', (int) $data['id'])->exists()) {
                throw ValidationException::withMessages(['id' => [Billing::t('package_gone')]]);
            }

            return Billing::t('a_package_saved');
        }
        if (DB::table(Billing::PACKAGES)->count() >= 50) {
            throw ValidationException::withMessages(['name' => [Billing::t('a_package_max')]]);
        }
        DB::table(Billing::PACKAGES)->insert($row + ['created_at' => now()]);

        return Billing::t('a_package_created');
    }

    private static function deletePackage(Request $request): string
    {
        $id = (int) $request->validate(['id' => ['required', 'integer', 'min:1']])['id'];
        DB::table(Billing::PACKAGES)->where('id', $id)->delete();

        return Billing::t('a_package_deleted');
    }

    private static function credit(Request $request): string
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'direction' => ['required', 'string', 'in:credit,debit'],
            'amount' => ['required', 'string', 'max:12'],
            'reason' => ['required', 'string', 'min:3', 'max:200', 'regex:/^[^\p{Cc}\p{Cf}<>]+$/u'],
        ], [
            'reason.*' => Billing::t('a_reason_required'),
            'user_id.*' => Billing::t('a_user_unknown'),
            'amount.*' => Billing::t('a_amount_format'),
        ]);
        $cents = Money::parse($data['amount']);
        if ($cents === null || $cents < 1) {
            throw ValidationException::withMessages(['amount' => [Billing::t('a_amount_format')]]);
        }
        $userId = (int) $data['user_id'];
        $signed = $data['direction'] === 'credit' ? $cents : -$cents;
        $actor = $request->user()?->id;

        try {
            Ledger::post($userId, $signed, 'admin', 'admin:' . Str::uuid()->toString(), trim($data['reason']), $actor);
        } catch (InsufficientFunds $e) {
            throw ValidationException::withMessages(['amount' => [Billing::t('a_debit_too_high')]]);
        }
        $text = Money::format($signed, Config::currency());
        Billing::log('admin-booking', 'Manual booking ' . Money::format($signed, Config::currency(), 'en') . ': ' . trim($data['reason']), null, $userId, null, $actor);
        if ($signed > 0) {
            Jobs::resumeFor($userId);
        }

        return Billing::t('a_booked', ['amount' => $text]);
    }

    private static function retryProvision(Request $request): string
    {
        $id = (int) $request->validate(['subscription_id' => ['required', 'integer', 'min:1']])['subscription_id'];
        $state = Provisioner::provision($id, true);
        if ($state === 'done') {
            Jobs::chargeDay($id);

            return Billing::t('a_provisioned');
        }
        if ($state === 'failed') {
            throw ValidationException::withMessages(['subscription_id' => [Billing::t('a_provision_failed_again')]]);
        }

        throw ValidationException::withMessages(['subscription_id' => [Billing::t('a_provision_not_waiting')]]);
    }

    /** Beendet ein Abo sofort (Stripe-Abo wird bei Stripe beendet). Der Server wird gesperrt, nicht gelöscht. */
    private static function endSubscription(Request $request): string
    {
        $data = $request->validate([
            'subscription_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:200', 'regex:/^[^\p{Cc}\p{Cf}<>]+$/u'],
        ], [
            'reason.*' => Billing::t('a_reason_required'),
            'subscription_id.*' => Billing::t('a_not_running'),
        ]);
        $row = DB::table(Billing::SUBSCRIPTIONS)->where('id', (int) $data['subscription_id'])->first();
        if ($row === null || !in_array($row->status, ['active', 'past_due'], true)) {
            throw ValidationException::withMessages(['subscription_id' => [Billing::t('a_not_running')]]);
        }
        if ($row->kind === 'stripe' && $row->stripe_subscription_id !== null) {
            try {
                Billing::stripe()->cancelSubscription((string) $row->stripe_subscription_id, 'techtyl-end-' . $row->id);
            } catch (StripeException $e) {
                Log::error('techtyl billing: Abo konnte bei Stripe nicht beendet werden', ['subscription' => $row->id, 'error' => $e->getMessage()]);
                throw ValidationException::withMessages(['subscription_id' => [Billing::t('a_end_stripe_failed')]]);
            }
        }
        DB::table(Billing::SUBSCRIPTIONS)->where('id', $row->id)->whereIn('status', ['active', 'past_due'])
            ->update(['status' => 'canceled', 'cancel_at_period_end' => false, 'ended_at' => now(), 'updated_at' => now()]);
        Billing::log('canceled', 'Subscription ended by an administrator: ' . trim($data['reason']), (int) $row->id, (int) $row->user_id, null, $request->user()?->id);
        Enforcer::sync((int) $row->id);

        return Billing::t('a_ended');
    }
}
