<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Middleware\Activity\ServerSubject;
use Pterodactyl\Http\Middleware\Api\Client\Server\ResourceBelongsToServer;
use Pterodactyl\Http\Middleware\Api\Client\Server\AuthenticateServerAccess;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Logs;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\ToolAvailable;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\MetaController;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\LogsController;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\ContentController;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\WorldsController;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\PlayersController;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\VersionController;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\SoftwareController;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\PropertiesController;

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

        // Software (loader) switch. Same server-type gate as the version list.
        // Paths sit beside /versions, not under it: GET /software, GET /software/versions, POST /software/preview, POST /software/switch.
        // Reading needs startup.read. Switching re-checks startup.update, settings.reinstall and the image right.
        Route::middleware(ToolAvailable::class . ':versions')->group(function () {
            Route::get('/software', [SoftwareController::class, 'show']);
            Route::get('/software/versions', [SoftwareController::class, 'versions'])->middleware('throttle:60,1,techtyl-tools-software-read');
            Route::post('/software/preview', [SoftwareController::class, 'preview'])->middleware('throttle:30,1,techtyl-tools-software-preview');
            Route::post('/software/switch', [SoftwareController::class, 'switch'])->middleware('throttle:6,1,techtyl-tools-software-switch');
        });

        // server.properties
        Route::prefix('/properties')->middleware(ToolAvailable::class . ':properties')->group(function () {
            Route::get('/', [PropertiesController::class, 'show']);
            Route::post('/', [PropertiesController::class, 'save'])->middleware('throttle:30,1,techtyl-tools-props');
        });

        // Latest log and crash reports. Listing a directory needs file.read; reading needs file.read-content.
        Route::prefix('/logs')->middleware(ToolAvailable::class . ':logs')->group(function () {
            Route::get('/', [LogsController::class, 'show']);
            Route::get('/crashes', [LogsController::class, 'crashes']);
            Route::get('/crashes/{file}', [LogsController::class, 'crash'])->where('file', Logs::CRASH_ROUTE);
            // Route cap is only a backstop. Logs::share enforces 5 shares per 10 minutes per user
            // and returns the translated share_limited message with Retry-After.
            Route::post('/share', [LogsController::class, 'share'])->middleware('throttle:20,10,techtyl-tools-share-edge');
        });

        // Whitelist, ops, bans. Console and file edits are separate because each has its own permission.
        Route::prefix('/players')->middleware(ToolAvailable::class . ':players')->group(function () {
            Route::get('/', [PlayersController::class, 'show']);
            Route::post('/command', [PlayersController::class, 'command'])->middleware('throttle:30,1,techtyl-tools-players');
            Route::post('/files', [PlayersController::class, 'files'])->middleware('throttle:30,1,techtyl-tools-players');
        });

        // Worlds. Backup is one Wings compress call, not a background task.
        Route::prefix('/worlds')->middleware(ToolAvailable::class . ':worlds')->group(function () {
            Route::get('/', [WorldsController::class, 'show']);
            Route::post('/backup', [WorldsController::class, 'backup'])->middleware('throttle:6,1,techtyl-tools-worlds');
            Route::post('/delete', [WorldsController::class, 'remove'])->middleware('throttle:6,1,techtyl-tools-worlds');
        });
    });
