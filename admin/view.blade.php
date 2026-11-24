@php
  $t = fn (string $key, array $replace = []) => \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::t('admin.' . $key, $replace);
@endphp
<div class="techtyl-admin">
  <ul class="nav nav-tabs techtyl-tabs" role="tablist">
    <li class="@if ($tab === 'design') active @endif"><a href="{{ $root }}">Design</a></li>
    <li class="@if ($tab === 'modules') active @endif"><a href="{{ $root }}?tab=modules">{{ $t('modules') }}</a></li>
    @foreach (array_keys($modules) as $key)
      @if ($moduleStates[$key])
        <li class="@if ($tab === $key) active @endif"><a href="{{ $root }}?tab={{ $key }}">{{ $t('module_' . $key) }}</a></li>
      @endif
    @endforeach
  </ul>

  @if ($tab === 'design')
  @php
    $dt = fn (string $key, array $replace = []) => \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::t('designer.' . $key, $replace);
  @endphp
  <div class="box techtyl-designer-entry">
    <div class="box-body">
      <div class="row">
        <div class="col-md-8">
          <h3 class="techtyl-designer-entry-title">{{ $dt('entry_title') }}</h3>
          <p class="text-muted">{{ $dt('entry_text') }}</p>
          <p class="small techtyl-designer-entry-summary">
            {{ $dt('entry_summary', [
                'variant' => $dt('variant_' . $design['variant']),
                'accent' => $design['accent'],
                'nav' => $dt('nav_' . $design['navigation']),
                'background' => $dt($design['background'] === 'none' ? 'summary_bg_none' : ($design['background'] === '' ? 'summary_bg_default' : 'summary_bg_custom')),
                'announcement' => $dt($designerSummary['announcement'] ? 'on' : 'off'),
                'links' => $dt($designerSummary['links'] === 1 ? 'links_one' : 'links_many', ['count' => $designerSummary['links']]),
            ]) }}
          </p>
        </div>
        <div class="col-md-4 text-right">
          <a class="btn btn-primary btn-lg techtyl-designer-open" href="{{ $designerUrl }}">{{ $dt('open') }}</a>
        </div>
      </div>
    </div>
  </div>

  @if (!$tablesReady)
    <div class="alert alert-warning">
      {{ $t('tables_missing') }}
      <code>php artisan migrate --force</code>
      {{ $t('tables_missing_after') }}
    </div>
  @endif

  <div class="row">
    <div class="col-md-12">
      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">{{ $t('egg_banners') }}</h3></div>
        <div class="box-body">
          <p class="text-muted small">{{ $t('egg_banners_hint') }}</p>
          @if ($tablesReady)
            <form method="POST" action="{{ $root }}" enctype="multipart/form-data">
              {{ csrf_field() }}
              <input type="hidden" name="action" value="egg-banner">
              <div class="form-group">
                <label for="techtyl-egg">Egg</label>
                <select class="form-control" id="techtyl-egg" name="egg_id" required>
                  @foreach ($eggs as $egg)
                    <option value="{{ $egg->id }}">{{ $egg->nest }} / {{ $egg->name }}@if (isset($eggBanners[$egg->id])) {{ $t('egg_has_banner') }}@endif</option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
                <label for="techtyl-egg-file">{{ $t('image') }}</label>
                <input type="file" id="techtyl-egg-file" name="image" accept="image/jpeg,image/png,image/webp" required>
              </div>
              <button type="submit" class="btn btn-primary">{{ $t('save_banner') }}</button>
            </form>
          @endif
        </div>
        @if ($tablesReady && count($eggBanners) > 0)
          <div class="box-body table-responsive no-padding">
            <table class="table table-hover">
              <thead><tr><th>Egg</th><th>Banner</th><th></th></tr></thead>
              <tbody>
                @foreach ($eggs as $egg)
                  @if (isset($eggBanners[$egg->id]))
                    <tr>
                      <td>{{ $egg->nest }} / {{ $egg->name }}</td>
                      <td><img src="{{ $eggBanners[$egg->id] }}" alt="" class="img-thumbnail" width="200"></td>
                      <td class="text-right">
                        <form method="POST" action="{{ $root }}/egg-banner/{{ $egg->id }}">
                          {{ csrf_field() }}
                          <input type="hidden" name="_method" value="DELETE">
                          <button type="submit" class="btn btn-danger btn-xs">{{ $t('remove') }}</button>
                        </form>
                      </td>
                    </tr>
                  @endif
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>
  </div>
  @endif

  @if ($tab === 'modules')
    <div class="row">
      <div class="col-md-8">
        <div class="box">
          <div class="box-header with-border"><h3 class="box-title">{{ $t('modules') }}</h3></div>
          <form method="POST" action="{{ $root }}">
            {{ csrf_field() }}
            <input type="hidden" name="action" value="modules">
            <div class="box-body">
              <p class="text-muted">{{ $t('modules_hint') }}</p>
              @foreach (array_keys($modules) as $key)
                <div class="form-group techtyl-module">
                  <input type="hidden" name="modules[{{ $key }}]" value="0">
                  <label><input type="checkbox" name="modules[{{ $key }}]" value="1" @if ($moduleStates[$key]) checked @endif> {{ $t('module_' . $key) }}</label>
                  <p class="text-muted small">{{ $t('module_' . $key . '_desc') }}</p>
                </div>
              @endforeach
            </div>
            <div class="box-footer">
              <button type="submit" class="btn btn-primary pull-right">{{ $t('save_modules') }}</button>
            </div>
          </form>
        </div>
      </div>
      <div class="col-md-4">
        @php
          $techtylAccount = \Pterodactyl\BlueprintFramework\Extensions\{identifier}\Account\AccountSettings::get();
        @endphp
        <div class="box">
          <div class="box-header with-border"><h3 class="box-title">{{ \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::t('core.account') }}</h3></div>
          <form method="POST" action="{{ $root }}">
            {{ csrf_field() }}
            <input type="hidden" name="action" value="account">
            <div class="box-body">
              @foreach (['uploads', 'gravatar', 'names'] as $flag)
                <div class="form-group">
                  <input type="hidden" name="{{ $flag }}" value="0">
                  <label><input type="checkbox" name="{{ $flag }}" value="1" @if ($techtylAccount[$flag]) checked @endif> {{ \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::t('core.account_' . $flag) }}</label>
                  <p class="text-muted small">{{ \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::t('core.account_' . $flag . '_help') }}</p>
                </div>
              @endforeach
            </div>
            <div class="box-footer">
              <button type="submit" class="btn btn-primary pull-right">{{ \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::t('core.save') }}</button>
            </div>
          </form>
        </div>
        <div class="box">
          <div class="box-header with-border"><h3 class="box-title">{{ \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::t('core.language') }}</h3></div>
          <form method="POST" action="{{ $root }}">
            {{ csrf_field() }}
            <input type="hidden" name="action" value="locale">
            <div class="box-body">
              <div class="form-group">
                <label for="techtyl-locale-select">{{ \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::t('core.language_default') }}</label>
                <select class="form-control" id="techtyl-locale-select" name="locale">
                  <option value="en" @if (\Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::fallbackLocale() === 'en') selected @endif>English</option>
                  <option value="de" @if (\Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::fallbackLocale() === 'de') selected @endif>Deutsch</option>
                </select>
                <p class="text-muted small">{{ \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::t('core.language_help') }}</p>
              </div>
            </div>
            <div class="box-footer">
              <button type="submit" class="btn btn-primary pull-right">{{ \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::t('core.save') }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    @php
      $techtylNav = \Pterodactyl\BlueprintFramework\Extensions\{identifier}\Integration\Registry::catalog();
      $techtylNavHidden = $techtylNav['ok']
        ? \Pterodactyl\BlueprintFramework\Extensions\{identifier}\Integration\Registry::hidden()
        : ['extensions' => [], 'pages' => []];
    @endphp
    <div class="row">
      <div class="col-md-12">
        <div class="box">
          <div class="box-header with-border"><h3 class="box-title">{{ $t('nav_ext_title') }}</h3></div>
          @if (!$techtylNav['ok'])
            <div class="box-body">
              <p class="text-muted">{{ $t('nav_ext_unavailable') }}</p>
            </div>
          @elseif (count($techtylNav['rows']) === 0)
            <div class="box-body">
              <p class="text-muted">{{ $t('nav_ext_hint') }}</p>
              <p class="text-muted">{{ $t('nav_ext_empty') }}</p>
            </div>
          @else
            <form method="POST" action="{{ $root }}">
              {{ csrf_field() }}
              <input type="hidden" name="action" value="extensions">
              <div class="box-body">
                <p class="text-muted">{{ $t('nav_ext_hint') }}</p>
                @if (count($techtylNav['rows']) > 300)
                  <p class="text-muted">{{ $t('nav_ext_truncated') }}</p>
                @endif
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th>{{ $t('nav_ext_identifier') }}</th>
                        <th>{{ $t('nav_ext_name') }}</th>
                        <th>{{ $t('nav_ext_path') }}</th>
                        <th>{{ $t('nav_ext_show') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach (array_slice($techtylNav['rows'], 0, 300) as $techtylNavRow)
                        @php
                          $techtylShown = !in_array($techtylNavRow['identifier'], $techtylNavHidden['extensions'], true)
                            && ($techtylNavRow['path'] === '' || !in_array($techtylNavRow['key'], $techtylNavHidden['pages'], true));
                        @endphp
                        <tr>
                          <td><code>{{ $techtylNavRow['identifier'] }}</code></td>
                          <td>
                            @if ($techtylNavRow['name'] !== '')
                              {{ $techtylNavRow['name'] }}
                            @else
                              {{ $t('nav_ext_fileless') }}
                            @endif
                          </td>
                          <td>
                            @if ($techtylNavRow['path'] !== '')
                              {{ $t($techtylNavRow['side'] === 'account' ? 'nav_ext_account' : 'nav_ext_server') }}
                              <code>{{ $techtylNavRow['path'] }}</code>
                            @endif
                          </td>
                          <td>
                            <input type="hidden" name="present[]" value="{{ $techtylNavRow['key'] }}">
                            <input type="checkbox" name="visible[]" value="{{ $techtylNavRow['key'] }}" @if ($techtylShown) checked @endif title="{{ $t('nav_ext_show') }}" aria-label="{{ $t('nav_ext_show') }}">
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="box-footer">
                <button type="submit" class="btn btn-primary pull-right">{{ $t('nav_ext_save') }}</button>
              </div>
            </form>
          @endif
        </div>
      </div>
    </div>
  @endif

  @if (isset($modules[$tab]))
    @if (!empty($moduleData['moduleError']))
      <div class="alert alert-danger">{{ $t('module_error') }}</div>
    @elseif ($moduleView !== null)
      @include($moduleView, $moduleData)
    @else
      <div class="alert alert-info">{{ $t('module_empty') }}</div>
    @endif
  @endif
</div>
