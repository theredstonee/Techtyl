@extends('blueprint.extensions.{identifier}.auth.layout')

@section('title', $t('password_page'))

@section('content')
  @include('blueprint.extensions.{identifier}.auth.steps', ['step' => 3])

  <h1 id="ta-title">{{ $t('password_title') }}</h1>
  <p class="ta-lead">
    @if ($provider)
      {{ $t('pw_lead_provider_before', ['provider' => $provider]) }} <strong class="ta-break">{{ $email }}</strong>{{ $lang === 'de' ? ' ' : '' }}{{ $t('pw_lead_provider_after') }}
    @else
      <strong class="ta-break">{{ $email }}</strong> {{ $t('pw_lead_after') }}
    @endif
  </p>

  @if ($errors->any())
    <p class="ta-alert" role="alert">{{ $t('check_fields') }}</p>
  @endif

  <form method="POST" action="{{ $action }}" class="ta-form" data-ta-form data-ta-password="{{ $minPassword }}"
        data-ta-show="{{ $t('show') }}" data-ta-hide="{{ $t('hide') }}" data-ta-count-text="{{ $t('count') }}" novalidate>
    {{ csrf_field() }}
    @if ($token)
      <input type="hidden" name="token" value="{{ $token }}">
    @endif

    <div class="ta-field @error('username') has-error @enderror">
      <label for="ta-username">{{ $t('username') }}</label>
      <input id="ta-username" name="username" type="text" autocomplete="username" autocapitalize="none" spellcheck="false"
             minlength="3" maxlength="32" required data-ta-lower value="{{ old('username', $username) }}"
             aria-describedby="ta-username-hint @error('username') ta-username-error @enderror" @error('username') aria-invalid="true" autofocus @enderror>
      @error('username')
        <p class="ta-error" id="ta-username-error">{{ $message }}</p>
      @enderror
      <p class="ta-hint" id="ta-username-hint">{{ $t('username_hint_final') }}</p>
    </div>

    <div class="ta-field @error('password') has-error @enderror">
      <label for="ta-password">{{ $t('password') }}</label>
      <div class="ta-reveal">
        <input id="ta-password" name="password" type="password" autocomplete="new-password" minlength="{{ $minPassword }}" maxlength="72" required
               aria-describedby="ta-password-rules @error('password') ta-password-error @enderror" @error('password') aria-invalid="true" @enderror
               @if (!$errors->has('username')) autofocus @endif>
        <button type="button" class="ta-reveal-toggle" data-ta-reveal aria-pressed="false" aria-controls="ta-password ta-password-confirmation">{{ $t('show') }}</button>
      </div>
      @error('password')
        <p class="ta-error" id="ta-password-error">{{ $message }}</p>
      @enderror
      <div class="ta-meter" aria-hidden="true"><span data-ta-meter></span></div>
      <ul class="ta-rules" id="ta-password-rules">
        <li data-ta-rule="length">{{ $t('rule_length', ['min' => $minPassword]) }} <span class="ta-count" data-ta-count></span></li>
        <li data-ta-rule="match">{{ $t('rule_match') }}</li>
      </ul>
    </div>

    <div class="ta-field">
      <label for="ta-password-confirmation">{{ $t('password_repeat') }}</label>
      <input id="ta-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" maxlength="72" required>
    </div>

    @if ($inviteField)
      <div class="ta-field @error('invite') has-error @enderror">
        <label for="ta-invite">{{ $t('invite') }}</label>
        <input id="ta-invite" name="invite" type="text" class="ta-code" autocomplete="off" autocapitalize="characters" spellcheck="false"
               maxlength="40" required data-ta-invite placeholder="XXXXX-XXXXX-XXXXX-XXXXX" @error('invite') aria-invalid="true" @enderror>
        @error('invite')
          <p class="ta-error">{{ $message }}</p>
        @enderror
      </div>
    @endif

    @if ($needsTurnstile)
      @include('blueprint.extensions.{identifier}.auth.turnstile')
    @endif

    <button type="submit" class="ta-button">
      <span class="ta-button-label">{{ $t('submit_account') }}</span>
      <span class="ta-button-busy" aria-hidden="true"></span>
    </button>
  </form>
@endsection

@section('foot')
  {{ $t('password_manager_tip') }}
@endsection
