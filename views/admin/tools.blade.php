@php
  $tt = fn (string $key, array $replace = []) => \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::t('tools.' . $key, $replace);
@endphp

@if (empty($toolsReady))
  <div class="alert alert-warning">{{ $tt('admin_tables_hint') }}</div>
@endif

<div class="row">
  <div class="col-md-8">
    @if (!empty($toolsReady))
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">{{ $tt('admin_types_title') }}</h3>
        </div>
        <form method="POST" action="{{ $root }}">
          {{ csrf_field() }}
          <input type="hidden" name="action" value="tools.egg-types">
          <div class="box-body">
            <p class="text-muted">{{ $tt('admin_types_help') }}</p>
          </div>
          <div class="box-body table-responsive no-padding">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>{{ $tt('admin_col_egg') }}</th>
                  <th>{{ $tt('admin_col_nest') }}</th>
                  <th>{{ $tt('admin_col_servers') }}</th>
                  <th>{{ $tt('admin_col_suggestion') }}</th>
                  <th>{{ $tt('admin_col_type') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($toolsEggs as $egg)
                  <tr>
                    <td><strong>{{ $egg['name'] }}</strong></td>
                    <td>{{ $egg['nest'] }}</td>
                    <td>{{ $egg['servers'] }}</td>
                    <td>
                      @if ($egg['suggested'] === 'none')
                        <span class="label label-default">{{ $egg['suggested_label'] }}</span>
                      @else
                        <span class="label label-primary">{{ $egg['suggested_label'] }}</span>
                      @endif
                    </td>
                    <td>
                      <label class="sr-only" for="techtyl-tools-egg-{{ $egg['id'] }}">{{ $tt('admin_type_for', ['egg' => $egg['name']]) }}</label>
                      <select class="form-control input-sm" id="techtyl-tools-egg-{{ $egg['id'] }}" name="types[{{ $egg['id'] }}]">
                        <option value="auto" @if ($egg['assigned'] === 'auto') selected @endif>{{ $tt('admin_auto', ['type' => $egg['suggested_label']]) }}</option>
                        @foreach ($toolsTypes as $type => $label)
                          <option value="{{ $type }}" @if ($egg['assigned'] === $type) selected @endif>{{ $label }}</option>
                        @endforeach
                      </select>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <div class="box-footer">
            <button type="submit" class="btn btn-primary pull-right">{{ $tt('admin_save') }}</button>
          </div>
        </form>
      </div>
    @endif

    <div class="box">
      <div class="box-header with-border">
        <h3 class="box-title">{{ $tt('admin_switch_title') }}</h3>
      </div>
      <form method="POST" action="{{ $root }}">
        {{ csrf_field() }}
        <input type="hidden" name="action" value="tools.switch">
        <div class="box-body">
          <p class="text-muted">{{ $tt('admin_switch_help') }}</p>
          <div class="form-group">
            <label for="techtyl-tools-switch">{{ $tt('admin_switch_label') }}</label>
            <select class="form-control" id="techtyl-tools-switch" name="switch_enabled">
              <option value="0" @if (empty($toolsSwitchEnabled)) selected @endif>{{ $tt('admin_switch_off') }}</option>
              <option value="1" @if (!empty($toolsSwitchEnabled)) selected @endif>{{ $tt('admin_switch_on') }}</option>
            </select>
          </div>
          <p class="text-muted">{{ $tt('admin_switch_eggs') }}</p>
          <p class="text-muted">{{ $tt('admin_switch_eggs_help') }}</p>
          @if (empty($toolsSwitchChoices))
            <p class="text-muted">{{ $tt('admin_switch_none') }}</p>
          @endif
        </div>
        @if (!empty($toolsSwitchChoices))
          <div class="box-body table-responsive no-padding">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>{{ $tt('admin_switch_col_allow') }}</th>
                  <th>{{ $tt('admin_col_egg') }}</th>
                  <th>{{ $tt('admin_col_nest') }}</th>
                  <th>{{ $tt('admin_col_type') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($toolsSwitchChoices as $choice)
                  <tr>
                    <td>
                      <label class="sr-only" for="techtyl-tools-switch-egg-{{ $choice['id'] }}">{{ $choice['name'] }}</label>
                      <input type="checkbox" id="techtyl-tools-switch-egg-{{ $choice['id'] }}" name="switch_eggs[]" value="{{ $choice['id'] }}" @if (in_array($choice['id'], $toolsSwitchEggs ?? [], false)) checked @endif>
                    </td>
                    <td>{{ $choice['name'] }}</td>
                    <td>{{ $choice['nest'] }}</td>
                    <td>{{ $choice['label'] }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
        <div class="box-footer">
          <button type="submit" class="btn btn-primary pull-right">{{ $tt('admin_switch_save') }}</button>
        </div>
      </form>
    </div>

    <div class="box">
      <div class="box-header with-border">
        <h3 class="box-title">{{ $tt('admin_share_title') }}</h3>
      </div>
      <form method="POST" action="{{ $root }}">
        {{ csrf_field() }}
        <input type="hidden" name="action" value="tools.settings">
        <div class="box-body">
          <p class="text-muted">{{ $tt('admin_share_help') }}</p>
          <div class="form-group">
            <label for="techtyl-tools-share">{{ $tt('admin_share_label') }}</label>
            <select class="form-control" id="techtyl-tools-share" name="share_logs">
              <option value="1" @if (!empty($toolsShareLogs)) selected @endif>{{ $tt('admin_share_on') }}</option>
              <option value="0" @if (empty($toolsShareLogs)) selected @endif>{{ $tt('admin_share_off') }}</option>
            </select>
          </div>
        </div>
        <div class="box-footer">
          <button type="submit" class="btn btn-primary pull-right">{{ $tt('admin_share_save') }}</button>
        </div>
      </form>
    </div>
  </div>

  <div class="col-md-4">
    @if (!empty($toolsReady))
      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">{{ $tt('admin_state') }}</h3></div>
        <div class="box-body">
          <p>{{ $tt('admin_stat_installed') }} <strong>{{ $toolsStats['installed'] }}</strong></p>
          <p>{{ $tt('admin_stat_active') }} <strong>{{ $toolsStats['active'] }}</strong></p>
          <p>{{ $tt('admin_stat_failed') }} <strong>{{ $toolsStats['failed'] }}</strong></p>
          @if ($toolsQueueSync)
            <p class="text-muted small">{{ $tt('admin_queue_sync') }}</p>
          @endif
        </div>
      </div>
    @endif

    <div class="box">
      <div class="box-header with-border"><h3 class="box-title">{{ $tt('admin_services') }}</h3></div>
      <div class="box-body">
        <p class="text-muted">{{ $tt('admin_services_help') }}</p>
        <ul>
          <li>{{ $tt('admin_service_modrinth') }}</li>
          <li>{{ $tt('admin_service_paper') }}</li>
          <li>{{ $tt('admin_service_purpur') }}</li>
          <li>{{ $tt('admin_service_mojang') }}</li>
          <li>{{ $tt('admin_service_mojang_accounts') }}</li>
          <li>{{ $tt('admin_service_fabric') }}</li>
          <li>{{ $tt('admin_service_forge') }}</li>
          <li>{{ $tt('admin_service_neoforge') }}</li>
          <li>{{ $tt('admin_service_mclo') }}</li>
        </ul>
        <p class="text-muted small">{{ $tt('admin_services_privacy') }}</p>
        <p class="text-muted small">{{ $tt('admin_share_privacy') }}</p>
      </div>
    </div>
  </div>
</div>
