<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Billing\ClientController;

/*
| Client-API der Abrechnung. Präfix: /api/client/extensions/techtyl/billing
| Anmeldung und das allgemeine Limit der Client-API setzt Blueprint; alles, was Stripe aufruft
| oder Kosten auslöst, hat zusätzlich ein engeres Limit.
*/
Route::get('/overview', [ClientController::class, 'overview']);

Route::middleware('throttle:10,1,techtyl-billing-pay')->group(function () {
    Route::post('/topup', [ClientController::class, 'topup']);
    Route::post('/order', [ClientController::class, 'order']);
    Route::post('/portal', [ClientController::class, 'portal']);
    Route::post('/subscriptions/{subscription}/cancel', [ClientController::class, 'cancel'])->whereNumber('subscription');
    Route::post('/subscriptions/{subscription}/resume', [ClientController::class, 'resume'])->whereNumber('subscription');
});
