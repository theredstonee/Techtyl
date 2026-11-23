<?php

// Wird von Blueprint innerhalb eines Artisan-Befehls eingebunden (techtyl:tickets-autoclose);
// $this ist der Befehl. Die eigentliche Arbeit steckt in Modules/Tickets/AutoClose.php.

use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;

$result = \Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tickets\AutoClose::run();

if (isset($this) && $this instanceof \Illuminate\Console\Command) {
    $this->line($result['skipped'] !== null
        ? I18n::t('core.tickets_skipped', ['reason' => $result['skipped']])
        : I18n::t('core.tickets_closed', ['closed' => $result['closed'], 'pruned' => $result['pruned']]));
}
