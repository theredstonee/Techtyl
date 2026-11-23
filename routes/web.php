<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Http\MediaController;

/*
| Web-Routen von Techtyl. Praefix: /extensions/techtyl
|
| Bilder brauchen weder Sitzung noch Cookies, deshalb laeuft die Route ohne die Web-Middleware.
| Der Zugriff ist nicht an eine Anmeldung gebunden (der Standard-Hintergrund erscheint auch auf
| der Login-Seite); geschuetzt sind die Bilder durch nicht erratbare Namen.
*/
Route::get('/media/{file}', [MediaController::class, 'show'])
    ->where('file', '[a-f0-9]{40}')
    ->withoutMiddleware('blueprint')
    ->middleware('throttle:600,1,techtyl-media');

// Routen der Module (app/Modules/<Ordner>/routes.web.php), jeweils unter /<schluessel>/...
\Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules::routes('web');
