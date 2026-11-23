@php($t = fn (string $key, array $replace = []) => \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::t('designer.' . $key, $replace))
<!doctype html>
<html lang="{{ $locale }}" data-motion="voll">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="robots" content="noindex">
  <meta name="color-scheme" content="dark">
  <title>{{ $t('page_title') }}</title>
  <link rel="icon" type="image/svg+xml" href="/extensions/{identifier}/logo.svg">
  <link rel="stylesheet" href="/extensions/{identifier}/designer/designer.css?v={{ $assetVersion }}">
  <script src="/extensions/{identifier}/designer/designer.js?v={{ $assetVersion }}" defer></script>
</head>
<body>
  <script type="application/json" id="dz-data">@json($state)</script>

  <div class="dz" id="dz" data-ready="nein">
    <nav class="dz-rail" aria-label="{{ $t('sections') }}">
      <a class="dz-brand" href="{{ $adminUrl }}" data-leave title="{{ $t('back_admin') }}">
        <svg viewBox="0 0 256 256" aria-hidden="true" class="dz-brand-mark">
          <rect width="256" height="256" rx="56" class="dz-brand-bg"/>
          <path d="M56 70h98v34h-32v88H88v-88H56z" class="dz-brand-t"/>
          <rect x="166" y="70" width="34" height="34" rx="7" class="dz-brand-dot"/>
        </svg>
        <span class="dz-sr">{{ $t('back_admin') }}</span>
      </a>
      <div class="dz-cats" id="dz-cats" role="tablist" aria-orientation="vertical" aria-label="{{ $t('sections') }}"></div>
      <div class="dz-rail-foot">
        <button type="button" class="dz-rail-btn" id="dz-help" aria-haspopup="dialog" title="{{ $t('shortcuts') }}">
          <span class="dz-ico" data-icon="keyboard"></span><span class="dz-sr">{{ $t('shortcuts') }}</span>
        </button>
      </div>
    </nav>

    <section class="dz-panel" aria-labelledby="dz-title">
      <header class="dz-head">
        <div class="dz-head-text">
          <p class="dz-eyebrow">{{ $t('eyebrow') }}</p>
          <h1 class="dz-title" id="dz-title">{{ $t('cat_style') }}</h1>
        </div>
        <div class="dz-head-tools">
          <button type="button" class="dz-icon-btn" id="dz-undo" title="{{ $t('undo_hint') }}" disabled>
            <span class="dz-ico" data-icon="undo"></span><span class="dz-sr">{{ $t('undo') }}</span>
          </button>
          <button type="button" class="dz-icon-btn" id="dz-redo" title="{{ $t('redo_hint') }}" disabled>
            <span class="dz-ico" data-icon="redo"></span><span class="dz-sr">{{ $t('redo') }}</span>
          </button>
        </div>
      </header>
      <div class="dz-scroll" id="dz-scroll">
        <div class="dz-content" id="dz-content" role="tabpanel" aria-labelledby="dz-title" tabindex="-1">
          <div class="dz-loading" aria-hidden="true"><i></i><i></i><i></i></div>
        </div>
      </div>
    </section>

    <main class="dz-stage" id="dz-stage" aria-label="{{ $t('preview') }}">
      <div class="dz-stage-bar">
        <div class="dz-seg" id="dz-pages" role="radiogroup" aria-label="{{ $t('preview_page') }}"></div>
        <div class="dz-stage-mid">
          <label class="dz-server-pick" id="dz-server-wrap" hidden>
            <span class="dz-sr">{{ $t('preview_server') }}</span>
            <select id="dz-server"></select>
          </label>
        </div>
        <div class="dz-seg dz-seg-icons" id="dz-devices" role="radiogroup" aria-label="{{ $t('screen_size') }}"></div>
        <button type="button" class="dz-icon-btn" id="dz-reload" title="{{ $t('reload_preview') }}">
          <span class="dz-ico" data-icon="reload"></span><span class="dz-sr">{{ $t('reload_preview') }}</span>
        </button>
        <button type="button" class="dz-icon-btn dz-stage-close" id="dz-stage-close" title="{{ $t('close_preview') }}">
          <span class="dz-ico" data-icon="close"></span><span class="dz-sr">{{ $t('close_preview') }}</span>
        </button>
      </div>
      <div class="dz-canvas" id="dz-canvas">
        <div class="dz-device" id="dz-device" data-device="desktop">
          <div class="dz-device-bar" aria-hidden="true">
            <span class="dz-dots"><i></i><i></i><i></i></span>
            <span class="dz-url" id="dz-url">/</span>
            <span class="dz-zoom" id="dz-zoom"></span>
          </div>
          <div class="dz-viewport" id="dz-viewport">
            <iframe id="dz-frame" title="{{ $t('preview_title') }}" src="about:blank"></iframe>
            <div class="dz-frame-state" id="dz-frame-state" data-state="laedt">
              <div class="dz-skeleton" aria-hidden="true"><i></i><b></b><b></b><b></b><u></u><u></u></div>
              <div class="dz-frame-error">
                <p class="dz-frame-error-title">{{ $t('preview_error_title') }}</p>
                <p class="dz-frame-error-text">{{ $t('preview_error_text') }}</p>
                <button type="button" class="dz-btn" id="dz-frame-retry">{{ $t('reload') }}</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <button type="button" class="dz-preview-fab" id="dz-preview-open">
      <span class="dz-ico" data-icon="eye"></span> {{ $t('open_preview') }}
    </button>

    <div class="dz-savebar" id="dz-savebar" role="region" aria-label="{{ $t('save_region') }}" aria-hidden="true">
      <span class="dz-savebar-pulse" aria-hidden="true"></span>
      <p class="dz-savebar-text" id="dz-savebar-text" aria-live="polite">{{ $t('unsaved') }}</p>
      <button type="button" class="dz-btn dz-btn-ghost" id="dz-discard" tabindex="-1">{{ $t('discard') }}</button>
      <button type="button" class="dz-btn dz-btn-go" id="dz-save" tabindex="-1">
        <span class="dz-btn-label">{{ $t('save') }}</span><kbd>{{ $t('save_key') }}</kbd>
      </button>
    </div>

    <div class="dz-toasts" id="dz-toasts" aria-live="polite" aria-atomic="false"></div>

    <div class="dz-modal" id="dz-modal" hidden>
      <div class="dz-modal-back" data-close></div>
      <div class="dz-modal-card" role="dialog" aria-modal="true" aria-labelledby="dz-modal-title" aria-describedby="dz-modal-text">
        <h2 class="dz-modal-title" id="dz-modal-title"></h2>
        <div class="dz-modal-text" id="dz-modal-text"></div>
        <div class="dz-modal-actions" id="dz-modal-actions"></div>
      </div>
    </div>
  </div>

  <noscript><p class="dz-noscript">{{ $t('noscript') }}</p></noscript>
</body>
</html>
