<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant\Controller;

/*
| KI-Assistent. Praefix: /api/client/extensions/techtyl/assistant
| Anmeldung und das allgemeine Limit der Client-API setzt Blueprint, den Modul-Schalter die
| Gruppe darueber. Fragen haben zusaetzlich eigene Limits je Nutzer (pro Minute, pro Tag,
| eine Antwort gleichzeitig) im Controller, damit die Meldungen verstaendlich sind; das
| Throttle hier ist nur die aeussere Bremse.
*/
Route::get('/state', [Controller::class, 'state']);

Route::get('/conversations', [Controller::class, 'conversations']);
Route::delete('/conversations', [Controller::class, 'deleteAll']);
Route::get('/conversations/{conversation}', [Controller::class, 'conversation'])->where('conversation', '[0-9]{1,9}');
Route::delete('/conversations/{conversation}', [Controller::class, 'deleteConversation'])->where('conversation', '[0-9]{1,9}');

Route::post('/chat', [Controller::class, 'chat'])->middleware('throttle:90,1,techtyl-assistant');
