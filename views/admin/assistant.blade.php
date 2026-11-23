@php
  $t = fn (string $key, array $replace = []) => \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::t('assistant.' . $key, $replace);
  $assistantNumberKeys = ['daily_limit', 'per_minute', 'max_input_chars', 'max_tokens', 'timeout'];
  $assistantEffortLabels = ['' => $t('admin_effort_none'), 'low' => $t('admin_effort_low'), 'medium' => $t('admin_effort_medium'), 'high' => $t('admin_effort_high')];
  $assistantRange = fn (string $key, string $number) => $t('admin_range', ['label' => $t($key), 'min' => $assistantNumbers[$number][1], 'max' => $assistantNumbers[$number][2]]);
@endphp

@if (empty($assistantReady))
  <div class="alert alert-warning">
    {{ $t('admin_tables_missing') }}
  </div>
@endif

<div class="row">
  <div class="col-md-12">
    @if ($assistantConfigured)
      <div class="callout callout-success">
        <h4>{{ $t('admin_ready_title') }}</h4>
        <p>@if ($assistant['provider'] !== 'demo') {{ $t('admin_ready_model', ['provider' => $assistantProviders[$assistant['provider']]['label'], 'model' => $assistantProviders[$assistant['provider']]['model']]) }} @else {{ $t('admin_ready_text', ['provider' => $assistantProviders[$assistant['provider']]['label']]) }} @endif</p>
        @if ($assistant['provider'] === 'demo')
          <p><strong>{{ $t('admin_demo_note') }}</strong></p>
        @endif
      </div>
    @else
      <div class="callout callout-warning">
        <h4>{{ $t('admin_missing_title') }}</h4>
        <p>{{ $t('admin_missing_text') }}</p>
      </div>
    @endif
  </div>
</div>

