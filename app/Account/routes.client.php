<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Account\AccountController;

/*
| Kontobereich. Praefix: /api/client/extensions/techtyl/account
| Anmeldung (Sitzung oder Client-API-Schluessel) und das allgemeine Limit der Client-API liegen
| bereits davor. Alles bezieht sich auf den angemeldeten Nutzer, es gibt keine Nutzer-Parameter.
*/
Route::get('/me', [AccountController::class, 'me']);
Route::patch('/profile', [AccountController::class, 'updateProfile'])->middleware('throttle:10,1,techtyl-account-profile');
Route::patch('/language', [AccountController::class, 'updateLanguage'])->middleware('throttle:20,1,techtyl-account-language');

Route::post('/avatar', [AccountController::class, 'uploadAvatar'])->middleware('throttle:10,10,techtyl-account-avatar');
Route::delete('/avatar', [AccountController::class, 'deleteAvatar'])->middleware('throttle:20,10,techtyl-account-avatar-delete');

Route::get('/avatars', [AccountController::class, 'avatars']);
Route::get('/avatars/{token}', [AccountController::class, 'image'])->where('token', '[a-f0-9]{40}');

Route::get('/security', [AccountController::class, 'security']);
Route::post('/sessions/logout-others', [AccountController::class, 'logoutOthers'])->middleware('throttle:5,1,techtyl-account-sessions');
