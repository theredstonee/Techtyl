<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ServerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Servers
    Route::get('/servers', [ServerController::class, 'index']);
    Route::post('/servers', [ServerController::class, 'store']);
    Route::get('/servers/{server}', [ServerController::class, 'show']);
    Route::patch('/servers/{server}', [ServerController::class, 'update']);
    Route::delete('/servers/{server}', [ServerController::class, 'destroy']);

    // Server controls
    Route::post('/servers/{server}/control', [ServerController::class, 'control']);
    Route::get('/servers/{server}/resources', [ServerController::class, 'resources']);

    // AI Assistant routes
    Route::post('/ai/suggestions', [ServerController::class, 'getAISuggestions']);
    Route::post('/ai/help', [ServerController::class, 'getAIHelp']);
    Route::post('/ai/name-suggestions', [ServerController::class, 'getNameSuggestions']);
    Route::post('/servers/{server}/ai/troubleshoot', [ServerController::class, 'getTroubleshootHelp']);
});
