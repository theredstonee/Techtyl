<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth;

use Illuminate\Support\Str;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Pterodactyl\Facades\Activity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Pterodactyl\Events\Auth\DirectLogin;

/** Schliesst Anmeldungen genauso ab wie das Panel selbst. */
class Login
{
    /**
     * Entspricht AbstractLoginController::sendLoginResponse: Sitzung erneuern, anmelden, Event DirectLogin.
     * Nur fuer Nutzer OHNE Zwei-Faktor-Anmeldung aufrufen.
     */
    public static function finish(Request $request, User $user): void
    {
        if ($user->use_totp) {
            throw new \LogicException('techtyl auth: Nutzer mit 2FA duerfen nicht direkt angemeldet werden');
        }

        $request->session()->remove('auth_confirmation_token');
        $request->session()->regenerate();

        Auth::guard()->login($user, true);

        Event::dispatch(new DirectLogin($user, true));
    }

    /**
     * Entspricht dem zweiten Teil von LoginController::login: legt das Bestaetigungs-Token in die
     * Sitzung. Den Code prueft anschliessend das Panel selbst unter POST /auth/login/checkpoint.
     */
    public static function checkpoint(Request $request, User $user): void
    {
        Activity::event('auth:checkpoint')->withRequestMetadata()->subject($user)->log();

        $request->session()->put('auth_confirmation_token', [
            'user_id' => $user->id,
            'token_value' => Str::random(64),
            'expires_at' => CarbonImmutable::now()->addMinutes(5),
        ]);
    }

    /** Token fuer die 2FA-Seite, falls in der Sitzung ein gueltiger Checkpoint liegt. */
    public static function pendingCheckpoint(Request $request): ?string
    {
        $details = $request->session()->get('auth_confirmation_token');
        if (!is_array($details) || !is_string($details['token_value'] ?? null)) {
            return null;
        }
        $expires = $details['expires_at'] ?? null;
        if (!$expires instanceof \Carbon\CarbonInterface || $expires->isBefore(CarbonImmutable::now())) {
            return null;
        }

        return $details['token_value'];
    }
}
