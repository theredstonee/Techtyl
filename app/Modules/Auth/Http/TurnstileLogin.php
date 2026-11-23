<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Http;

use Closure;
use Illuminate\Http\Request;
use Pterodactyl\Http\Middleware\VerifyReCaptcha;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Pages;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Config;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Turnstile;

/**
 * Ersetzt den Middleware-Alias "recaptcha" des Panels (POST /auth/login und POST /auth/password).
 * Ist Turnstile fuer den Login aktiv, wird das Turnstile-Token verbindlich geprueft; sonst laeuft
 * unveraendert die reCAPTCHA-Pruefung des Panels.
 */
class TurnstileLogin
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (!Config::turnstileProtectsLogin()) {
            return app(VerifyReCaptcha::class)->handle($request, $next);
        }

        if (!Turnstile::verify($request)) {
            throw new HttpException(400, Pages::t('login_check_failed'));
        }

        return $next($request);
    }
}
