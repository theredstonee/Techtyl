{{-- Techtyl group in the admin sidebar. Included from layouts/admin.blade.php through
     data/patches/admin.json. Renders nothing if the class is missing or returns no entries. --}}
@php
  $techtylNavClass = '\Pterodactyl\BlueprintFramework\Extensions\{identifier}\AdminNav';
  try {
    $techtylNav = class_exists($techtylNavClass) ? $techtylNavClass::items() : [];
    $techtylNavTitle = count($techtylNav) > 0 ? $techtylNavClass::title() : '';
  } catch (\Throwable $techtylNavError) {
    $techtylNav = [];
    $techtylNavTitle = '';
  }
@endphp
@if (count($techtylNav) > 0)
  <li class="header techtyl-nav-header">{{ $techtylNavTitle }}</li>
  @foreach ($techtylNav as $techtylNavItem)
    <li class="techtyl-nav-item @if ($techtylNavItem['active']) active @endif @if ($techtylNavItem['count'] > 0) techtyl-nav-waiting @endif">
      <a href="{{ $techtylNavItem['href'] }}" @if ($techtylNavItem['active']) aria-current="page" @endif @if ($techtylNavItem['hint'] !== '') title="{{ $techtylNavItem['label'] }}: {{ $techtylNavItem['hint'] }}" @endif>
        <i class="fa {{ $techtylNavItem['icon'] }}"></i> <span>{{ $techtylNavItem['label'] }}</span>
        @if ($techtylNavItem['count'] > 0)
          <span class="techtyl-nav-count" aria-hidden="true">{{ $techtylNavItem['count'] > 99 ? '99+' : $techtylNavItem['count'] }}</span>
          <span class="sr-only">{{ $techtylNavItem['hint'] }}</span>
        @endif
      </a>
    </li>
  @endforeach
@endif
