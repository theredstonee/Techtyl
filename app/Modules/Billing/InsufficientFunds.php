<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Billing;

/** Eine Abbuchung hätte das Guthaben unter null gebracht und wurde deshalb nicht gebucht. */
class InsufficientFunds extends \RuntimeException
{
}
