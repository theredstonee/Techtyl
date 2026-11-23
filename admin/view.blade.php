<div class="techtyl-admin">
  <ul class="nav nav-tabs techtyl-tabs" role="tablist">
    <li class="@if ($tab === 'design') active @endif"><a href="{{ $root }}">Design</a></li>
    <li class="@if ($tab === 'modules') active @endif"><a href="{{ $root }}?tab=modules">Module</a></li>
    @foreach ($modules as $key => $module)
      @if ($moduleStates[$key])
        <li class="@if ($tab === $key) active @endif"><a href="{{ $root }}?tab={{ $key }}">{{ $module[1] }}</a></li>
      @endif
    @endforeach
  </ul>

  @if ($tab === 'design')
  @php($dt = fn (string $key, array $replace = []) => \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::t('designer.' . $key, $replace))
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
      Die Datenbank-Tabellen von Techtyl fehlen noch. Eigene Bilder, Banner und Ordner funktionieren erst nach
      <code>php artisan migrate --force</code> im Panel-Ordner (bei Docker-Installationen fuehrt Blueprint diesen Schritt nicht selbst aus).
    </div>
  @endif

  <div class="row">
    <div class="col-md-12">
      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">Standard-Banner je Egg</h3></div>
        <div class="box-body">
          <p class="text-muted small">Server ohne eigenes Banner zeigen das Banner ihres Eggs.</p>
          @if ($tablesReady)
            <form method="POST" action="{{ $root }}" enctype="multipart/form-data">
              {{ csrf_field() }}
              <input type="hidden" name="action" value="egg-banner">
              <div class="form-group">
                <label for="techtyl-egg">Egg</label>
                <select class="form-control" id="techtyl-egg" name="egg_id" required>
                  @foreach ($eggs as $egg)
                    <option value="{{ $egg->id }}">{{ $egg->nest }} / {{ $egg->name }}@if (isset($eggBanners[$egg->id])) (hat Banner)@endif</option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
                <label for="techtyl-egg-file">Bild</label>
                <input type="file" id="techtyl-egg-file" name="image" accept="image/jpeg,image/png,image/webp" required>
              </div>
              <button type="submit" class="btn btn-primary">Banner speichern</button>
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
                          <button type="submit" class="btn btn-danger btn-xs">Entfernen</button>
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
          <div class="box-header with-border"><h3 class="box-title">Module</h3></div>
          <form method="POST" action="{{ $root }}">
            {{ csrf_field() }}
            <input type="hidden" name="action" value="modules">
            <div class="box-body">
              <p class="text-muted">Abgeschaltete Module sind fuer Nutzer nicht erreichbar. Ihre Daten bleiben erhalten.</p>
              @foreach ($modules as $key => $module)
                <div class="form-group techtyl-module">
                  <input type="hidden" name="modules[{{ $key }}]" value="0">
                  <label><input type="checkbox" name="modules[{{ $key }}]" value="1" @if ($moduleStates[$key]) checked @endif> {{ $module[1] }}</label>
                  <p class="text-muted small">{{ $module[2] }}</p>
                </div>
              @endforeach
            </div>
            <div class="box-footer">
              <button type="submit" class="btn btn-primary pull-right">Module speichern</button>
            </div>
          </form>
        </div>
      </div>
      <div class="col-md-4">
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
  @endif

  @if (isset($modules[$tab]))
    @if (!empty($moduleData['moduleError']))
      <div class="alert alert-danger">Die Einstellungen dieses Moduls konnten nicht geladen werden. Details stehen im Panel-Log.</div>
    @elseif ($moduleView !== null)
      @include($moduleView, $moduleData)
    @else
      <div class="alert alert-info">Dieses Modul hat noch keine Einstellungen.</div>
    @endif
  @endif
</div>
