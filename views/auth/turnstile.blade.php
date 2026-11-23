@if ($turnstileKey !== '')
  <div class="ta-field ta-check @error('turnstile') has-error @enderror">
    <div class="cf-turnstile" data-sitekey="{{ $turnstileKey }}" data-theme="{{ $turnstileTheme }}" data-language="{{ $lang }}" data-size="flexible" data-action="techtyl"></div>
    <noscript><p class="ta-error">{{ $t('js_required_check') }}</p></noscript>
    @error('turnstile')
      <p class="ta-error" role="alert">{{ $message }}</p>
    @enderror
  </div>
@endif
