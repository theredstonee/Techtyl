<?php

// Wird von Blueprint innerhalb eines Artisan-Befehls eingebunden (techtyl:tickets-autoclose);
// $this ist der Befehl. Die eigentliche Arbeit steckt in Modules/Tickets/AutoClose.php.

$result = \Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tickets\AutoClose::run();

if (isset($this) && $this instanceof \Illuminate\Console\Command) {
    $this->line($result['skipped'] !== null
        ? 'Tickets: uebersprungen (' . $result['skipped'] . ').'
        : 'Tickets: ' . $result['closed'] . ' automatisch geschlossen, ' . $result['pruned'] . ' verwaiste Anhaenge entfernt.');
}
