<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests;

use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

/**
 * Versionswechsel (aendert Server-Jar, Egg-Variablen und ggf. das Java-Image).
 * ClientApiRequest prueft das Recht gegen den Server der Route (Besitzer und Root-Admins
 * haben alle Rechte, Subuser nur die ihnen zugewiesenen).
 */
class VersionRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return 'startup.update';
    }
}
