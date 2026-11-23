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
