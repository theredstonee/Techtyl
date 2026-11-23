@if (!$ready)
  <div class="alert alert-warning">{{ $t('adm_tables_missing') }}</div>
@endif

@if ($newInvite)
  <div class="alert alert-success">
    <strong>{{ $t('adm_new_invite') }}</strong> <code>{{ $newInvite }}</code><br>
    {{ $t('adm_new_invite_hint') }}
  </div>
@endif

<div class="row">
  <div class="col-md-6">
    <div class="box">
      <div class="box-header with-border"><h3 class="box-title">{{ $t('adm_registration') }}</h3></div>
      <form method="POST" action="{{ $root }}">
        {{ csrf_field() }}
        <input type="hidden" name="action" value="auth.settings">
        <div class="box-body">
          <div class="form-group">
            <input type="hidden" name="registration" value="0">
            <label><input type="checkbox" name="registration" value="1" @if ($settings['registration']) checked @endif> {{ $t('adm_allow') }}</label>
            <p class="text-muted small">
              {{ $t('adm_page') }} <a href="{{ $registerUrl }}" target="_blank" rel="noopener">{{ $registerUrl }}</a><br>
              {{ $t('adm_allow_help', ['minutes' => $linkLifetime, 'mails' => $mailsPerHour]) }}
            </p>
          </div>
          <div class="form-group">
            <label for="techtyl-auth-min-password">{{ $t('adm_min_password', ['min' => $minPasswordFloor, 'max' => $minPasswordCeiling]) }}</label>
            <input class="form-control" type="number" id="techtyl-auth-min-password" name="min_password" min="{{ $minPasswordFloor }}" max="{{ $minPasswordCeiling }}" value="{{ $settings['min_password'] }}" required>
          </div>
          <div class="form-group">
            <input type="hidden" name="invite_required" value="0">
            <label><input type="checkbox" name="invite_required" value="1" @if ($settings['invite_required']) checked @endif> {{ $t('adm_invite_only') }}</label>
            <p class="text-muted small">{{ $t('adm_invite_only_help') }}</p>
          </div>
          <div class="form-group">
            <label for="techtyl-auth-domains">{{ $t('adm_domains') }}</label>
            <textarea class="form-control" id="techtyl-auth-domains" name="domains" rows="4" maxlength="8000" placeholder="example.com">{{ $settings['domains'] }}</textarea>
            <p class="text-muted small">{{ $t('adm_domains_help') }}</p>
          </div>
        </div>
        <div class="box-footer">
          <span class="text-muted small">{{ $t('adm_pending', ['count' => $pending]) }}</span>
          <button type="submit" class="btn btn-primary pull-right">{{ $t('adm_save_registration') }}</button>
        </div>
      </form>
    </div>

    <div class="box">
      <div class="box-header with-border"><h3 class="box-title">{{ $t('adm_hash_title') }}</h3></div>
      <div class="box-body">
        <p>
          {{ $t('adm_hash_before') }}
          @if ($hashDriver === 'bcrypt')
            <strong>{{ $t('adm_hash_bcrypt', ['rounds' => $effectiveRounds]) }}</strong>{{ $t('adm_hash_after') }}
          @else
            <strong>{{ $hashDriver }}</strong> {{ $t('adm_hash_panel') }}{{ $t('adm_hash_after') }}
          @endif
        </p>
        @if ($hashDriver === 'bcrypt' && $panelRounds < 12)
          <div class="alert alert-warning">{{ $t('adm_hash_warning', ['rounds' => $panelRounds]) }}</div>
        @endif
        <p class="text-muted small">{{ $t('adm_mail_info', ['mailer' => $mailer, 'queue' => $queue]) }}</p>
        @if (in_array($mailer, ['array', 'log'], true))
          <div class="alert alert-warning">{{ $t('adm_mail_fake', ['mailer' => $mailer]) }}</div>
        @endif
      </div>
    </div>

    <div class="box">
      <div class="box-header with-border"><h3 class="box-title">{{ $t('adm_invites') }}</h3></div>
      <form method="POST" action="{{ $root }}">
        {{ csrf_field() }}
        <input type="hidden" name="action" value="auth.invite-create">
        <div class="box-body">
          <div class="row">
            <div class="form-group col-sm-5">
              <label for="techtyl-auth-invite-label">{{ $t('adm_invite_note') }}</label>
              <input class="form-control" type="text" id="techtyl-auth-invite-label" name="label" maxlength="80" placeholder="{{ $t('adm_invite_note_example') }}">
            </div>
            <div class="form-group col-sm-3">
              <label for="techtyl-auth-invite-uses">{{ $t('adm_invite_uses') }}</label>
              <input class="form-control" type="number" id="techtyl-auth-invite-uses" name="max_uses" min="1" max="100000" value="1" required>
            </div>
            <div class="form-group col-sm-4">
              <label for="techtyl-auth-invite-days">{{ $t('adm_invite_days') }}</label>
              <input class="form-control" type="number" id="techtyl-auth-invite-days" name="expires_days" min="1" max="730" placeholder="{{ $t('adm_unlimited') }}">
            </div>
          </div>
          <button type="submit" class="btn btn-primary" @if (!$ready) disabled @endif>{{ $t('adm_invite_create') }}</button>
        </div>
      </form>
      @if (count($invites) > 0)
        <div class="box-body table-responsive no-padding">
          <table class="table table-hover">
            <thead><tr><th>{{ $t('adm_col_code') }}</th><th>{{ $t('adm_col_note') }}</th><th>{{ $t('adm_col_used') }}</th><th>{{ $t('adm_col_until') }}</th><th>{{ $t('adm_col_state') }}</th><th></th></tr></thead>
            <tbody>
              @foreach ($invites as $invite)
                <tr>
                  <td><code>{{ $invite['hint'] }}…</code></td>
                  <td>{{ $invite['label'] !== '' ? $invite['label'] : '–' }}</td>
                  <td>{{ $t('adm_used', ['uses' => $invite['uses'], 'max' => $invite['max_uses'], 'percent' => $invite['percent']]) }}</td>
                  <td>{{ $invite['expires_at'] ?? $t('adm_unlimited') }}</td>
                  <td>
                    <span class="label @if ($invite['state'] === 'active') label-success @else label-default @endif">{{ $t('adm_state_' . $invite['state']) }}</span>
                  </td>
                  <td class="text-right">
                    @if ($invite['state'] === 'active')
                      <form method="POST" action="{{ $root }}">
                        {{ csrf_field() }}
                        <input type="hidden" name="action" value="auth.invite-revoke">
                        <input type="hidden" name="invite_id" value="{{ $invite['id'] }}">
                        <button type="submit" class="btn btn-danger btn-xs">{{ $t('adm_revoke') }}</button>
                      </form>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="box-body">
          <p class="text-muted">{{ $t('adm_invites_empty') }}</p>
        </div>
      @endif
    </div>
  </div>

  <div class="col-md-6">
    <div class="box">
      <div class="box-header with-border"><h3 class="box-title">Cloudflare Turnstile</h3></div>
      <form method="POST" action="{{ $root }}">
        {{ csrf_field() }}
        <input type="hidden" name="action" value="auth.turnstile">
        <div class="box-body">
          @if ($selfTest['ok'])
            <div class="alert alert-success"><strong>{{ $t('adm_turnstile_yes') }}</strong> {{ $t('adm_turnstile_yes_text') }}</div>
          @else
            <div class="alert @if ($selfTest['active']) alert-danger @else alert-info @endif">
              <strong>{{ $t('adm_turnstile_no') }}</strong>
              @foreach ($selfTest['reasons'] as $reason)
                {{ $reason }}
              @endforeach
            </div>
          @endif
          @if ($testKey && $settings['turnstile'])
            <div class="alert alert-danger">{{ $t('adm_test_key') }}</div>
          @endif

          <div class="form-group">
            <input type="hidden" name="turnstile" value="0">
            <label><input type="checkbox" name="turnstile" value="1" @if ($settings['turnstile']) checked @endif> {{ $t('adm_turnstile_on') }}</label>
            <p class="text-muted small">{{ $t('adm_turnstile_on_help') }}</p>
          </div>
          <div class="form-group">
            <input type="hidden" name="turnstile_login" value="0">
            <label><input type="checkbox" name="turnstile_login" value="1" @if ($settings['turnstile_login']) checked @endif> {{ $t('adm_turnstile_login') }}</label>
            <p class="text-muted small">
              {{ $t('adm_turnstile_login_help') }}
              @if ($panelRecaptcha)
                {{ $t('adm_recaptcha_on') }}
              @endif
            </p>
          </div>
          <div class="form-group">
            <label for="techtyl-auth-site-key">{{ $t('adm_site_key') }}</label>
            <input class="form-control" type="text" id="techtyl-auth-site-key" name="turnstile_site_key" maxlength="100" value="{{ $settings['turnstile_site_key'] }}" autocomplete="off">
          </div>
          <div class="form-group">
            <label for="techtyl-auth-secret">{{ $t('adm_secret') }} ({{ $t($settings['turnstile_secret_set'] ? 'adm_set' : 'adm_not_set') }})</label>
            <input class="form-control" type="password" id="techtyl-auth-secret" name="turnstile_secret" maxlength="200" autocomplete="new-password" placeholder="{{ $settings['turnstile_secret_set'] ? $t('adm_keep') : '' }}">
            <p class="text-muted small">{{ $t('adm_secret_help', ['host' => $expectedHost]) }}</p>
          </div>
          <p class="text-muted small">{{ $t('adm_lockout') }}</p>
        </div>
        <div class="box-footer">
          <button type="submit" class="btn btn-primary pull-right">{{ $t('adm_save_turnstile') }}</button>
        </div>
      </form>
      @if ($settings['turnstile_secret_set'])
        <div class="box-footer">
          <form method="POST" action="{{ $root }}">
            {{ csrf_field() }}
            <input type="hidden" name="action" value="auth.turnstile-check">
            <button type="submit" class="btn btn-default btn-sm">{{ $t('adm_check_turnstile') }}</button>
          </form>
        </div>
      @endif
    </div>

    <div class="box">
      <div class="box-header with-border"><h3 class="box-title">{{ $t('adm_providers') }}</h3></div>
      <form method="POST" action="{{ $root }}">
        {{ csrf_field() }}
        <input type="hidden" name="action" value="auth.providers">
        <div class="box-body">
          <p class="text-muted small">{{ $t('adm_providers_help') }}</p>
          @if (!$appUrlSecure)
            <div class="alert alert-warning">{{ $t('adm_no_https', ['url' => $appUrl]) }}</div>
          @endif
          @foreach ($providers as $key => $provider)
            <h4>{{ $provider['title'] }}</h4>
            <div class="form-group">
              <input type="hidden" name="{{ $key }}" value="0">
              <label><input type="checkbox" name="{{ $key }}" value="1" @if ($provider['enabled']) checked @endif> {{ $t('adm_provider_on', ['provider' => $provider['title']]) }}</label>
            </div>
            <div class="form-group">
              <input type="hidden" name="{{ $key }}_register" value="0">
              <label><input type="checkbox" name="{{ $key }}_register" value="1" @if ($provider['register']) checked @endif> {{ $t('adm_provider_register', ['provider' => $provider['title']]) }}</label>
              <p class="text-muted small">{{ $t('adm_provider_register_help') }}</p>
            </div>
            <div class="form-group">
              <label for="techtyl-auth-{{ $key }}-id">{{ $t('adm_client_id') }}</label>
              <input class="form-control" type="text" id="techtyl-auth-{{ $key }}-id" name="{{ $key }}_client_id" maxlength="200" value="{{ $provider['client_id'] }}" autocomplete="off">
            </div>
            <div class="form-group">
              <label for="techtyl-auth-{{ $key }}-secret">{{ $t('adm_client_secret') }} ({{ $t($provider['secret_set'] ? 'adm_set' : 'adm_not_set') }})</label>
              <input class="form-control" type="password" id="techtyl-auth-{{ $key }}-secret" name="{{ $key }}_secret" maxlength="300" autocomplete="new-password" placeholder="{{ $provider['secret_set'] ? $t('adm_keep') : '' }}">
            </div>
            <div class="form-group">
              <label for="techtyl-auth-{{ $key }}-uri">{{ $t('adm_redirect_uri') }}</label>
              <input class="form-control" type="text" id="techtyl-auth-{{ $key }}-uri" value="{{ $provider['redirect_uri'] }}" readonly>
              <p class="text-muted small">{{ $t('adm_linked', ['count' => $provider['linked']]) }}</p>
            </div>
            @if (!$loop->last)
              <hr>
            @endif
          @endforeach
        </div>
        <div class="box-footer">
          <button type="submit" class="btn btn-primary pull-right">{{ $t('adm_save_providers') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>
