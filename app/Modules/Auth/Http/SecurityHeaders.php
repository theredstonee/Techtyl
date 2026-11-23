<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Http;

use Closure;
use Illuminate\Http\Request;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Pages;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Config;

/**
 * Strenge Antwort-Header fuer die eigenen Seiten des Moduls. Die Seiten kommen ohne Inline-Skripte
 * und ohne Inline-Stile aus; Cloudflare wird nur auf Seiten freigegeben, die das Turnstile-Widget
 * wirklich zeigen.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        $script = "'self'";
        $frame = "'none'";
        if ($request->attributes->get(Pages::TURNSTILE_ATTRIBUTE) === true && Config::turnstileOn()) {
            $script .= ' https://challenges.cloudflare.com';
            $frame = 'https://challenges.cloudflare.com';
        }

        $csp = implode('; ', [
            "default-src 'none'",
            'script-src ' . $script,
            "style-src 'self'",
            "font-src 'self'",
            "img-src 'self' data: https:",
            "connect-src 'self'",
            'frame-src ' . $frame,
            "form-action 'self'",
            "base-uri 'none'",
            "frame-ancestors 'none'",
            "object-src 'none'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
