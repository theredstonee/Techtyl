<!DOCTYPE html>
<html lang="{{ $lang }}" data-techtyl-variant="{{ $variant }}" data-techtyl-motion="{{ $motion }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="referrer" content="no-referrer">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title') · {{ $appName }}</title>
  <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png" sizes="32x32">
  <link rel="preload" href="/extensions/{identifier}/fonts/bricolage-grotesque-var.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="{{ $base }}/auth.css?v={version}">
  <link rel="stylesheet" href="{{ $base }}/theme.css">
  <script src="{{ $base }}/auth.js?v={version}" defer></script>
  @if ($turnstileKey !== '')
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
  @endif
</head>
<body>
  <div class="ta-backdrop" aria-hidden="true"></div>
  <main class="ta-stage">
    <a class="ta-mark" href="/auth/login" aria-label="{{ $appName }}, {{ $t('to_login') }}">{{ $appName }}<i aria-hidden="true"></i></a>
    <section class="ta-sheet" aria-labelledby="ta-title">
      @yield('content')
    </section>
    <p class="ta-foot">@yield('foot')</p>
  </main>
</body>
</html>
