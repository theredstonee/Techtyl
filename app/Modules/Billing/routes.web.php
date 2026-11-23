<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Billing\WebhookController;

/*
| Web-Routen der Abrechnung. Präfix: /extensions/techtyl/billing
|
| Der Stripe-Webhook läuft ohne die Web-Middleware-Gruppe von Blueprint (keine Sitzung, keine
| Cookies, kein CSRF) – berechtigt ist eine Anfrage allein durch die Stripe-Signatur.
*/
Route::post('/webhook', [WebhookController::class, 'handle'])
    ->withoutMiddleware('blueprint')
    ->middleware('throttle:600,1,techtyl-billing-webhook');
