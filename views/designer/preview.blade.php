{{-- Seite fuer die Live-Vorschau des Designers: das echte Panel, nur mit vorgegebenem Pfad und dem
     Empfaenger-Skript. preview-path.js setzt den Pfad, bevor die Panel-App startet. --}}
@extends('templates/wrapper', [
    'css' => ['body' => 'bg-neutral-800'],
])

@section('head')
    <script src="/extensions/{identifier}/designer/preview-path.js?v={{ $assetVersion }}" data-path="{{ $previewPath }}"></script>
    <script src="/extensions/{identifier}/designer/receiver.js?v={{ $assetVersion }}" defer></script>
@endsection

@if ($guest)
    {{-- Login-Vorschau: die App startet ohne angemeldeten Nutzer. --}}
    @section('user-data')
        @if (!empty($siteConfiguration))
            <script>
                window.SiteConfiguration = @json($siteConfiguration);
            </script>
        @endif
    @endsection
@endif

@section('container')
    <div id="modal-portal"></div>
    <div id="app"></div>
@endsection
