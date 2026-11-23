@extends('blueprint.extensions.{identifier}.auth.layout')

@section('title', $t('register_title'))

@section('content')
  @include('blueprint.extensions.{identifier}.auth.steps', ['step' => 1])

  <h1 id="ta-title">{{ $t('register_title') }}</h1>
  <p class="ta-lead">{{ $t('register_lead') }}</p>

  @if ($errors->any())
    <p class="ta-alert" role="alert">{{ $t('check_fields') }}</p>
  @endif

  <form method="POST" action="{{ $base }}/register" class="ta-form" data-ta-form novalidate>
    {{ csrf_field() }}

    <div class="ta-field @error('email') has-error @enderror">
      <label for="ta-email">{{ $t('email') }}</label>
      <input id="ta-email" name="email" type="email" inputmode="email" autocomplete="email" maxlength="191" required autofocus
             value="{{ old('email') }}" @error('email') aria-invalid="true" aria-describedby="ta-email-error" @enderror>
      @error('email')
        <p class="ta-error" id="ta-email-error">{{ $message }}</p>
      @enderror
    </div>

    <div class="ta-field @error('username') has-error @enderror">
      <label for="ta-username">{{ $t('username') }}</label>
      <input id="ta-username" name="username" type="text" autocomplete="username" autocapitalize="none" spellcheck="false"
             minlength="3" maxlength="32" required data-ta-lower value="{{ old('username') }}"
             aria-describedby="ta-username-hint @error('username') ta-username-error @enderror" @error('username') aria-invalid="true" @enderror>
      @error('username')
        <p class="ta-error" id="ta-username-error">{{ $message }}</p>
      @enderror
      <p class="ta-hint" id="ta-username-hint">{{ $t('username_hint') }}</p>
    </div>

    <div class="ta-field @error('name') has-error @enderror">
      <label for="ta-name">{{ $t('name') }} <span class="ta-optional">{{ $t('optional') }}</span></label>
      <input id="ta-name" name="name" type="text" autocomplete="name" maxlength="120" value="{{ old('name') }}"
             aria-describedby="ta-name-hint" @error('name') aria-invalid="true" @enderror>
      @error('name')
        <p class="ta-error">{{ $message }}</p>
      @enderror
      <p class="ta-hint" id="ta-name-hint">{{ $t('name_hint') }}</p>
    </div>

    @if ($inviteRequired)
      <div class="ta-field @error('invite') has-error @enderror">
        <label for="ta-invite">{{ $t('invite') }}</label>
        <input id="ta-invite" name="invite" type="text" class="ta-code" autocomplete="off" autocapitalize="characters" spellcheck="false"
               maxlength="40" required data-ta-invite placeholder="XXXXX-XXXXX-XXXXX-XXXXX" value="{{ old('invite') }}"
               @error('invite') aria-invalid="true" aria-describedby="ta-invite-error" @enderror>
        @error('invite')
          <p class="ta-error" id="ta-invite-error">{{ $message }}</p>
        @enderror
      </div>
    @endif

    @include('blueprint.extensions.{identifier}.auth.turnstile')

    <button type="submit" class="ta-button">
      <span class="ta-button-label">{{ $t('submit_register') }}</span>
      <span class="ta-button-busy" aria-hidden="true"></span>
    </button>
  </form>
@endsection

@section('foot')
  {{ $t('have_account') }} <a href="/auth/login">{{ $t('sign_in') }}</a>
@endsection
