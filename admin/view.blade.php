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
  <form method="POST" action="">
    {{ csrf_field() }}
    <input type="hidden" name="_method" value="PATCH">

    <div class="row">
      <div class="col-md-6">
        <div class="box">
          <div class="box-header with-border"><h3 class="box-title">Aussehen</h3></div>
          <div class="box-body">
            <div class="form-group">
              <label for="techtyl-variant">Variante</label>
              <select class="form-control" id="techtyl-variant" name="variant">
                @foreach ($variants as $option)
                  <option value="{{ $option }}" @if ($design['variant'] === $option) selected @endif>{{ ucfirst($option) }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group">
              <label for="techtyl-accent">Akzentfarbe</label>
              <input class="form-control" type="color" id="techtyl-accent" name="accent" value="{{ $design['accent'] }}">
            </div>
            <div class="form-group">
              <label for="techtyl-radius">Ecken (0 bis 22 px)</label>
              <input class="form-control" type="number" min="0" max="22" id="techtyl-radius" name="radius" value="{{ $design['radius'] }}">
            </div>
            <div class="form-group">
              <label for="techtyl-motion">Animationen</label>
              <select class="form-control" id="techtyl-motion" name="motion">
                @foreach ($motion as $option)
                  <option value="{{ $option }}" @if ($design['motion'] === $option) selected @endif>{{ ucfirst($option) }}</option>
                @endforeach
              </select>
            </div>
          </div>
        </div>

        <div class="box">
          <div class="box-header with-border"><h3 class="box-title">Hintergrund</h3></div>
          <div class="box-body">
            <div class="form-group">
              <label for="techtyl-background">Bildadresse</label>
              <input class="form-control" type="text" maxlength="500" id="techtyl-background" name="background" value="{{ $design['background'] }}" placeholder="https://... oder /extensions/techtyl/...">
              <p class="text-muted small">Leer lassen fuer einen einfarbigen Hintergrund. Erlaubt sind https-Adressen und Pfade auf diesem Panel.</p>
            </div>
            <div class="form-group">
              <label for="techtyl-dim">Abdunkeln (0 bis 90 %)</label>
              <input class="form-control" type="number" min="0" max="90" id="techtyl-dim" name="dim" value="{{ $design['dim'] }}">
            </div>
            <div class="form-group">
              <label for="techtyl-opacity">Deckkraft der Flaechen (35 bis 100 %)</label>
              <input class="form-control" type="number" min="35" max="100" id="techtyl-opacity" name="opacity" value="{{ $design['opacity'] }}">
            </div>
            <div class="form-group">
              <label for="techtyl-blur">Unschaerfe hinter Flaechen (0 bis 24 px)</label>
              <input class="form-control" type="number" min="0" max="24" id="techtyl-blur" name="blur" value="{{ $design['blur'] }}">
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="box">
          <div class="box-header with-border"><h3 class="box-title">Anordnung</h3></div>
          <div class="box-body">
            <div class="form-group">
              <label for="techtyl-navigation">Navigation</label>
              <select class="form-control" id="techtyl-navigation" name="navigation">
                <option value="sidebar" @if ($design['navigation'] === 'sidebar') selected @endif>Seitenleiste mit Gruppen</option>
                <option value="icons" @if ($design['navigation'] === 'icons') selected @endif>Schmale Icon-Leiste</option>
                <option value="top" @if ($design['navigation'] === 'top') selected @endif>Oben</option>
              </select>
            </div>
            <div class="form-group">
              <label for="techtyl-servers">Serverliste</label>
              <select class="form-control" id="techtyl-servers" name="server_layout">
                @foreach ($serverLayouts as $option)
                  <option value="{{ $option }}" @if ($design['server_layout'] === $option) selected @endif>{{ ucfirst($option) }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group">
              <input type="hidden" name="banners" value="0">
              <label><input type="checkbox" name="banners" value="1" @if ($design['banners']) checked @endif> Server-Banner anzeigen</label>
            </div>
            <div class="form-group">
              <label for="techtyl-banner-height">Banner-Hoehe (48 bis 160 px)</label>
              <input class="form-control" type="number" min="48" max="160" id="techtyl-banner-height" name="banner_height" value="{{ $design['banner_height'] }}">
            </div>
          </div>
          <div class="box-footer">
            <button type="submit" class="btn btn-primary pull-right">Design speichern</button>
          </div>
        </div>
      </div>
    </div>
  </form>

  @if (!$tablesReady)
    <div class="alert alert-warning">
      Die Datenbank-Tabellen von Techtyl fehlen noch. Eigene Bilder, Banner und Ordner funktionieren erst nach
      <code>php artisan migrate --force</code> im Panel-Ordner (bei Docker-Installationen fuehrt Blueprint diesen Schritt nicht selbst aus).
    </div>
  @endif

  <div class="row">
    <div class="col-md-6">
      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">Grenzen fuer Nutzer</h3></div>
        <form method="POST" action="{{ $root }}">
          {{ csrf_field() }}
          <input type="hidden" name="action" value="limits">
          <div class="box-body">
            <p class="text-muted small">Was Nutzer in ihrem eigenen Konto aendern duerfen. Abgeschaltete Punkte folgen dem Standard oben.</p>
            <div class="form-group">
              <input type="hidden" name="user_background" value="0">
              <label><input type="checkbox" name="user_background" value="1" @if ($limits['user_background']) checked @endif> Eigenes Hintergrundbild (samt Abdunkeln, Deckkraft, Unschaerfe)</label>
            </div>
            <div class="form-group">
              <input type="hidden" name="user_banners" value="0">
              <label><input type="checkbox" name="user_banners" value="1" @if ($limits['user_banners']) checked @endif> Eigene Banner fuer Server</label>
            </div>
            <div class="form-group">
              <input type="hidden" name="user_appearance" value="0">
              <label><input type="checkbox" name="user_appearance" value="1" @if ($limits['user_appearance']) checked @endif> Variante, Akzentfarbe, Ecken und Animationen aendern</label>
            </div>
            <div class="form-group">
              <input type="hidden" name="user_layout" value="0">
              <label><input type="checkbox" name="user_layout" value="1" @if ($limits['user_layout']) checked @endif> Anordnung aendern (Navigation, Serverliste, Banner)</label>
            </div>
            <div class="form-group">
              <label for="techtyl-max-upload">Groesste Bilddatei in KB ({{ $uploadMin }} bis {{ $uploadMax }})</label>
              <input class="form-control" type="number" min="{{ $uploadMin }}" max="{{ $uploadMax }}" id="techtyl-max-upload" name="max_upload_kb" value="{{ $limits['max_upload_kb'] }}">
              <p class="text-muted small">
                Erlaubt sind JPEG, PNG und WebP; jedes Bild wird auf dem Server neu kodiert.
                PHP nimmt auf diesem Server Dateien bis {{ $phpUploadLimit }} an.
                Lesbar sind hier: {{ $readableFormats ? implode(', ', $readableFormats) : 'keine (GD fehlt)' }}.
              </p>
            </div>
          </div>
          <div class="box-footer">
            <button type="submit" class="btn btn-primary pull-right">Grenzen speichern</button>
          </div>
        </form>
      </div>

      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">Standard-Hintergrundbild</h3></div>
        <div class="box-body">
          @if ($defaultBackground !== '')
            <p><img src="{{ $defaultBackground }}" alt="Aktueller Standard-Hintergrund" class="img-responsive img-thumbnail" width="320"></p>
            <form method="POST" action="{{ $root }}/background/0">
              {{ csrf_field() }}
              <input type="hidden" name="_method" value="DELETE">
              <button type="submit" class="btn btn-danger btn-sm">Bild entfernen</button>
            </form>
            <hr>
          @endif
          <form method="POST" action="{{ $root }}" enctype="multipart/form-data">
            {{ csrf_field() }}
            <input type="hidden" name="action" value="background">
            <div class="form-group">
              <label for="techtyl-background-file">Bild hochladen</label>
              <input type="file" id="techtyl-background-file" name="image" accept="image/jpeg,image/png,image/webp" required>
              <p class="text-muted small">Wird als Bildadresse im Standard-Design eingetragen und auf hoechstens 2560 x 1440 Pixel verkleinert.</p>
            </div>
            <button type="submit" class="btn btn-primary">Hochladen</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-md-6">
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
