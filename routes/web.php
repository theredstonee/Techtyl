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

// Designer fuer Root-Admins (alle anderen: 404). Sitzung und CSRF kommen aus dem Web-Router;
// Anmeldung pruefen AuthenticateSession und RootAdminOnly, die 2FA-Pflicht wie im Admin-Bereich.
// Die Seite liegt unter /designer/editor: /designer selbst ist der Ordner mit Skript und Stil
// (public/designer), den nginx direkt ausliefert; dessen index.html leitet hierher weiter.
Route::prefix('/designer')
    ->middleware([
        'auth.session',
        \Pterodactyl\BlueprintFramework\Extensions\{identifier}\Designer\RootAdminOnly::class,
        \Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication::class,
        'throttle:120,1,techtyl-designer',
    ])
    ->group(function () {
        $designer = \Pterodactyl\BlueprintFramework\Extensions\{identifier}\Designer\DesignerController::class;
        Route::get('/editor', [$designer, 'page'])->name('techtyl.designer');
        Route::put('/editor', [$designer, 'save'])->name('techtyl.designer.save');
        Route::post('/upload', [$designer, 'upload'])->name('techtyl.designer.upload')->middleware('throttle:12,1,techtyl-upload');
        Route::get('/preview/{page}', [$designer, 'preview'])->name('techtyl.designer.preview')->where('page', 'dashboard|console|login|admin');
    });

// Admin overview: customise cards (root admins only, middleware is set in that file).
Route::group([], base_path('app/BlueprintFramework/Extensions/{identifier}/Overview/routes.web.php'));

// Routen der Module (app/Modules/<Ordner>/routes.web.php), jeweils unter /<schluessel>/...
\Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules::routes('web');
