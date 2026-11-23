<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

/**
 * Fuer Endpunkte, die jeder sehen darf, der Zugriff auf den Server hat (GET /meta, Vorgaenge).
 * Den Zugriff selbst prueft die Middleware AuthenticateServerAccess.
 */
class AccessRequest extends ClientApiRequest
{
}
