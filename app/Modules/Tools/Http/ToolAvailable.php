<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\Http;

use Closure;
use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\ServerTypes;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools\ToolsException;

/**
 * Laesst eine Anfrage nur durch, wenn das Werkzeug (Schluessel aus ServerTypes::TOOLS) zum
 * Servertyp passt. Verwendung in den Routen: ->middleware(ToolAvailable::class . ':content').
 */
class ToolAvailable
{
    public function handle(Request $request, Closure $next, string $tool)
    {
        $server = $request->route()->parameter('server');
        if (!$server instanceof Server) {
            throw ToolsException::notFound();
        }
        $type = ServerTypes::forServer($server)['type'];
        if (!ServerTypes::toolAvailable($tool, $type)) {
            throw $type === ServerTypes::NONE
                ? ToolsException::conflict('not_minecraft')
                : ToolsException::conflict('tool_unavailable', ['type' => ServerTypes::label($type)]);
        }

        return $next($request);
    }
}
