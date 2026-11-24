<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests;

use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

/**
 * Reading the software section and its version lists. Switching is a separate request.
 */
class SoftwareReadRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return 'startup.read';
    }
}
