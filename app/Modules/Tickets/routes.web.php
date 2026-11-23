<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tickets\TicketController;

/*
| Web-Route der Tickets. Praefix: /extensions/techtyl/tickets
| Anhaenge fuer Bild-Elemente im Dashboard und im Admin-Bereich: die Sitzung des Browsers weist
| den Nutzer aus, der Controller laesst nur den Besitzer des Tickets und Root-Admins durch
| (ohne Anmeldung oder fuer Fremde: 404).
*/
Route::get('/attachments/{attachment}', [TicketController::class, 'attachment'])
    ->whereNumber('attachment')
    ->middleware('throttle:240,1,techtyl-tickets-files');
