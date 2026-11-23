<?php

// Sperr-/Löschlauf der Abrechnung (php artisan techtyl:billing-enforce).
// Sperrt nach Zahlungsausfall, entsperrt nach Zahlung und löscht Server nach der Lösch-Frist.
// Nur dieser Lauf löscht Server, und nur solche, die das Modul selbst angelegt hat.
$techtylBillingResult = \Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Billing\Jobs::enforce();
echo 'techtyl billing enforce: ' . json_encode($techtylBillingResult) . PHP_EOL;
