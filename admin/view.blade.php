<div class="techtyl-admin">
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
</div>
