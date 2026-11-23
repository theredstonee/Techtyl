<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tickets\TicketController;

/*
| Client-API der Tickets. Praefix: /api/client/extensions/techtyl/tickets
| Anmeldung, allgemeines Limit der Client-API und der Modul-Schalter liegen bereits davor.
| Eroeffnen und Antworten haben eigene, engere Limits je Nutzer.
*/
Route::get('/', [TicketController::class, 'index']);
Route::get('/meta', [TicketController::class, 'meta']);
Route::post('/', [TicketController::class, 'store'])->middleware('throttle:6,10,techtyl-tickets-open');

Route::get('/attachments/{attachment}', [TicketController::class, 'attachment'])->whereNumber('attachment');

Route::get('/{ticket}', [TicketController::class, 'show'])->whereNumber('ticket');
Route::post('/{ticket}/messages', [TicketController::class, 'reply'])->whereNumber('ticket')
    ->middleware('throttle:15,1,techtyl-tickets-reply');
Route::post('/{ticket}/close', [TicketController::class, 'close'])->whereNumber('ticket')
    ->middleware('throttle:20,1,techtyl-tickets-state');
Route::post('/{ticket}/reopen', [TicketController::class, 'reopen'])->whereNumber('ticket')
    ->middleware('throttle:20,1,techtyl-tickets-state');
