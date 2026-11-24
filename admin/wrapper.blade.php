@php
  $techtylDesign = \Pterodactyl\BlueprintFramework\Extensions\{identifier}\Design::fromStored(
    app(\Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Admin\BlueprintAdminLibrary::class)
      ->dbGet(\Pterodactyl\BlueprintFramework\Extensions\{identifier}\Design::TABLE, \Pterodactyl\BlueprintFramework\Extensions\{identifier}\Design::RECORD, '{}')
  );
  $techtylAttributes = \Pterodactyl\BlueprintFramework\Extensions\{identifier}\Design::attributes($techtylDesign);
@endphp
<style id="techtyl-vars">{!! \Pterodactyl\BlueprintFramework\Extensions\{identifier}\Design::toCss($techtylDesign) !!}</style>
<script id="techtyl-attributes" type="application/json">@json($techtylAttributes)</script>
<script src="/extensions/{identifier}/techtyl.js?v={version}" defer></script>
{{-- Lebender Hintergrund auf allen Admin-Seiten ausser der Uebersicht. Die Uebersicht hat darunter
     einen eigenen Block; der bleibt unangetastet. Die Zeichenflaeche liegt fest hinter der Seite. --}}
@if (!request()->routeIs('admin.index'))
<style>#techtyl-backdrop{position:fixed;inset:0;z-index:-1;pointer-events:none}</style>
<div id="techtyl-backdrop" aria-hidden="true"></div>
<script src="/extensions/{identifier}/backdrop.js?v={version}" defer></script>
@endif
{{-- Admin-Uebersicht: nur auf /admin (Route admin.index) fuer Root-Admins. render() prueft beides selbst
     und liefert null bei Fehlern; dann bleibt die Panel-Seite unveraendert. Andere Admin-Seiten
     kostet das nur den Routenvergleich. Das HTML ist fertig escaped (views/admin/overview.blade.php). --}}
@if (request()->routeIs('admin.index'))
  @php
    $techtylOverview = \Pterodactyl\BlueprintFramework\Extensions\{identifier}\Overview\Overview::render();
  @endphp
  @if ($techtylOverview !== null)
    {!! $techtylOverview !!}
    <script src="/extensions/{identifier}/admin/overview.js?v={version}"></script>
  @endif
@endif
