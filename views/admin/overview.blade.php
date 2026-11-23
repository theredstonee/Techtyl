{{-- Techtyl admin overview for /admin. The admin wrapper renders it hidden at the end of the page and
     public/admin/overview.js moves it to the top of the content. Without the script the panel page stays
     as it is. All values come from Overview\Overview::build(), texts from app/lang/overview.php, and
     everything is printed escaped. --}}
@php
  $t = static fn (string $key, array $replace = []): string => \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::t('overview.' . $key, $replace);
  $n = static fn (float|int $value, int $decimals = 0): string => \Pterodactyl\BlueprintFramework\Extensions\{identifier}\Overview\Overview::number($value, $decimals);
  $techtylDot = static fn (string $tone): string => 'techtyl-ov-dot techtyl-ov-dot--' . $tone;
  $plural = static fn (int $count, string $one, string $many): string => $t($count === 1 ? $one : $many, ['count' => $n($count)]);
@endphp
<div id="techtyl-overview" class="techtyl-ov" hidden lang="{{ $ov['locale'] }}"
  data-title="{{ $t('title') }}" data-subtitle="{{ $t('subtitle') }}" data-intl="{{ $ov['intl'] }}">

  @if ($ov['update']['state'] === 'available')
    <div class="techtyl-ov-update" role="status">
      <span class="{{ $techtylDot('warn') }}" aria-hidden="true"></span>
      <p class="techtyl-ov-update-text">
        <strong>{{ $t('update_available', ['version' => $ov['update']['latest']]) }}</strong>
        <span>{{ $t('update_installed', ['version' => $ov['update']['current']]) }}</span>
      </p>
      <span class="techtyl-ov-update-links">
        <a href="{{ $ov['update']['release'] }}" target="_blank" rel="noopener noreferrer">{{ $t('update_whats_new') }}</a>
        <a href="{{ $ov['update']['guide'] }}" target="_blank" rel="noopener noreferrer">{{ $t('update_guide') }}</a>
      </span>
    </div>
  @endif

  {{-- Toolbar: freshness of the figures and the card editor --}}
  <div class="techtyl-ov-toolbar">
    <p class="techtyl-ov-stamp">{{ $t('stamp', ['age' => $ov['age']]) }}</p>
    @if ($ov['customize']['enabled'])
      <details class="techtyl-ov-customize" id="techtyl-ov-customize" @if ($ov['customize']['open']) open @endif>
        <summary class="btn btn-sm"><i class="fa fa-th" aria-hidden="true"></i> {{ $t('customize_button') }}</summary>
        <div class="techtyl-ov-customize-body">
          <form class="techtyl-ov-cardform" method="POST" action="{{ route('techtyl.overview.cards') }}">
            {{ csrf_field() }}
            <div class="techtyl-ov-customize-head">
              <h2>{{ $t('customize_title') }}</h2>
              <p>{{ $t('customize_help') }}</p>
            </div>
            <ol class="techtyl-ov-cardlist" data-moved-text="{{ $t('moved_to') }}">
              @foreach ($ov['catalog'] as $techtylCard)
                <li class="techtyl-ov-cardrow" data-id="{{ $techtylCard['id'] }}" data-title="{{ $techtylCard['title'] }}">
                  <input type="hidden" name="order[]" value="{{ $techtylCard['id'] }}">
                  <span class="techtyl-ov-grip" aria-hidden="true" title="{{ $t('drag_handle', ['title' => $techtylCard['title']]) }}"><i class="fa fa-ellipsis-v"></i><i class="fa fa-ellipsis-v"></i></span>
                  <label class="techtyl-ov-cardrow-main">
                    <input type="checkbox" name="visible[]" value="{{ $techtylCard['id'] }}" @if ($techtylCard['visible']) checked @endif>
                    <i class="fa fa-{{ $techtylCard['icon'] }} techtyl-ov-cardrow-icon" aria-hidden="true"></i>
                    <span class="techtyl-ov-cardrow-title">{{ $techtylCard['title'] }}</span>
                    <span class="techtyl-ov-cardrow-kind">{{ $t('kind_' . $techtylCard['kind']) }}</span>
                  </label>
                  <span class="techtyl-ov-cardrow-tools">
                    @if ($techtylCard['kind'] === 'custom')
                      <button type="submit" class="btn btn-xs btn-danger techtyl-ov-remove" name="remove" value="{{ $techtylCard['id'] }}"
                        aria-label="{{ $t('remove_label', ['title' => $techtylCard['title']]) }}" data-confirm="{{ $t('remove_confirm') }}">{{ $t('remove') }}</button>
                    @endif
                    <button type="submit" class="btn btn-xs techtyl-ov-move" name="move" value="{{ $techtylCard['id'] }}|up" data-direction="up"
                      aria-label="{{ $t('move_up', ['title' => $techtylCard['title']]) }}" @if ($loop->first) disabled @endif><i class="fa fa-arrow-up" aria-hidden="true"></i></button>
                    <button type="submit" class="btn btn-xs techtyl-ov-move" name="move" value="{{ $techtylCard['id'] }}|down" data-direction="down"
                      aria-label="{{ $t('move_down', ['title' => $techtylCard['title']]) }}" @if ($loop->last) disabled @endif><i class="fa fa-arrow-down" aria-hidden="true"></i></button>
                  </span>
                </li>
              @endforeach
            </ol>
            <p class="techtyl-ov-sr" role="status" aria-live="polite" data-live></p>
            <div class="techtyl-ov-customize-foot">
              <button type="submit" class="btn btn-sm btn-primary">{{ $t('save') }}</button>
              <span class="techtyl-ov-unsaved" hidden><span class="techtyl-ov-dot techtyl-ov-dot--warn" aria-hidden="true"></span>{{ $t('unsaved') }}</span>
              <button type="submit" class="btn btn-sm btn-link techtyl-ov-reset" name="reset" value="1">{{ $t('reset') }}</button>
            </div>
          </form>

          <form class="techtyl-ov-addform" method="POST" action="{{ route('techtyl.overview.cards.custom') }}">
            {{ csrf_field() }}
            <div class="techtyl-ov-customize-head">
              <h2>{{ $t('add_title') }}</h2>
              <p>{{ $t('add_help', ['count' => $ov['customize']['custom_count'], 'max' => $ov['customize']['custom_max']]) }}</p>
            </div>
            @if ($ov['customize']['custom_count'] >= $ov['customize']['custom_max'])
              <p class="techtyl-ov-muted">{{ $t('add_full', ['max' => $ov['customize']['custom_max']]) }}</p>
            @else
              <div class="techtyl-ov-fields">
                <div class="form-group">
                  <label class="control-label" for="techtyl-ov-card-title">{{ $t('cards_field_title') }}</label>
                  <input class="form-control" id="techtyl-ov-card-title" name="title" type="text" required maxlength="{{ $ov['customize']['title_max'] }}" value="{{ old('title') }}">
                </div>
                <div class="form-group">
                  <label class="control-label" for="techtyl-ov-card-link">{{ $t('cards_field_link') }}</label>
                  <input class="form-control" id="techtyl-ov-card-link" name="link" type="url" inputmode="url" maxlength="{{ $ov['customize']['link_max'] }}"
                    placeholder="https://" pattern="https?://.+" aria-describedby="techtyl-ov-card-link-hint" value="{{ old('link') }}">
                  <p class="help-block" id="techtyl-ov-card-link-hint">{{ $t('cards_link_hint') }}</p>
                </div>
                <div class="form-group techtyl-ov-fields-wide">
                  <label class="control-label" for="techtyl-ov-card-text">{{ $t('cards_field_text') }}</label>
                  <input class="form-control" id="techtyl-ov-card-text" name="text" type="text" maxlength="{{ $ov['customize']['text_max'] }}" value="{{ old('text') }}">
                </div>
                <fieldset class="form-group techtyl-ov-fields-wide techtyl-ov-iconpick">
                  <legend class="control-label">{{ $t('cards_field_icon') }}</legend>
                  @foreach ($ov['customize']['icons'] as $techtylIcon)
                    <label title="{{ $techtylIcon }}">
                      <input type="radio" name="icon" value="{{ $techtylIcon }}" @if (old('icon', $ov['customize']['icons'][0]) === $techtylIcon) checked @endif>
                      <i class="fa fa-{{ $techtylIcon }}" aria-hidden="true"></i><span class="techtyl-ov-sr">{{ $techtylIcon }}</span>
                    </label>
                  @endforeach
                </fieldset>
              </div>
              <div class="techtyl-ov-customize-foot">
                <button type="submit" class="btn btn-sm btn-primary">{{ $t('add_button') }}</button>
              </div>
            @endif
          </form>
        </div>
      </details>
    @endif
  </div>

  {{-- Cards (order and visibility from Cards::config()) --}}
  @if (count($ov['cards']) === 0)
    <p class="techtyl-ov-nocards">{{ $t('no_cards') }}</p>
  @else
    <div class="techtyl-ov-kpis">
      @foreach ($ov['cards'] as $techtylCard)
        @php $techtylTag = $techtylCard['link'] !== null ? 'a' : 'div'; @endphp
        <{{ $techtylTag }} class="techtyl-ov-kpi techtyl-ov-kpi--{{ $techtylCard['kind'] }} @if ($techtylCard['wide']) techtyl-ov-kpi--wide @endif"
          data-card="{{ $techtylCard['id'] }}"
          @if ($techtylCard['link'] !== null) href="{{ $techtylCard['link'] }}" @if ($techtylCard['external']) target="_blank" rel="noopener noreferrer" @endif @endif>
          <span class="techtyl-ov-kpi-label"><i class="fa fa-{{ $techtylCard['icon'] }}" aria-hidden="true"></i><span>{{ $techtylCard['title'] }}</span>@if ($techtylCard['external'])<span class="techtyl-ov-sr"> {{ $t('new_tab') }}</span>@endif</span>

          @if ($techtylCard['value'] !== null)
            <span class="techtyl-ov-kpi-value">
              @if ($techtylCard['count'] !== null)
                <span data-count="{{ $techtylCard['count'] }}" data-decimals="{{ $techtylCard['decimals'] }}">{{ $techtylCard['value'] }}</span>
              @else
                <span>{{ $techtylCard['value'] }}</span>
              @endif
              @if ($techtylCard['unit'] !== null)<span class="techtyl-ov-unit">{{ $techtylCard['unit'] }}</span>@endif
            </span>
          @endif

          @if ($techtylCard['text'] !== null && $techtylCard['text'] !== '')
            <span class="techtyl-ov-kpi-text">{{ $techtylCard['text'] }}</span>
          @endif

          @if ($techtylCard['list'] !== null)
            @if (count($techtylCard['list']) === 0)
              <span class="techtyl-ov-kpi-sub techtyl-ov-kpi-empty">{{ $techtylCard['empty'] }}</span>
            @else
              <span class="techtyl-ov-minilist">
                @foreach ($techtylCard['list'] as $techtylRow)
                  <span class="techtyl-ov-minirow">
                    <span class="techtyl-ov-minirow-name">{{ $techtylRow['name'] }} <span class="techtyl-ov-muted">{{ $techtylRow['meta'] }}</span></span>
                    <b class="techtyl-ov-num">{{ $techtylRow['value'] }}</b>
                    <span class="techtyl-ov-meter" data-p="{{ $techtylRow['percent'] }}" aria-hidden="true"><span class="techtyl-ov-meter-fill"></span></span>
                  </span>
                @endforeach
              </span>
            @endif
          @endif

          @if ($techtylCard['meter'] !== null || $techtylCard['stack'] !== null || $techtylCard['legend'] !== [] || $techtylCard['sub'] !== null)
            <span class="techtyl-ov-kpi-foot">
              @if ($techtylCard['stack'] !== null)
                <span class="techtyl-ov-stack" role="img" aria-label="{{ $techtylCard['stack_aria'] }}">
                  @foreach ($techtylCard['stack'] as $techtylPart)
                    <span class="techtyl-ov-stack-part techtyl-ov-stack-part--{{ $techtylPart['tone'] }}" data-share="{{ $techtylPart['share'] }}"></span>
                  @endforeach
                </span>
              @endif
              @if ($techtylCard['meter'] !== null)
                <span class="techtyl-ov-meter techtyl-ov-meter--{{ $techtylCard['meter']['tone'] }}" data-p="{{ $techtylCard['meter']['percent'] }}" role="img"
                  aria-label="{{ $techtylCard['meter']['aria'] }}"><span class="techtyl-ov-meter-fill"></span></span>
              @endif
              @if ($techtylCard['legend'] !== [])
                <span class="techtyl-ov-legend">
                  @foreach ($techtylCard['legend'] as $techtylItem)
                    <span><span class="{{ $techtylDot($techtylItem['tone']) }}" aria-hidden="true"></span>{{ $techtylItem['text'] }}</span>
                  @endforeach
                </span>
              @endif
              @if ($techtylCard['sub'] !== null)
                <span class="techtyl-ov-kpi-sub">@if ($techtylCard['sub_tone'] !== null)<span class="{{ $techtylDot($techtylCard['sub_tone']) }}" aria-hidden="true"></span>@endif @if ($techtylCard['sub_strong'] !== null)<b class="techtyl-ov-num">{{ $techtylCard['sub_strong'] }}</b> @endif{{ $techtylCard['sub'] }}</span>
              @endif
            </span>
          @endif
        </{{ $techtylTag }}>
      @endforeach
    </div>
  @endif

  <div class="techtyl-ov-grid">
    <div class="techtyl-ov-main">

      {{-- Allocation per node --}}
      <section class="techtyl-ov-card techtyl-ov-nodes" aria-labelledby="techtyl-ov-nodes-title">
        <header class="techtyl-ov-card-head">
          <h2 id="techtyl-ov-nodes-title">{{ $t('nodes_title') }}</h2>
          <span class="techtyl-ov-card-note">{{ $t('nodes_note') }}</span>
          <a class="techtyl-ov-more" href="{{ route('admin.nodes') }}">{{ $t('nodes_all') }}</a>
        </header>
        @if (count($ov['nodes']) === 0)
          <div class="techtyl-ov-empty">
            <p><strong>{{ $t('nodes_empty_title') }}</strong> {{ $t('nodes_empty_text') }}</p>
            <a class="btn btn-sm btn-primary" href="{{ route('admin.nodes.new') }}">{{ $t('nodes_create') }}</a>
          </div>
        @else
          <ul class="techtyl-ov-nodelist">
            @foreach ($ov['nodes'] as $techtylNode)
              <li class="techtyl-ov-node">
                <div class="techtyl-ov-node-name">
                  <a href="{{ route('admin.nodes.view', $techtylNode['id']) }}">{{ $techtylNode['name'] }}</a>
                  <span class="techtyl-ov-node-meta">
                    @if ($techtylNode['location'] !== ''){{ $techtylNode['location'] }} · @endif{{ $plural($techtylNode['servers'], 'node_servers_one', 'node_servers_many') }}
                    @if ($techtylNode['maintenance'])
                      <span class="techtyl-ov-flag"><span class="{{ $techtylDot('warn') }}" aria-hidden="true"></span>{{ $t('maintenance') }}</span>
                    @endif
                  </span>
                </div>
                @foreach ([['memory', 'meter_memory'], ['disk', 'meter_disk']] as [$techtylKey, $techtylLabel])
                  @php $techtylMeter = $techtylNode[$techtylKey]; @endphp
                  <div class="techtyl-ov-node-meter">
                    <span class="techtyl-ov-node-meter-label">
                      <span>{{ $t($techtylLabel) }}</span>
                      <b class="techtyl-ov-num">{{ $techtylMeter['percent_text'] ?? '–' }}</b>
                    </span>
                    <span class="techtyl-ov-meter techtyl-ov-meter--{{ $techtylMeter['tone'] }}" data-p="{{ $techtylMeter['percent'] ?? 0 }}" role="img"
                      aria-label="{{ $t('meter_aria', ['label' => $t($techtylLabel), 'percent' => $techtylMeter['percent'] ?? 0]) }}"><span class="techtyl-ov-meter-fill"></span></span>
                    <span class="techtyl-ov-node-meter-sub techtyl-ov-num">{{ $techtylMeter['used']['text'] }} / {{ $techtylMeter['capacity_text'] }}@if ($techtylMeter['used']['unit'] !== $techtylMeter['capacity']['unit']) <span class="techtyl-ov-muted">{{ $t('unit_allocated', ['unit' => $techtylMeter['used']['unit']]) }}</span>@endif</span>
                  </div>
                @endforeach
              </li>
            @endforeach
          </ul>
          @if ($ov['node_more'] > 0)
            <p class="techtyl-ov-card-foot">{{ $t('more', ['count' => $n($ov['node_more'])]) }} – <a href="{{ route('admin.nodes') }}">{{ $t('show_all') }}</a></p>
          @endif
        @endif
      </section>

      {{-- Recently created servers --}}
      <section class="techtyl-ov-card techtyl-ov-recent" aria-labelledby="techtyl-ov-recent-title">
        <header class="techtyl-ov-card-head">
          <h2 id="techtyl-ov-recent-title">{{ $t('recent_title') }}</h2>
          <a class="techtyl-ov-more" href="{{ route('admin.servers') }}">{{ $t('recent_all') }}</a>
        </header>
        @if (count($ov['recent']) === 0)
          <div class="techtyl-ov-empty">
            <p><strong>{{ $t('recent_empty_title') }}</strong> {{ $t('recent_empty_text') }}</p>
            <a class="btn btn-sm btn-primary" href="{{ route('admin.servers.new') }}">{{ $t('recent_create') }}</a>
          </div>
        @else
          <table class="techtyl-ov-table">
            <thead>
              <tr><th scope="col">{{ $t('col_server') }}</th><th scope="col">{{ $t('col_node') }}</th><th scope="col">{{ $t('col_owner') }}</th><th scope="col" class="techtyl-ov-right">{{ $t('col_created') }}</th></tr>
            </thead>
            <tbody>
              @foreach ($ov['recent'] as $techtylServer)
                <tr>
                  <td data-cell="server">
                    <a class="techtyl-ov-strong" href="{{ route('admin.servers.view', $techtylServer['id']) }}">{{ $techtylServer['name'] }}</a>
                    <span class="techtyl-ov-mono techtyl-ov-muted">{{ $techtylServer['short'] }}</span>
                    @if ($techtylServer['state'] !== null)
                      <span class="techtyl-ov-flag"><span class="{{ $techtylDot($techtylServer['state']['tone']) }}" aria-hidden="true"></span>{{ $techtylServer['state']['label'] }}</span>
                    @endif
                  </td>
                  <td data-cell="node" data-label="{{ $t('col_node') }}"><a href="{{ route('admin.nodes.view', $techtylServer['node_id']) }}">{{ $techtylServer['node_name'] }}</a></td>
                  <td data-cell="owner" data-label="{{ $t('col_owner') }}"><a href="{{ route('admin.users.view', $techtylServer['user_id']) }}">{{ $techtylServer['username'] }}</a></td>
                  <td data-cell="created" class="techtyl-ov-right techtyl-ov-muted">{{ $techtylServer['ago'] ?? '–' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @endif
      </section>

      {{-- Application API --}}
      <section class="techtyl-ov-card techtyl-ov-keys" aria-labelledby="techtyl-ov-keys-title">
        <header class="techtyl-ov-card-head">
          <h2 id="techtyl-ov-keys-title">{{ $t('keys_title') }}</h2>
          <span class="techtyl-ov-card-note">{{ $plural($ov['key_count'], 'keys_count_one', 'keys_count_many') }}</span>
          <a class="techtyl-ov-more" href="{{ route('admin.api.index') }}">{{ $t('keys_manage') }}</a>
        </header>
        @if (count($ov['keys']) === 0)
          <div class="techtyl-ov-empty">
            <p><strong>{{ $t('keys_empty_title') }}</strong> {{ $t('keys_empty_text') }}</p>
            <a class="btn btn-sm" href="{{ route('admin.api.new') }}">{{ $t('keys_create') }}</a>
          </div>
        @else
          <table class="techtyl-ov-table">
            <thead>
              <tr><th scope="col">{{ $t('col_description') }}</th><th scope="col">{{ $t('col_identifier') }}</th><th scope="col" class="techtyl-ov-right">{{ $t('col_last_used') }}</th></tr>
            </thead>
            <tbody>
              @foreach ($ov['keys'] as $techtylKeyRow)
                <tr>
                  <td data-cell="description">@if ($techtylKeyRow['memo'] !== '')<span class="techtyl-ov-strong">{{ $techtylKeyRow['memo'] }}</span>@else<span class="techtyl-ov-muted">{{ $t('no_description') }}</span>@endif</td>
                  <td data-cell="identifier"><span class="techtyl-ov-mono">{{ $techtylKeyRow['identifier'] }}</span></td>
                  <td data-cell="used" class="techtyl-ov-right @if ($techtylKeyRow['used_ago'] === null) techtyl-ov-muted @endif">{{ $techtylKeyRow['used_ago'] ?? $t('never') }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
          @if ($ov['key_count'] > count($ov['keys']))
            <p class="techtyl-ov-card-foot">{{ $t('more', ['count' => $n($ov['key_count'] - count($ov['keys']))]) }} – <a href="{{ route('admin.api.index') }}">{{ $t('show_all') }}</a></p>
          @endif
        @endif
      </section>
    </div>

    <div class="techtyl-ov-side">

      {{-- Quick actions --}}
      <section class="techtyl-ov-card techtyl-ov-actions" aria-labelledby="techtyl-ov-actions-title">
        <header class="techtyl-ov-card-head">
          <h2 id="techtyl-ov-actions-title">{{ $t('actions_title') }}</h2>
        </header>
        <div class="techtyl-ov-actiongrid">
          <a class="techtyl-ov-action" href="{{ route('admin.servers.new') }}"><i class="fa fa-plus" aria-hidden="true"></i><span>{{ $t('action_server') }}</span></a>
          <a class="techtyl-ov-action" href="{{ route('admin.users.new') }}"><i class="fa fa-user-plus" aria-hidden="true"></i><span>{{ $t('action_user') }}</span></a>
          <a class="techtyl-ov-action" href="{{ route('admin.nodes.new') }}"><i class="fa fa-sitemap" aria-hidden="true"></i><span>{{ $t('action_node') }}</span></a>
          <a class="techtyl-ov-action" href="{{ route('admin.settings') }}"><i class="fa fa-sliders" aria-hidden="true"></i><span>{{ $t('action_settings') }}</span></a>
        </div>
      </section>

      {{-- System --}}
      <section class="techtyl-ov-card techtyl-ov-system" aria-labelledby="techtyl-ov-system-title">
        <header class="techtyl-ov-card-head">
          <h2 id="techtyl-ov-system-title">{{ $t('system_title') }}</h2>
        </header>
        <dl class="techtyl-ov-facts">
          <div>
            <dt>{{ $t('sys_nodes') }}</dt>
            <dd><a href="{{ route('admin.nodes') }}" class="techtyl-ov-num">{{ $n($ov['node_count']) }}</a>@if ($ov['maintenance'] > 0) <span class="techtyl-ov-muted">· {{ $t('sys_maintenance', ['count' => $n($ov['maintenance'])]) }}</span>@endif</dd>
          </div>
          <div>
            <dt>{{ $t('sys_locations') }}</dt>
            <dd><a href="{{ route('admin.locations') }}" class="techtyl-ov-num">{{ $n($ov['locations']) }}</a></dd>
          </div>
          <div>
            <dt>{{ $t('sys_allocations') }}</dt>
            <dd class="techtyl-ov-num">{{ $t('alloc_summary', ['used' => $n($ov['allocations']['used']), 'free' => $n($ov['allocations']['free']), 'total' => $n($ov['allocations']['total'])]) }}</dd>
            @if ($ov['allocations']['percent'] !== null)
              <dd class="techtyl-ov-facts-bar">
                <span class="techtyl-ov-meter techtyl-ov-meter--{{ $ov['allocations']['percent'] >= 90 ? 'warn' : 'ok' }}" data-p="{{ $ov['allocations']['percent'] }}" role="img"
                  aria-label="{{ $t('alloc_aria', ['percent' => $ov['allocations']['percent']]) }}"><span class="techtyl-ov-meter-fill"></span></span>
                <span class="techtyl-ov-num">{{ \Pterodactyl\BlueprintFramework\Extensions\{identifier}\Overview\Overview::percentText($ov['allocations']['percent']) }}</span>
              </dd>
            @endif
          </div>
          <div>
            <dt>{{ $t('sys_databases') }}</dt>
            <dd><a href="{{ route('admin.databases') }}" class="techtyl-ov-num">{{ $n($ov['databases']) }}</a> <span class="techtyl-ov-muted">{{ $plural($ov['database_hosts'], 'db_hosts_one', 'db_hosts_many') }}</span></dd>
          </div>
          <div>
            <dt>{{ $t('sys_nests') }}</dt>
            <dd><a href="{{ route('admin.nests') }}" class="techtyl-ov-num">{{ $n($ov['nests']) }} / {{ $n($ov['eggs']) }}</a></dd>
          </div>
          <div>
            <dt>{{ $t('sys_panel') }}</dt>
            <dd>
              <span class="techtyl-ov-mono">{{ $ov['update']['current'] }}</span>
              @if ($ov['update']['state'] === 'current')
                <span class="techtyl-ov-flag"><span class="{{ $techtylDot('ok') }}" aria-hidden="true"></span>{{ $t('panel_current') }}</span>
              @elseif ($ov['update']['state'] === 'available')
                <span class="techtyl-ov-flag"><span class="{{ $techtylDot('warn') }}" aria-hidden="true"></span>{{ $t('panel_available', ['version' => $ov['update']['latest']]) }}</span>
              @else
                <span class="techtyl-ov-flag techtyl-ov-muted">{{ $t('panel_unknown') }}</span>
              @endif
            </dd>
          </div>
          <div>
            <dt>{{ $t('sys_php') }}</dt>
            <dd class="techtyl-ov-mono">{{ $ov['system']['php'] }}</dd>
          </div>
          <div>
            <dt>{{ $t('sys_laravel') }}</dt>
            <dd class="techtyl-ov-mono">{{ $ov['system']['laravel'] }}</dd>
          </div>
          <div class="techtyl-ov-facts-wide">
            <dt>{{ $t('sys_os') }}</dt>
            <dd class="techtyl-ov-mono">{{ $ov['system']['os'] }}</dd>
          </div>
        </dl>
      </section>

      {{-- Help and project (replaces the four buttons of the panel page) --}}
      <nav class="techtyl-ov-card techtyl-ov-help" aria-label="{{ $t('help_label') }}">
        <a href="{{ $ov['update']['discord'] }}" target="_blank" rel="noopener noreferrer"><i class="fa fa-life-ring" aria-hidden="true"></i>{{ $t('help_discord') }}</a>
        <a href="https://pterodactyl.io" target="_blank" rel="noopener noreferrer"><i class="fa fa-book" aria-hidden="true"></i>{{ $t('help_docs') }}</a>
        <a href="https://github.com/pterodactyl/panel" target="_blank" rel="noopener noreferrer"><i class="fa fa-github" aria-hidden="true"></i>{{ $t('help_github') }}</a>
        <a href="{{ $ov['update']['donate'] }}" target="_blank" rel="noopener noreferrer"><i class="fa fa-heart-o" aria-hidden="true"></i>{{ $t('help_donate') }}</a>
      </nav>
    </div>
  </div>

</div>
