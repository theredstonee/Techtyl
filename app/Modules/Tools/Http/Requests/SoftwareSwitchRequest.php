<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http\Requests;

use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

/**
 * Starting a loader switch. The service checks settings.reinstall, the image right,
 * stop and backup again with can(); this request is the first gate.
 */
class SoftwareSwitchRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return 'startup.update';
    }
}
