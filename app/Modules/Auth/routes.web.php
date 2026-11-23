<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Config;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Http\OAuthController;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Http\TurnstileLogin;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Http\SecurityHeaders;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Http\RegisterController;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Http\PublicController;

/*
| Web-Routen des Moduls "Registrierung und Anmeldung". Praefix: /extensions/techtyl/auth
| Sitzung und CSRF kommen aus der Blueprint-Gruppe; Anmeldung, Gast-Pruefung und Limits setzt
| jede Route selbst. Limits zaehlen je IP-Adresse (hinter einem Proxy TRUSTED_PROXIES setzen).
|
| Diese Datei wird bei jeder Anfrage geladen (auch bei abgeschaltetem Modul). Das nutzt die
| folgende Zeile: sie biegt den Middleware-Alias "recaptcha" des Panels um, der an
| POST /auth/login und POST /auth/password haengt. Die Klasse prueft Turnstile nur, wenn Modul
| und Turnstile-Login-Schutz eingeschaltet sind, und reicht sonst an die reCAPTCHA-Pruefung
| des Panels weiter. Mit "php artisan route:cache" wird diese Datei nicht geladen; der
| Selbsttest im Admin-Reiter zeigt dann "nein".
*/
Route::aliasMiddleware('recaptcha', TurnstileLogin::class);

// Auf den Anmeldeseiten das reCAPTCHA des Panels fuer diese Anfrage ausblenden, wenn Turnstile
// den Login schuetzt (sonst wuerde die Oberflaeche beide Pruefungen anzeigen).
if (!app()->runningInConsole() && request()->is('auth', 'auth/*') && Config::turnstileProtectsLogin()) {
    config(['recaptcha.enabled' => false]);
}

Route::middleware(SecurityHeaders::class)->group(function () {
    // Oeffentliche Angaben fuer die Anmeldeseite (keine Geheimnisse).
    Route::get('/config', [PublicController::class, 'config'])->middleware('throttle:120,1,techtyl-auth-config');
    Route::get('/theme.css', [PublicController::class, 'theme'])->middleware('throttle:240,1,techtyl-auth-theme');
    Route::get('/hinweis/{code}', [PublicController::class, 'notice'])
        ->where('code', '[a-z-]{3,24}')
        ->middleware('throttle:60,1,techtyl-auth-notice');

    Route::middleware('guest')->group(function () {
        Route::get('/register', [RegisterController::class, 'showForm'])->middleware('throttle:60,1,techtyl-auth-form');
        Route::post('/register', [RegisterController::class, 'submit'])
            ->middleware(['throttle:5,1,techtyl-auth-register', 'throttle:20,60,techtyl-auth-register-hour']);
        Route::get('/sent', [RegisterController::class, 'sent'])->middleware('throttle:60,1,techtyl-auth-form');
        Route::post('/resend', [RegisterController::class, 'resend'])
            ->middleware(['throttle:3,1,techtyl-auth-resend', 'throttle:10,60,techtyl-auth-resend-hour']);

        Route::get('/verify/{token}', [RegisterController::class, 'showVerify'])
            ->where('token', '[a-f0-9]{64}')
            ->middleware('throttle:30,1,techtyl-auth-verify');
        Route::post('/verify', [RegisterController::class, 'verify'])->middleware('throttle:10,1,techtyl-auth-verify-post');

        Route::get('/oauth/{provider}/redirect', [OAuthController::class, 'redirect'])
            ->where('provider', 'discord|google')
            ->middleware('throttle:20,1,techtyl-auth-oauth');
        Route::get('/oauth/complete', [OAuthController::class, 'showComplete'])->middleware('throttle:60,1,techtyl-auth-form');
        Route::post('/oauth/complete', [OAuthController::class, 'complete'])->middleware('throttle:10,1,techtyl-auth-verify-post');
        Route::get('/two-factor', [OAuthController::class, 'twoFactor'])->middleware('throttle:30,1,techtyl-auth-form');
    });

    // Rueckkehr vom Anbieter: als Gast (Anmeldung) oder angemeldet (Verknuepfung).
    Route::get('/oauth/{provider}/callback', [OAuthController::class, 'callback'])
        ->where('provider', 'discord|google')
        ->middleware('throttle:20,1,techtyl-auth-oauth');

    Route::middleware('auth')->group(function () {
        Route::get('/links', [OAuthController::class, 'links'])->middleware('throttle:60,1,techtyl-auth-links');
        Route::post('/links/{provider}', [OAuthController::class, 'linkStart'])
            ->where('provider', 'discord|google')
            ->middleware('throttle:6,1,techtyl-auth-link');
        Route::delete('/links/{provider}', [OAuthController::class, 'unlink'])
            ->where('provider', 'discord|google')
            ->middleware('throttle:6,1,techtyl-auth-link');
    });
});
