<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Billing;

/** Stripe hat abgelehnt oder war nicht erreichbar. Die Meldung ist nur für das Log gedacht. */
class StripeException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0, public readonly string $stripeCode = '')
    {
        parent::__construct($message);
    }
}
