<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Http;

use Closure;
use Illuminate\Http\Request;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Laesst Anfragen nur durch, wenn das genannte Modul im Admin-Bereich eingeschaltet ist. */
class ModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module)
    {
        if (!Modules::enabled($module)) {
            throw new NotFoundHttpException();
        }

        return $next($request);
    }
}
