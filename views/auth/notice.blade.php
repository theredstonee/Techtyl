@extends('blueprint.extensions.{identifier}.auth.layout')

@section('title', $title)

@section('content')
  <div class="ta-glyph ta-glyph-{{ $tone }}" aria-hidden="true"><span></span></div>
  <h1 id="ta-title">{{ $title }}</h1>
  <p class="ta-lead">{{ $text }}</p>
  <a class="ta-button" href="{{ $actionUrl }}"><span class="ta-button-label">{{ $actionLabel }}</span></a>
@endsection

@section('foot')
  @if ($actionUrl !== '/auth/login')
    <a href="/auth/login">{{ $t('to_login') }}</a>
  @endif
@endsection
