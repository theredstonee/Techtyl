<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Designer;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Laesst nur angemeldete Root-Admins durch. Alle anderen (auch Gaeste) bekommen 404, damit die
 * Seite nach aussen nicht verraet, dass es sie gibt.
 */
class RootAdminOnly
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (!auth()->user()?->root_admin) {
            throw new NotFoundHttpException();
        }

        return $next($request);
    }
}