<form method="POST" action="{{ $root }}">
  {{ csrf_field() }}
  <input type="hidden" name="action" value="assistant.save">

  <div class="row">
    <div class="col-md-7">
      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">{{ $t('admin_providers') }}</h3></div>
        <div class="box-body">
          <p class="text-muted small">{{ $t('admin_keys_note') }}</p>
          <div class="form-group">
            <label for="techtyl-assistant-provider">{{ $t('admin_default_provider') }}</label>
            <select class="form-control" id="techtyl-assistant-provider" name="provider">
              <option value="">{{ $t('admin_none') }}</option>
              @foreach ($assistantProviders as $id => $provider)
                <option value="{{ $id }}" @if ($assistant['provider'] === $id) selected @endif>{{ $provider['label'] }}@if (!$provider['ready']) ({{ $t('admin_not_ready') }})@endif</option>
              @endforeach
            </select>
            <p class="text-muted small">{{ $t('admin_default_help') }}</p>
          </div>
        </div>

        @foreach ($assistantProviders as $id => $provider)
          @if ($id !== 'demo')
            <div class="box-body techtyl-assistant-provider">
              <h4>
                {{ $provider['label'] }}
                @if ($provider['has_key'])
                  <span class="label label-success">{{ $t('admin_key_set') }}</span>
                @elseif ($id === 'custom')
                  <span class="label label-default">{{ $t('admin_key_optional') }}</span>
                @else
                  <span class="label label-default">{{ $t('admin_key_none') }}</span>
                @endif
                @if ($assistant['provider'] === $id)
                  <span class="label label-primary">{{ $t('admin_default_badge') }}</span>
                @endif
              </h4>

              @if ($id === 'azure')
                <div class="form-group">
                  <label for="techtyl-assistant-azure-endpoint">{{ $t('admin_endpoint') }}</label>
                  <input class="form-control" type="url" maxlength="200" id="techtyl-assistant-azure-endpoint" name="azure_endpoint" value="{{ old('azure_endpoint', $provider['endpoint']) }}" placeholder="https://meine-ressource.openai.azure.com">
                </div>
              @endif
              @if ($id === 'custom')
                <div class="form-group">
                  <label for="techtyl-assistant-custom-url">{{ $t('admin_address') }}</label>
                  <input class="form-control" type="url" maxlength="200" id="techtyl-assistant-custom-url" name="custom_url" value="{{ old('custom_url', $provider['url']) }}" placeholder="https://ki.example.org/v1">
                  <p class="text-muted small">{{ $t('admin_address_help') }}</p>
                </div>
                <div class="form-group">
                  <input type="hidden" name="allow_private" value="0">
                  <label><input type="checkbox" name="allow_private" value="1" @if ($assistant['allow_private']) checked @endif> {{ $t('admin_allow_private') }}</label>
                  <p class="text-muted small">{{ $t('admin_allow_private_help') }}</p>
                </div>
              @endif

              <div class="row">
                <div class="col-sm-6">
                  <div class="form-group">
                    <label for="techtyl-assistant-key-{{ $id }}">{{ $provider['has_key'] ? $t('admin_key_replace') : $t('admin_key') }}</label>
                    <input class="form-control" type="password" maxlength="400" autocomplete="new-password" id="techtyl-assistant-key-{{ $id }}" name="key_{{ $id }}" value="" placeholder="{{ $provider['has_key'] ? $t('admin_key_stored') : $t('admin_key_empty') }}">
                    @if ($provider['has_key'])
                      <input type="hidden" name="remove_key_{{ $id }}" value="0">
                      <label class="small"><input type="checkbox" name="remove_key_{{ $id }}" value="1"> {{ $t('admin_key_remove') }}</label>
                    @endif
                  </div>
                </div>
                <div class="col-sm-6">
                  <div class="form-group">
                    <label for="techtyl-assistant-model-{{ $id }}">{{ $id === 'azure' ? $t('admin_deployment') : $t('admin_model') }}</label>
                    <input class="form-control" type="text" maxlength="120" id="techtyl-assistant-model-{{ $id }}" name="model_{{ $id }}" value="{{ old('model_' . $id, $provider['model']) }}" list="techtyl-assistant-models-{{ $id }}" placeholder="{{ $provider['default_model'] }}" autocomplete="off" spellcheck="false">
                    <datalist id="techtyl-assistant-models-{{ $id }}">
                      @foreach ($provider['models'] as $model)
                        <option value="{{ $model }}"></option>
                      @endforeach
                    </datalist>
                    @if (count($provider['models']) > 0)
                      <p class="text-muted small">{{ $t('admin_model_help', ['models' => implode(', ', $provider['models'])]) }}</p>
                    @endif
                  </div>
                </div>
              </div>

              @if ($id === 'azure')
                <div class="form-group">
                  <label for="techtyl-assistant-azure-version">{{ $t('admin_azure_version') }}</label>
                  <input class="form-control" type="text" maxlength="24" id="techtyl-assistant-azure-version" name="azure_api_version" value="{{ old('azure_api_version', $provider['api_version']) }}" placeholder="{{ $t('admin_azure_version_placeholder') }}">
                  <p class="text-muted small">{{ $t('admin_azure_version_help') }}</p>
                </div>
              @endif
              @if ($id === 'anthropic')
                <div class="form-group">
                  <label for="techtyl-assistant-effort">{{ $t('admin_effort') }}</label>
                  <select class="form-control" id="techtyl-assistant-effort" name="effort">
                    @foreach ($assistantEffortLabels as $value => $label)
                      <option value="{{ $value }}" @if ($assistant['effort'] === $value) selected @endif>{{ $label }}</option>
                    @endforeach
                  </select>
                  <p class="text-muted small">{{ $t('admin_effort_help') }}</p>
                </div>
                <div class="form-group">
                  <input type="hidden" name="fallbacks" value="0">
                  <label><input type="checkbox" name="fallbacks" value="1" @if ($assistant['fallbacks']) checked @endif> {{ $t('admin_fallbacks') }}</label>
                  <p class="text-muted small">{{ $t('admin_fallbacks_help') }}</p>
                </div>
              @endif
            </div>
          @endif
        @endforeach
      </div>
    </div>

    <div class="col-md-5">
      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">{{ $t('admin_limits') }}</h3></div>
        <div class="box-body">
          @foreach ($assistantNumberKeys as $key)
            <div class="form-group">
              <label for="techtyl-assistant-{{ $key }}">{{ $assistantRange('admin_' . $key, $key) }}</label>
              <input class="form-control" type="number" min="{{ $assistantNumbers[$key][1] }}" max="{{ $assistantNumbers[$key][2] }}" id="techtyl-assistant-{{ $key }}" name="{{ $key }}" value="{{ old($key, $assistant[$key]) }}" required>
              <p class="text-muted small">{{ $t('admin_' . $key . '_help') }}</p>
            </div>
          @endforeach
        </div>
      </div>

      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">{{ $t('admin_privacy') }}</h3></div>
        <div class="box-body">
          <div class="form-group">
            <input type="hidden" name="context_enabled" value="0">
            <label><input type="checkbox" name="context_enabled" value="1" @if ($assistant['context_enabled']) checked @endif> {{ $t('admin_context_enabled') }}</label>
            <p class="text-muted small">{{ $t('admin_context_help') }}</p>
          </div>
          <div class="form-group">
            <label for="techtyl-assistant-context-lines">{{ $assistantRange('admin_context_lines', 'context_lines') }}</label>
            <input class="form-control" type="number" min="{{ $assistantNumbers['context_lines'][1] }}" max="{{ $assistantNumbers['context_lines'][2] }}" id="techtyl-assistant-context-lines" name="context_lines" value="{{ old('context_lines', $assistant['context_lines']) }}" required>
          </div>
          <div class="form-group">
            <label for="techtyl-assistant-context-chars">{{ $assistantRange('admin_context_chars', 'context_chars') }}</label>
            <input class="form-control" type="number" min="{{ $assistantNumbers['context_chars'][1] }}" max="{{ $assistantNumbers['context_chars'][2] }}" id="techtyl-assistant-context-chars" name="context_chars" value="{{ old('context_chars', $assistant['context_chars']) }}" required>
          </div>
          <div class="form-group">
            <label for="techtyl-assistant-retention">{{ $t('admin_retention') }}</label>
            <input class="form-control" type="number" min="{{ $assistantNumbers['retention_days'][1] }}" max="{{ $assistantNumbers['retention_days'][2] }}" id="techtyl-assistant-retention" name="retention_days" value="{{ old('retention_days', $assistant['retention_days']) }}" required>
            <p class="text-muted small">{{ $t('admin_retention_help') }}</p>
          </div>
        </div>
      </div>

      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">{{ $t('admin_extra') }}</h3></div>
        <div class="box-body">
          <div class="form-group">
            <label for="techtyl-assistant-extra">{{ $t('admin_extra_label') }}</label>
            <textarea class="form-control" rows="4" maxlength="1000" id="techtyl-assistant-extra" name="extra_prompt" placeholder="{{ $t('admin_extra_placeholder') }}">{{ old('extra_prompt', $assistant['extra_prompt']) }}</textarea>
            <p class="text-muted small">{{ $t('admin_extra_help') }}</p>
          </div>
        </div>
        <div class="box-footer">
          <button type="submit" class="btn btn-primary pull-right">{{ $t('admin_save') }}</button>
        </div>
      </div>
    </div>
  </div>
