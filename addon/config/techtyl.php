<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Techtyl AI Addon Configuration
    |--------------------------------------------------------------------------
    */

    // AI-Features aktivieren/deaktivieren
    'enabled' => env('TECHTYL_AI_ENABLED', true),

    // AI-Vorschläge in UI anzeigen
    'show_suggestions' => env('TECHTYL_SHOW_SUGGESTIONS', true),

    // Maximale AI-Anfragen pro Benutzer pro Tag
    'max_requests_per_user' => env('TECHTYL_MAX_REQUESTS', 50),

    // AI-Antworten cachen (24 Stunden)
    'cache_responses' => env('TECHTYL_CACHE_RESPONSES', true),

    // Cache-Dauer in Minuten
    'cache_duration' => env('TECHTYL_CACHE_DURATION', 1440), // 24h

    // Logging
    'log_requests' => env('TECHTYL_LOG_REQUESTS', true),

];
