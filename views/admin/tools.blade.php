@php
  $tt = fn (string $key, array $replace = []) => \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::t('tools.' . $key, $replace);
@endphp

@if (empty($toolsReady))
  <div class="alert alert-warning">{{ $tt('admin_tables_hint') }}</div>
@else
  <div class="row">
    <div class="col-md-8">
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
    </div>

    <div class="col-md-4">
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

      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">{{ $tt('admin_services') }}</h3></div>
        <div class="box-body">
          <p class="text-muted">{{ $tt('admin_services_help') }}</p>
          <ul>
            <li>{{ $tt('admin_service_modrinth') }}</li>
            <li>{{ $tt('admin_service_paper') }}</li>
            <li>{{ $tt('admin_service_purpur') }}</li>
            <li>{{ $tt('admin_service_mojang') }}</li>
            <li>{{ $tt('admin_service_fabric') }}</li>
          </ul>
          <p class="text-muted small">{{ $tt('admin_services_privacy') }}</p>
        </div>
      </div>
    </div>
  </div>
@endif
