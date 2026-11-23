<?php

// Geplanter Job "techtyl:auth-cleanup": loescht abgelaufene, nie bestaetigte Registrierungen.
// Wird von Blueprint innerhalb eines Artisan-Kommandos eingebunden.

use Illuminate\Support\Facades\Schema;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Registrations;

if (!Schema::hasTable(Registrations::TABLE)) {
    echo I18n::t('core.auth_tables_missing') . "\n";

    return;
}

$deleted = Registrations::cleanup();
echo I18n::t('core.auth_removed', ['count' => $deleted]) . "\n";
