@extends('blueprint.extensions.{identifier}.auth.layout')

@section('title', $t('sent_page'))

@section('content')
  @include('blueprint.extensions.{identifier}.auth.steps', ['step' => 2])

  <div class="ta-glyph ta-glyph-mail" aria-hidden="true"><span></span></div>
  <h1 id="ta-title">{{ $t('sent_title') }}</h1>
  <p class="ta-lead">
    {{ $t('sent_lead_before') }} <strong class="ta-break">{{ $email }}</strong> {{ $t('sent_lead_after', ['minutes' => $minutes]) }}
  </p>

  @if ($resent)
    <p class="ta-alert is-ok" role="status">{{ $t('resent_ok') }}</p>
  @endif

  <ul class="ta-list">
    <li>{{ $t('tip_spam') }}</li>
    <li>{{ $t('tip_existing_before') }} <a href="/auth/login">{{ $t('tip_existing_link') }}</a> {{ $t('tip_existing_after') }}</li>
  </ul>

  <form method="POST" action="{{ $base }}/resend" class="ta-form ta-form-quiet" data-ta-form data-ta-wait="60">
    {{ csrf_field() }}
    @include('blueprint.extensions.{identifier}.auth.turnstile')
    <button type="submit" class="ta-button is-ghost">
      <span class="ta-button-label">{{ $t('resend') }}</span>
      <span class="ta-button-busy" aria-hidden="true"></span>
    </button>
    <p class="ta-hint" data-ta-wait-text hidden>{{ $t('resend_wait_before') }} <span data-ta-wait-seconds>60</span> {{ $t('resend_wait_after') }}</p>
  </form>
@endsection

@section('foot')
  {{ $t('wrong_address') }} <a href="{{ $base }}/register">{{ $t('start_over') }}</a>
@endsection