</form>

<div class="row">
  <div class="col-md-7">
    <div class="box">
      <div class="box-header with-border">
        <h3 class="box-title">{{ $t('admin_usage') }}</h3>
        <div class="box-tools"><span class="text-muted small">{{ $t('admin_usage_summary', ['today' => $assistantTotals['today'], 'users' => $assistantTotals['users_today'], 'month' => $assistantTotals['month']]) }}</span></div>
      </div>
      @if (count($assistantUsage) > 0)
        <div class="box-body table-responsive no-padding">
          <table class="table table-hover">
            <thead>
              <tr><th>{{ $t('admin_col_user') }}</th><th>{{ $t('admin_col_today') }}</th><th>{{ $t('admin_col_share') }}</th><th>{{ $t('admin_col_month') }}</th><th>{{ $t('admin_col_tokens') }}</th></tr>
            </thead>
            <tbody>
              @foreach ($assistantUsage as $row)
                <tr>
                  <td>{{ $row['username'] }}</td>
                  <td>{{ $row['today'] }}</td>
                  <td>
                    <span class="label @if ($row['today_percent'] >= 100) label-danger @elseif ($row['today_percent'] >= 75) label-warning @else label-default @endif">{{ $row['today_percent'] }} %</span>
                  </td>
                  <td>{{ $row['month'] }}</td>
                  <td>{{ number_format($row['tokens_in'], 0, ',', '.') }} / {{ number_format($row['tokens_out'], 0, ',', '.') }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="box-body"><p class="text-muted">{{ $t('admin_usage_empty') }}</p></div>
      @endif
    </div>
  </div>

  <div class="col-md-5">
    <div class="box">
      <div class="box-header with-border"><h3 class="box-title">{{ $t('admin_tools') }}</h3></div>
      <div class="box-body">
        <form method="POST" action="{{ $root }}">
          {{ csrf_field() }}
          <input type="hidden" name="action" value="assistant.test">
          <p class="text-muted small">{{ $t('admin_test_help') }}</p>
          <p><button type="submit" class="btn btn-default" @if (!$assistantConfigured) disabled @endif>{{ $t('admin_test') }}</button></p>
        </form>
        <hr>
        <form method="POST" action="{{ $root }}">
          {{ csrf_field() }}
          <input type="hidden" name="action" value="assistant.prune">
          <p class="text-muted small">{{ $t('admin_prune_help', ['count' => $assistantTotals['conversations']]) }}</p>
          <p><button type="submit" class="btn btn-default">{{ $t('admin_prune') }}</button></p>
        </form>
      </div>
    </div>
  </div>
</div>
