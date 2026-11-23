<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Overview\CardsController;

/*
| Card layout of the admin overview (/admin). Included from routes/web.php, so the paths are
| /extensions/techtyl/overview/... with session and CSRF from the web router.
| Same protection as the panel's admin area: session, two-factor requirement, root admins only
| (everyone else gets 403 from AdminAuthenticate), plus a request limit.
*/
Route::prefix('/overview')
    ->middleware([
        'auth.session',
        \Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication::class,
        \Pterodactyl\Http\Middleware\AdminAuthenticate::class,
        'throttle:30,1,techtyl-overview',
    ])
    ->group(function () {
        Route::post('/cards', [CardsController::class, 'update'])->name('techtyl.overview.cards');
        Route::post('/cards/custom', [CardsController::class, 'store'])->name('techtyl.overview.cards.custom');
    });
