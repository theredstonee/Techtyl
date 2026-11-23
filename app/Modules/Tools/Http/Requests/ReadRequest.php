<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests;

use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

/**
 * Lesen: Inhalte von plugins/ bzw. mods/ ansehen, Versionen auflisten.
 * ClientApiRequest prueft das Recht gegen den Server der Route (Besitzer und Root-Admins
 * haben alle Rechte, Subuser nur die ihnen zugewiesenen).
 */
class ReadRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return 'file.read';
    }
}
