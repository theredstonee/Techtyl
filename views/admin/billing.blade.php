@if (!$billingTables)
  <div class="alert alert-warning">
    {{ $bt('v_tables_missing') }} <code>php artisan migrate --force</code>
  </div>
@else

@if ($billingMode === 'test')
  <div class="alert alert-warning"><strong>{{ $bt('v_test_mode') }}</strong> {{ $bt('v_test_mode_text') }}</div>
@elseif ($billingMode === '')
  <div class="alert alert-info">{{ $bt('v_no_key') }}</div>
@endif

@if (count($billingFailed) > 0)
  <div class="alert alert-danger">
    <strong>{{ $bt('v_provision_failed') }}</strong> {{ $bt('v_provision_failed_text', ['count' => count($billingFailed)]) }}
  </div>
@endif

<div class="row">
  @foreach ($billingStats as $label => $value)
    <div class="col-sm-3 col-xs-6">
      <div class="small-box bg-gray techtyl-stat">
        <div class="inner"><h3>{{ $value }}</h3><p>{{ $label }}</p></div>
      </div>
    </div>
  @endforeach
</div>

<div class="row">
  <div class="col-md-6">
    <div class="box">
      <div class="box-header with-border"><h3 class="box-title">Stripe</h3></div>
      <form method="POST" action="{{ $root }}" autocomplete="off">
        {{ csrf_field() }}
        <input type="hidden" name="action" value="billing.settings">
        <div class="box-body">
          <div class="form-group">
            <label for="billing-secret">Secret Key
              <span class="label @if ($billingSecretSet) label-success @else label-default @endif">@if ($billingSecretSet) {{ $bt('v_set') }} @else {{ $bt('v_not_set') }} @endif</span>
              @if ($billingMode === 'live') <span class="label label-primary">Live</span> @elseif ($billingMode === 'test') <span class="label label-warning">Test</span> @endif
            </label>
            <input class="form-control" type="password" id="billing-secret" name="secret_key" maxlength="255" autocomplete="new-password" placeholder="sk_live_… / sk_test_… ({{ $bt('v_keep_empty') }})">
            <p class="text-muted small">{{ $bt('v_secret_help') }}</p>
          </div>
          <div class="form-group">
            <label for="billing-webhook">Webhook Secret
              <span class="label @if ($billingWebhookSet) label-success @else label-default @endif">@if ($billingWebhookSet) {{ $bt('v_set') }} @else {{ $bt('v_not_set') }} @endif</span>
            </label>
            <input class="form-control" type="password" id="billing-webhook" name="webhook_secret" maxlength="255" autocomplete="new-password" placeholder="whsec_… ({{ $bt('v_keep_empty') }})">
          </div>
          <div class="form-group">
            <label for="billing-currency">{{ $bt('v_currency') }}</label>
            <select class="form-control" id="billing-currency" name="currency">
              @foreach ($billingCurrencies as $code)
                <option value="{{ $code }}" @if ($billingSettings['currency'] === $code) selected @endif>{{ strtoupper($code) }}</option>
              @endforeach
            </select>
            @if ($billingCurrencyLocked)
              <p class="text-muted small">{{ $bt('v_currency_locked') }}</p>
            @endif
          </div>
        </div>
        <div class="box-footer">
          <button type="submit" class="btn btn-primary pull-right">{{ $bt('v_save_stripe') }}</button>
        </div>
      </form>
      <div class="box-footer">
        <form method="POST" action="{{ $root }}">
          {{ csrf_field() }}
          <input type="hidden" name="action" value="billing.test">
          <button type="submit" class="btn btn-default" @if (!$billingSecretSet) disabled @endif>{{ $bt('v_test_connection') }}</button>
          <span class="text-muted small">{{ $bt('v_test_connection_help') }}</span>
        </form>
      </div>
    </div>

    <div class="box">
      <div class="box-header with-border"><h3 class="box-title">{{ $bt('v_webhook_title') }}</h3></div>
      <div class="box-body">
        <p>{{ $bt('v_webhook_intro') }}</p>
        <p><code>{{ $billingWebhookUrl }}</code></p>
        <p>{{ $bt('v_webhook_version') }} <code>{{ $billingApiVersion }}</code></p>
        <p>{{ $bt('v_webhook_events') }}</p>
        <ul>
          @foreach ($billingEvents as $eventName)
            <li><code>{{ $eventName }}</code></li>
          @endforeach
        </ul>
        <p class="text-muted small">{{ $bt('v_webhook_help') }}</p>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="box">
      <div class="box-header with-border"><h3 class="box-title">{{ $bt('v_rules_title') }}</h3></div>
      <form method="POST" action="{{ $root }}">
        {{ csrf_field() }}
        <input type="hidden" name="action" value="billing.rules">
        <div class="box-body">
          <div class="row">
            <div class="col-sm-6 form-group">
              <label for="billing-suspend">{{ $bt('v_suspend_days') }}</label>
              <input class="form-control" type="number" min="0" max="60" id="billing-suspend" name="suspend_days" value="{{ $billingSettings['suspend_days'] }}">
              <p class="text-muted small">{{ $bt('v_suspend_days_help') }}</p>
            </div>
            <div class="col-sm-6 form-group">
              <label for="billing-delete">{{ $bt('v_delete_days') }}</label>
              <input class="form-control" type="number" min="1" max="365" id="billing-delete" name="delete_days" value="{{ $billingSettings['delete_days'] }}">
              <p class="text-muted small">{{ $bt('v_delete_days_help') }}</p>
            </div>
          </div>
          <div class="row">
            <div class="col-sm-6 form-group">
              <label for="billing-topups">{{ $bt('v_topups') }}</label>
              <input class="form-control" type="text" maxlength="120" id="billing-topups" name="topup_amounts" value="{{ $billingSettings['topup_amounts'] }}">
              <p class="text-muted small">{{ $bt('v_topups_help') }}</p>
            </div>
            <div class="col-sm-6 form-group">
              <label for="billing-min">{{ $bt('v_min_balance') }}</label>
              <input class="form-control" type="text" maxlength="12" id="billing-min" name="min_balance" value="{{ $billingSettings['min_balance'] }}">
            </div>
          </div>
          <div class="form-group">
            <label for="billing-terms">{{ $bt('v_terms_url') }}</label>
            <input class="form-control" type="url" maxlength="300" id="billing-terms" name="terms_url" value="{{ $billingSettings['terms_url'] }}" placeholder="https://…">
          </div>
          <div class="form-group">
            <label for="billing-withdrawal">{{ $bt('v_withdrawal_url') }}</label>
            <input class="form-control" type="url" maxlength="300" id="billing-withdrawal" name="withdrawal_url" value="{{ $billingSettings['withdrawal_url'] }}" placeholder="https://…">
            <p class="text-muted small">{{ $bt('v_legal_help') }}</p>
          </div>
        </div>
        <div class="box-footer">
          <button type="submit" class="btn btn-primary pull-right">{{ $bt('v_save_rules') }}</button>
        </div>
      </form>
    </div>

    <div class="box">
      <div class="box-header with-border"><h3 class="box-title">{{ $bt('v_credit_title') }}</h3></div>
      <form method="POST" action="{{ $root }}">
        {{ csrf_field() }}
        <input type="hidden" name="action" value="billing.credit">
        <div class="box-body">
          <div class="row">
            <div class="col-sm-4 form-group">
              <label for="billing-credit-user">{{ $bt('v_user_id') }}</label>
              <input class="form-control" type="number" min="1" id="billing-credit-user" name="user_id" required>
            </div>
            <div class="col-sm-4 form-group">
              <label for="billing-credit-direction">{{ $bt('v_direction') }}</label>
              <select class="form-control" id="billing-credit-direction" name="direction">
                <option value="credit">{{ $bt('v_direction_credit') }}</option>
                <option value="debit">{{ $bt('v_direction_debit') }}</option>
              </select>
            </div>
            <div class="col-sm-4 form-group">
              <label for="billing-credit-amount">{{ $bt('v_amount') }}</label>
              <input class="form-control" type="text" maxlength="12" id="billing-credit-amount" name="amount" placeholder="{{ $bt('v_amount_example') }}" required>
            </div>
          </div>
          <div class="form-group">
            <label for="billing-credit-reason">{{ $bt('v_reason') }}</label>
            <input class="form-control" type="text" minlength="3" maxlength="200" id="billing-credit-reason" name="reason" required>
            <p class="text-muted small">{{ $bt('v_reason_help') }}</p>
          </div>
        </div>
        <div class="box-footer">
          <button type="submit" class="btn btn-primary pull-right">{{ $bt('v_book') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-7">
    <div class="box">
      <div class="box-header with-border"><h3 class="box-title">{{ $bt('v_packages') }}</h3></div>
      <div class="box-body table-responsive no-padding">
        <table class="table table-hover">
          <thead>
            <tr><th>{{ $bt('f_position') }}</th><th>{{ $bt('v_name') }}</th><th>{{ $bt('v_scope') }}</th><th>{{ $bt('v_per_month') }}</th><th>{{ $bt('v_per_day') }}</th><th>{{ $bt('v_status') }}</th><th></th></tr>
          </thead>
          <tbody>
            @forelse ($billingPackages as $package)
              <tr>
                <td>{{ $package['position'] }}</td>
                <td><strong>{{ $package['name'] }}</strong><br><span class="text-muted small">{{ $package['description'] }}</span></td>
                <td>{{ $package['memory'] }} MB RAM, {{ $package['cpu'] }} % CPU, {{ $package['disk'] }} MB<br>
                  <span class="text-muted small">{{ $bt('v_package_extras', ['databases' => $package['databases'], 'backups' => $package['backups'], 'allocations' => $package['allocations'], 'eggs' => count($package['egg_ids'])]) }}</span></td>
                <td>{{ $package['price_month_text'] }}</td>
                <td>{{ $package['price_day_text'] }}</td>
                <td>@if ($package['active']) <span class="label label-success">{{ $bt('v_active') }}</span> @else <span class="label label-default">{{ $bt('v_inactive') }}</span> @endif</td>
                <td class="text-right">
                  <a class="btn btn-xs btn-default" href="{{ $root }}?tab=billing&amp;edit={{ $package['id'] }}#billing-package">{{ $bt('v_edit') }}</a>
                  <form method="POST" action="{{ $root }}" class="techtyl-inline">
                    {{ csrf_field() }}
                    <input type="hidden" name="action" value="billing.package-delete">
                    <input type="hidden" name="id" value="{{ $package['id'] }}">
                    <button type="submit" class="btn btn-xs btn-danger">{{ $bt('v_remove') }}</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-muted">{{ $bt('v_packages_empty') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-5">
    <div class="box" id="billing-package">
      <div class="box-header with-border">
        <h3 class="box-title">@if ($billingEdit) {{ $bt('v_package_edit', ['package' => $billingEdit['name']]) }} @else {{ $bt('v_package_new') }} @endif</h3>
        @if ($billingEdit)
          <div class="box-tools"><a class="btn btn-xs btn-default" href="{{ $root }}?tab=billing">{{ $bt('v_cancel') }}</a></div>
        @endif
      </div>
      <form method="POST" action="{{ $root }}">
        {{ csrf_field() }}
        <input type="hidden" name="action" value="billing.package-save">
        @if ($billingEdit)
          <input type="hidden" name="id" value="{{ $billingEdit['id'] }}">
        @endif
        <div class="box-body">
          <div class="form-group">
            <label for="billing-p-name">{{ $bt('v_name') }}</label>
            <input class="form-control" type="text" maxlength="60" id="billing-p-name" name="name" value="{{ old('name', $billingEdit['name'] ?? '') }}" required>
          </div>
          <div class="form-group">
            <label for="billing-p-description">{{ $bt('v_description') }}</label>
            <input class="form-control" type="text" maxlength="300" id="billing-p-description" name="description" value="{{ old('description', $billingEdit['description'] ?? '') }}">
          </div>
          <div class="row">
            <div class="col-xs-4 form-group">
              <label for="billing-p-memory">RAM (MB)</label>
              <input class="form-control" type="number" min="64" id="billing-p-memory" name="memory" value="{{ old('memory', $billingEdit['memory'] ?? 2048) }}" required>
            </div>
            <div class="col-xs-4 form-group">
              <label for="billing-p-cpu">CPU (%)</label>
              <input class="form-control" type="number" min="10" id="billing-p-cpu" name="cpu" value="{{ old('cpu', $billingEdit['cpu'] ?? 100) }}" required>
            </div>
            <div class="col-xs-4 form-group">
              <label for="billing-p-disk">{{ $bt('f_disk') }} (MB)</label>
              <input class="form-control" type="number" min="128" id="billing-p-disk" name="disk" value="{{ old('disk', $billingEdit['disk'] ?? 10240) }}" required>
            </div>
          </div>
          <div class="row">
            <div class="col-xs-4 form-group">
              <label for="billing-p-databases">{{ $bt('f_databases') }}</label>
              <input class="form-control" type="number" min="0" max="100" id="billing-p-databases" name="databases" value="{{ old('databases', $billingEdit['databases'] ?? 0) }}" required>
            </div>
            <div class="col-xs-4 form-group">
              <label for="billing-p-backups">{{ $bt('f_backups') }}</label>
              <input class="form-control" type="number" min="0" max="100" id="billing-p-backups" name="backups" value="{{ old('backups', $billingEdit['backups'] ?? 0) }}" required>
            </div>
            <div class="col-xs-4 form-group">
              <label for="billing-p-allocations">{{ $bt('f_allocations') }}</label>
              <input class="form-control" type="number" min="0" max="100" id="billing-p-allocations" name="allocations" value="{{ old('allocations', $billingEdit['allocations'] ?? 0) }}" required>
            </div>
          </div>
          <div class="row">
            <div class="col-xs-4 form-group">
              <label for="billing-p-month">{{ $bt('v_price_month') }}</label>
              <input class="form-control" type="text" maxlength="12" id="billing-p-month" name="price_month" value="{{ old('price_month', $billingEdit['price_month'] ?? '') }}" placeholder="{{ $bt('v_price_example') }}" required>
            </div>
            <div class="col-xs-4 form-group">
              <label for="billing-p-day">{{ $bt('v_price_day') }}</label>
              <input class="form-control" type="text" maxlength="12" id="billing-p-day" name="price_day" value="{{ old('price_day', $billingEdit['price_day'] ?? '') }}" placeholder="{{ $bt('v_price_day_empty') }}">
            </div>
            <div class="col-xs-4 form-group">
              <label for="billing-p-position">{{ $bt('f_position') }}</label>
              <input class="form-control" type="number" min="-9999" max="9999" id="billing-p-position" name="position" value="{{ old('position', $billingEdit['position'] ?? 0) }}" required>
            </div>
          </div>
          <div class="form-group">
            <label for="billing-p-eggs">{{ $bt('v_eggs') }}</label>
            <select class="form-control" id="billing-p-eggs" name="egg_ids[]" multiple size="8" required>
              @foreach ($billingEggs as $egg)
                <option value="{{ $egg->id }}" @if (in_array($egg->id, $billingEdit['egg_ids'] ?? [])) selected @endif>{{ $egg->nest }} – {{ $egg->name }}</option>
              @endforeach
            </select>
            <p class="text-muted small">{{ $bt('v_eggs_help') }}</p>
          </div>
          <div class="row">
            <div class="col-xs-6 form-group">
              <label for="billing-p-locations">Locations</label>
              <select class="form-control" id="billing-p-locations" name="location_ids[]" multiple size="4">
                @foreach ($billingLocations as $location)
                  <option value="{{ $location->id }}" @if (in_array($location->id, $billingEdit['location_ids'] ?? [])) selected @endif>{{ $location->short }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-xs-6 form-group">
              <label for="billing-p-nodes">Nodes</label>
              <select class="form-control" id="billing-p-nodes" name="node_ids[]" multiple size="4">
                @foreach ($billingNodes as $node)
                  <option value="{{ $node->id }}" @if (in_array($node->id, $billingEdit['node_ids'] ?? [])) selected @endif>{{ $node->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <p class="text-muted small">{{ $bt('v_nodes_help') }}</p>
          <div class="form-group">
            <input type="hidden" name="active" value="0">
            <label><input type="checkbox" name="active" value="1" @if ($billingEdit === null || $billingEdit['active']) checked @endif> {{ $bt('v_active_label') }}</label>
          </div>
        </div>
        <div class="box-footer">
          <button type="submit" class="btn btn-primary pull-right">@if ($billingEdit) {{ $bt('v_package_save') }} @else {{ $bt('v_package_create') }} @endif</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="box">
  <div class="box-header with-border"><h3 class="box-title">{{ $bt('v_subscriptions') }}</h3></div>
  <div class="box-body table-responsive no-padding">
    <table class="table table-hover">
      <thead>
        <tr><th>{{ $bt('v_no') }}</th><th>{{ $bt('v_user') }}</th><th>Server</th><th>{{ $bt('v_package') }}</th><th>{{ $bt('v_kind_price') }}</th><th>{{ $bt('v_next_payment') }}</th><th>{{ $bt('v_status') }}</th><th></th></tr>
      </thead>
      <tbody>
        @forelse ($billingSubscriptions as $subscription)
          <tr>
            <td>{{ $subscription['id'] }}</td>
            <td>{{ $subscription['user'] }}</td>
            <td>
              @if ($subscription['server_id'])
                <a href="/admin/servers/view/{{ $subscription['server_id'] }}">{{ $subscription['server'] }}</a>
              @else
                {{ $subscription['server'] }}
              @endif
              @if ($subscription['missing_server']) <span class="label label-warning">{{ $bt('v_server_missing') }}</span> @endif
            </td>
            <td>{{ $subscription['package'] }}</td>
            <td>{{ $subscription['kind'] }}<br><span class="text-muted small">{{ $subscription['price'] }}</span></td>
            <td>{{ $subscription['next'] }}</td>
            <td>
              {{ $subscription['status_text'] }}
              @if ($subscription['provision'] === 'failed')
                <br><span class="label label-danger">{{ $bt('v_provision_failed_label') }}</span>
                <br><span class="text-muted small">{{ $subscription['provision_error'] }}</span>
              @elseif ($subscription['provision'] === 'provisioning')
                <br><span class="label label-info">{{ $bt('v_provisioning') }}</span>
              @endif
            </td>
            <td class="text-right">
              @if ($subscription['provision'] === 'failed' && $subscription['status'] === 'active')
                <form method="POST" action="{{ $root }}" class="techtyl-inline">
                  {{ csrf_field() }}
                  <input type="hidden" name="action" value="billing.provision-retry">
                  <input type="hidden" name="subscription_id" value="{{ $subscription['id'] }}">
                  <button type="submit" class="btn btn-xs btn-primary">{{ $bt('v_retry') }}</button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-muted">{{ $bt('v_subscriptions_empty') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <form method="POST" action="{{ $root }}">
    {{ csrf_field() }}
    <input type="hidden" name="action" value="billing.subscription-end">
    <div class="box-footer">
      <div class="row">
        <div class="col-sm-2 form-group">
          <label for="billing-end-id">{{ $bt('v_subscription_no') }}</label>
          <input class="form-control" type="number" min="1" id="billing-end-id" name="subscription_id" required>
        </div>
        <div class="col-sm-7 form-group">
          <label for="billing-end-reason">{{ $bt('v_reason') }}</label>
          <input class="form-control" type="text" minlength="3" maxlength="200" id="billing-end-reason" name="reason" required>
        </div>
        <div class="col-sm-3 form-group">
          <label>&nbsp;</label>
          <button type="submit" class="btn btn-danger btn-block">{{ $bt('v_end_now') }}</button>
        </div>
      </div>
      <p class="text-muted small">{{ $bt('v_end_help') }}</p>
    </div>
  </form>
</div>

<div class="row">
  <div class="col-md-6">
    <div class="box">
      <div class="box-header with-border"><h3 class="box-title">{{ $bt('v_balances') }}</h3></div>
      <div class="box-body table-responsive no-padding">
        <table class="table table-hover">
          <thead><tr><th>ID</th><th>{{ $bt('v_user') }}</th><th>{{ $bt('v_email') }}</th><th class="text-right">{{ $bt('v_balance') }}</th></tr></thead>
          <tbody>
            @forelse ($billingAccounts as $account)
              <tr><td>{{ $account['user_id'] }}</td><td>{{ $account['user'] }}</td><td>{{ $account['email'] }}</td><td class="text-right">{{ $account['balance'] }}</td></tr>
            @empty
              <tr><td colspan="4" class="text-muted">{{ $bt('v_balances_empty') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="box">
      <div class="box-header with-border"><h3 class="box-title">{{ $bt('v_payments') }}</h3></div>
      <div class="box-body table-responsive no-padding">
        <table class="table table-hover">
          <thead><tr><th>{{ $bt('v_time') }}</th><th>{{ $bt('v_user') }}</th><th>{{ $bt('v_kind') }}</th><th class="text-right">{{ $bt('v_amount') }}</th><th>{{ $bt('v_status') }}</th></tr></thead>
          <tbody>
            @forelse ($billingPayments as $payment)
              <tr>
                <td>{{ $payment['when'] }}</td>
                <td>{{ $payment['user'] }}</td>
                <td>{{ $payment['kind'] }}<br><span class="text-muted small">{{ $payment['reference'] }}</span></td>
                <td class="text-right">{{ $payment['amount'] }}@if ($payment['refunded'] !== '')<br><span class="text-muted small">{{ $bt('v_refunded', ['amount' => $payment['refunded']]) }}</span>@endif</td>
                <td>{{ $payment['status'] }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-muted">{{ $bt('v_payments_empty') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="box">
  <div class="box-header with-border"><h3 class="box-title">{{ $bt('v_log') }}</h3></div>
  <div class="box-body table-responsive no-padding">
    <table class="table table-hover">
      <thead><tr><th>{{ $bt('v_time') }}</th><th>{{ $bt('v_action') }}</th><th>{{ $bt('v_user') }}</th><th>{{ $bt('v_subscription') }}</th><th>{{ $bt('v_actor') }}</th><th>{{ $bt('v_details') }}</th></tr></thead>
      <tbody>
        @forelse ($billingLog as $entry)
          <tr>
            <td>{{ $entry['when'] }}</td>
            <td><code>{{ $entry['action'] }}</code></td>
            <td>{{ $entry['user'] }}</td>
            <td>{{ $entry['subscription'] ?? '–' }}</td>
            <td>{{ $entry['actor'] }}</td>
            <td>{{ $entry['detail'] }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-muted">{{ $bt('v_log_empty') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="box-footer"><p class="text-muted small">{{ $bt('v_log_help') }}</p></div>
</div>

@endif
