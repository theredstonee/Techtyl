<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Middleware\Activity\ServerSubject;
use Pterodactyl\Http\Middleware\Api\Client\Server\ResourceBelongsToServer;
use Pterodactyl\Http\Middleware\Api\Client\Server\AuthenticateServerAccess;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\ToolAvailable;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\MetaController;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\ContentController;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\VersionController;

/*
| Client-API der Server-Werkzeuge. Praefix: /api/client/extensions/techtyl/tools
| Davor liegen bereits: Anmeldung, allgemeines Limit der Client-API, Modul-Schalter.
|
| Alles haengt an /servers/{server}: {server} wird wie in der Panel-API aufgeloest (UUID oder
| Kurz-ID), AuthenticateServerAccess laesst nur Besitzer, Subuser und Root-Admins durch.
| Das einzelne Recht prueft der FormRequest des Endpunkts (Http/Requests/*), ob das Werkzeug
| zum Servertyp passt die Middleware ToolAvailable.
|
| Weiteren Endpunkt ergaenzen: Route in die passende Gruppe (oder neue Gruppe mit
| ToolAvailable::class . ':<werkzeug>'), Controller-Methode mit einem FormRequest als erstem
| Parameter (ReadRequest, InstallRequest, RemoveRequest, VersionRequest oder ein neuer mit
| eigenem permission()).
*/
Route::prefix('/servers/{server}')
    ->middleware([ServerSubject::class, AuthenticateServerAccess::class, ResourceBelongsToServer::class])
    ->group(function () {
        Route::get('/meta', [MetaController::class, 'show']);
        Route::get('/tasks', [MetaController::class, 'tasks']);
        Route::get('/tasks/{task}', [MetaController::class, 'task'])->whereUuid('task');

        // Plugins und Mods (Modrinth)
        Route::prefix('/content')->middleware(ToolAvailable::class . ':content')->group(function () {
            Route::get('/filters', [ContentController::class, 'filters']);
            Route::get('/search', [ContentController::class, 'search'])->middleware('throttle:90,1,techtyl-tools-search');
            Route::get('/projects/{project}', [ContentController::class, 'project'])->middleware('throttle:90,1,techtyl-tools-read');
            Route::get('/projects/{project}/versions', [ContentController::class, 'versions'])->middleware('throttle:90,1,techtyl-tools-read');
            Route::get('/versions/{versionId}/dependencies', [ContentController::class, 'dependencies'])->middleware('throttle:60,1,techtyl-tools-deps');
            Route::get('/installed', [ContentController::class, 'installed']);
            Route::get('/updates', [ContentController::class, 'updates'])->middleware('throttle:30,1,techtyl-tools-updates');
            Route::post('/install', [ContentController::class, 'install'])->middleware('throttle:40,1,techtyl-tools-install');
            Route::post('/update', [ContentController::class, 'update'])->middleware('throttle:40,1,techtyl-tools-install');
            Route::post('/remove', [ContentController::class, 'remove'])->middleware('throttle:60,1,techtyl-tools-remove');
        });

        // Versionswechsler
        Route::prefix('/versions')->middleware(ToolAvailable::class . ':versions')->group(function () {
            Route::get('/', [VersionController::class, 'index']);
            Route::get('/builds', [VersionController::class, 'builds'])->middleware('throttle:60,1,techtyl-tools-read');
            Route::post('/preview', [VersionController::class, 'preview'])->middleware('throttle:30,1,techtyl-tools-preview');
            Route::post('/switch', [VersionController::class, 'switch'])->middleware('throttle:6,1,techtyl-tools-switch');
        });
    });
