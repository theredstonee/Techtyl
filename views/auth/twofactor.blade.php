@extends('blueprint.extensions.{identifier}.auth.layout')

@section('title', $t('tf_page'))

@section('content')
  <div class="ta-glyph ta-glyph-key" aria-hidden="true"><span></span></div>
  <h1 id="ta-title">{{ $t('tf_title') }}</h1>
  <p class="ta-lead">{{ $t('tf_lead') }}</p>

  <p class="ta-alert" role="alert" data-ta-2fa-error hidden></p>

  <form class="ta-form" data-ta-2fa="{{ $token }}" novalidate
        data-err-many="{{ $t('tf_err_many') }}" data-err-session="{{ $t('tf_err_session') }}" data-err-recovery="{{ $t('tf_err_recovery') }}"
        data-err-code="{{ $t('tf_err_code') }}" data-err-network="{{ $t('tf_err_network') }}">
    <div class="ta-field" data-ta-2fa-code>
      <label for="ta-code">{{ $t('tf_code') }}</label>
      <input id="ta-code" name="authentication_code" type="text" class="ta-otp" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code"
             maxlength="6" required autofocus>
    </div>
    <div class="ta-field" data-ta-2fa-recovery hidden>
      <label for="ta-recovery">{{ $t('tf_recovery') }}</label>
      <input id="ta-recovery" name="recovery_token" type="text" class="ta-code" autocomplete="off" spellcheck="false" maxlength="20">
      <p class="ta-hint">{{ $t('tf_recovery_hint') }}</p>
    </div>

    <button type="submit" class="ta-button">
      <span class="ta-button-label">{{ $t('sign_in') }}</span>
      <span class="ta-button-busy" aria-hidden="true"></span>
    </button>
    <button type="button" class="ta-link" data-ta-2fa-switch data-alt="{{ $t('tf_use_app') }}">{{ $t('tf_use_recovery') }}</button>
  </form>
  <noscript><p class="ta-alert">{{ $t('js_required_page') }}</p></noscript>
@endsection

@section('foot')
  <a href="/auth/login">{{ $t('back_to_login') }}</a>
@endsection
