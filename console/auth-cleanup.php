<?php

// Geplanter Job "techtyl:auth-cleanup": loescht abgelaufene, nie bestaetigte Registrierungen.
// Wird von Blueprint innerhalb eines Artisan-Kommandos eingebunden.

use Illuminate\Support\Facades\Schema;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Registrations;

if (!Schema::hasTable(Registrations::TABLE)) {
    echo "techtyl auth: Tabellen fehlen, nichts zu tun\n";

    return;
}

$deleted = Registrations::cleanup();
echo "techtyl auth: {$deleted} abgelaufene Registrierungen entfernt\n";
