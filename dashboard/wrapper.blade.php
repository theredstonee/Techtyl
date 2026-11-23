@php
  // Angemeldet: wirksames Design des Nutzers (Admin-Standard + erlaubte eigene Werte), sonst der Admin-Standard.
  // Zwei Abfragen je Seitenaufruf: Admin-Einstellungen und die Zeile des Nutzers.
  $techtylDesign = \Pterodactyl\BlueprintFramework\Extensions\{identifier}\Settings::designForPage(auth()->id());
  $techtylAttributes = \Pterodactyl\BlueprintFramework\Extensions\{identifier}\Design::attributes($techtylDesign);
@endphp
<style id="techtyl-vars">{!! \Pterodactyl\BlueprintFramework\Extensions\{identifier}\Design::toCss($techtylDesign) !!}</style>
<div id="techtyl-backdrop" aria-hidden="true"></div>
<script id="techtyl-attributes" type="application/json">@json($techtylAttributes)</script>
<script src="/extensions/{identifier}/techtyl.js?v={version}" defer></script>
