<?php

// Tägliche Abbuchung der Guthaben-Server (php artisan techtyl:billing-daily).
// Wird von Blueprint innerhalb eines Artisan-Befehls eingebunden. Mehrfaches Ausführen am selben
// Tag bucht nichts doppelt.
$techtylBillingResult = \Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Billing\Jobs::daily();
echo 'techtyl billing daily: ' . json_encode($techtylBillingResult) . PHP_EOL;
