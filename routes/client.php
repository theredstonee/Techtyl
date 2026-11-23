<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Middleware\Api\Client\Server\AuthenticateServerAccess;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Http\ClientController;

/*
| Client-API von Techtyl. Praefix: /api/client/extensions/techtyl
| Anmeldung (Sitzung oder Client-API-Schluessel) und das allgemeine Limit der Client-API
| setzt Blueprint fuer die ganze Gruppe. Uploads haben zusaetzlich ein eigenes, engeres Limit
| je Nutzer.
*/
Route::get('/bootstrap', [ClientController::class, 'bootstrap']);

Route::get('/settings', [ClientController::class, 'settings']);
Route::patch('/settings', [ClientController::class, 'updateSettings']);

Route::post('/background', [ClientController::class, 'uploadBackground'])->middleware('throttle:12,1,techtyl-upload');
Route::delete('/background', [ClientController::class, 'deleteBackground']);

// {server} loest die Panel-Middleware wie in der uebrigen Client-API auf (UUID oder Kurz-ID);
// AuthenticateServerAccess laesst nur Besitzer, Subuser und Root-Admins durch.
Route::middleware(AuthenticateServerAccess::class)->group(function () {
    Route::post('/servers/{server}/banner', [ClientController::class, 'uploadBanner'])->middleware('throttle:12,1,techtyl-upload');
    Route::delete('/servers/{server}/banner', [ClientController::class, 'deleteBanner']);
});

Route::get('/folders', [ClientController::class, 'folderIndex']);
Route::post('/folders', [ClientController::class, 'folderStore']);
Route::put('/folders/order', [ClientController::class, 'folderOrder']);
Route::patch('/folders/{folder}', [ClientController::class, 'folderUpdate'])->whereNumber('folder');
Route::delete('/folders/{folder}', [ClientController::class, 'folderDelete'])->whereNumber('folder');
Route::put('/folders/{folder}/servers', [ClientController::class, 'folderServers'])->whereNumber('folder');

// Routen der Module (app/Modules/<Ordner>/routes.client.php), jeweils unter /<schluessel>/...
\Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules::routes('client');
