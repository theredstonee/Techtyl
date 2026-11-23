<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests;

use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

/**
 * Installieren und Aktualisieren von Plugins und Mods.
 * ClientApiRequest prueft das Recht gegen den Server der Route (Besitzer und Root-Admins
 * haben alle Rechte, Subuser nur die ihnen zugewiesenen).
 */
class InstallRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return 'file.create';
    }
}
