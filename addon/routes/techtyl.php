<?php

/**
 * Techtyl AI Addon Routes
 * Füge diese Routes zu routes/api.php hinzu
 */

Route::group(['prefix' => '/techtyl', 'middleware' => 'auth:sanctum'], function () {
    Route::post('/ai/suggestions', [Pterodactyl\Http\Controllers\Techtyl\AIController::class, 'getSuggestions']);
    Route::post('/ai/help', [Pterodactyl\Http\Controllers\Techtyl\AIController::class, 'getHelp']);
    Route::post('/ai/names', [Pterodactyl\Http\Controllers\Techtyl\AIController::class, 'getNameSuggestions']);
    Route::post('/ai/troubleshoot', [Pterodactyl\Http\Controllers\Techtyl\AIController::class, 'troubleshoot']);
});
