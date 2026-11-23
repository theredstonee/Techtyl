<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Pterodactyl\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Login;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Pages;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Config;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Invites;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Turnstile;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Registrations;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\RegistrationFailed;

/**
 * Registrierung mit E-Mail-Bestaetigung. Antworten verraten nie, ob zu einer Adresse schon ein
 * Konto besteht: nach dem Absenden folgt immer dieselbe Seite.
 */
class RegisterController extends Controller
{
    public const SESSION_EMAIL = 'techtyl_auth_pending_email';

    /** Mindestdauer der Antwort auf "Registrieren" und "Erneut senden". */
    private const FLOOR_MICROSECONDS = 350000;

    public const USERNAME_RULES = ['required', 'string', 'min:3', 'max:32', 'regex:/^[a-z0-9]([\w\.-]+)[a-z0-9]$/'];

    /** @return array<string, string> Validation messages in the current language. */
    public static function messages(): array
    {
        return [
            'email.required' => Pages::t('v_email_required'),
            'email.email' => Pages::t('v_email_invalid'),
            'email.max' => Pages::t('v_email_long'),
            'username.required' => Pages::t('v_username_required'),
            'username.min' => Pages::t('v_username_min'),
            'username.max' => Pages::t('v_username_max'),
            'username.regex' => Pages::t('v_username_format'),
            'name.max' => Pages::t('v_name_max'),
            'name.regex' => Pages::t('v_name_format'),
            'invite.required' => Pages::t('v_invite_required'),
            'invite.max' => Pages::t('invite_invalid'),
            'password.required' => Pages::t('v_password_required'),
            'password.min' => Pages::t('v_password_min', ['min' => (int) Config::get('min_password')]),
            'password.confirmed' => Pages::t('v_password_confirmed'),
        ];
    }

    public function showForm(): Response
    {
        if (!$this->open()) {
            return redirect(Pages::BASE . '/hinweis/geschlossen');
        }

        return Pages::view('register', ['inviteRequired' => Config::get('invite_required') === true], 200, true);
    }

    public function submit(Request $request): Response
    {
        $started = microtime(true);
        if (!$this->open()) {
            return redirect(Pages::BASE . '/hinweis/geschlossen');
        }

        $input = [
            'email' => is_string($request->input('email')) ? strtolower(trim($request->input('email'))) : null,
            'username' => is_string($request->input('username')) ? strtolower(trim($request->input('username'))) : null,
            'name' => is_string($request->input('name')) ? trim($request->input('name')) : null,
            'invite' => is_string($request->input('invite')) ? trim($request->input('invite')) : null,
        ];
        $back = fn (array $errors) => redirect(Pages::BASE . '/register')->withErrors($errors)->withInput($input);

        // Zuerst die Sicherheitspruefung, davor wird nichts nachgeschlagen.
        if (Config::turnstileOn() && !Turnstile::verify($request)) {
            return $back(['turnstile' => Pages::t('turnstile_failed')]);
        }

        $inviteRequired = Config::get('invite_required') === true;
        $validator = Validator::make($input, [
            'email' => ['required', 'string', 'max:191', 'email:strict'],
            'username' => self::USERNAME_RULES,
            'name' => ['nullable', 'string', 'max:120', 'regex:/^[^\x00-\x1F\x7F<>]*$/u'],
            'invite' => [$inviteRequired ? 'required' : 'nullable', 'string', 'max:40'],
        ], self::messages());
        if ($validator->fails()) {
            return $back($validator->errors()->toArray());
        }

        if (!Config::domainAllowed($input['email'])) {
            return $back(['email' => Pages::t('domain_blocked')]);
        }

        $inviteId = null;
        if ($inviteRequired) {
            $inviteId = Invites::findUsable((string) $input['invite']);
            if ($inviteId === null) {
                return $back(['invite' => Pages::t('invite_invalid')]);
            }
        }

        [$first, $last] = Registrations::splitName($input['name'], $input['username']);
        try {
            Registrations::start([
                'email' => $input['email'],
                'username' => $input['username'],
                'name_first' => $first,
                'name_last' => $last,
            ], $inviteId);
        } catch (\Throwable $e) {
            // Auch dann dieselbe Antwort; der Betreiber sieht den Grund im Log.
            Log::error('techtyl auth: Registrierung konnte nicht gespeichert werden', ['error' => $e->getMessage()]);
        }

        $request->session()->put(self::SESSION_EMAIL, $input['email']);
        self::settle($started);

        return redirect(Pages::BASE . '/sent');
    }

    public function sent(Request $request): Response
    {
        $email = $request->session()->get(self::SESSION_EMAIL);
        if (!is_string($email)) {
            return redirect(Pages::BASE . '/register');
        }

        return Pages::view('sent', [
            'email' => $email,
            'minutes' => Registrations::LIFETIME_MINUTES,
            'resent' => $request->session()->get('techtyl_auth_resent') === true,
        ], 200, true);
    }

    public function resend(Request $request): Response
    {
        $started = microtime(true);
        $email = $request->session()->get(self::SESSION_EMAIL);
        if (!is_string($email) || !$this->open()) {
            return redirect(Pages::BASE . '/register');
        }
        if (Config::turnstileOn() && !Turnstile::verify($request)) {
            return redirect(Pages::BASE . '/sent')->withErrors(['turnstile' => Pages::t('turnstile_failed')]);
        }

        try {
            Registrations::resend($email);
        } catch (\Throwable $e) {
            Log::error('techtyl auth: erneutes Senden fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        self::settle($started);

        return redirect(Pages::BASE . '/sent')->with('techtyl_auth_resent', true);
    }

    public function showVerify(string $token): Response
    {
        if (!$this->open()) {
            return redirect(Pages::BASE . '/hinweis/geschlossen');
        }
        $pending = Registrations::find($token);
        if ($pending === null) {
            return redirect(Pages::BASE . '/hinweis/link');
        }

        return Pages::view('password', [
            'action' => Pages::BASE . '/verify',
            'token' => $token,
            'email' => $pending->email,
            'username' => $pending->username,
            'minPassword' => (int) Config::get('min_password'),
            'inviteField' => false,
            'needsTurnstile' => false,
            'provider' => null,
        ]);
    }

    public function verify(Request $request): Response
    {
        if (!$this->open()) {
            return redirect(Pages::BASE . '/hinweis/geschlossen');
        }
        $token = $request->input('token');
        $pending = Registrations::find($token);
        if ($pending === null) {
            return redirect(Pages::BASE . '/hinweis/link');
        }

        $username = is_string($request->input('username')) ? strtolower(trim($request->input('username'))) : null;
        $back = fn (array $errors) => redirect(Pages::BASE . '/verify/' . $token)->withErrors($errors)->withInput(['username' => $username]);

        $errors = self::credentialErrors($request, $username, $pending->email);
        if ($errors !== []) {
            return $back($errors);
        }

        try {
            $user = Registrations::complete($pending, $username, (string) $request->input('password'));
        } catch (RegistrationFailed $e) {
            return $e->reason === 'username'
                ? $back(['username' => Pages::t('username_taken')])
                : redirect(Pages::BASE . '/hinweis/' . self::noticeFor($e->reason));
        } catch (\Throwable $e) {
            Log::error('techtyl auth: Konto konnte nicht angelegt werden', ['error' => $e->getMessage()]);

            return redirect(Pages::BASE . '/hinweis/fehler');
        }

        $request->session()->forget(self::SESSION_EMAIL);
        Login::finish($request, $user);

        return redirect('/');
    }

    /**
     * Prueft Benutzername und Passwort der Abschluss-Seiten.
     *
     * @return array<string, mixed> Fehler je Feld, leer wenn alles passt
     */
    public static function credentialErrors(Request $request, ?string $username, string $email): array
    {
        $min = (int) Config::get('min_password');
        $validator = Validator::make([
            'username' => $username,
            'password' => $request->input('password'),
            'password_confirmation' => $request->input('password_confirmation'),
        ], [
            'username' => self::USERNAME_RULES,
            'password' => ['required', 'string', 'min:' . $min, 'confirmed'],
        ], self::messages());
        if ($validator->fails()) {
            return $validator->errors()->toArray();
        }

        $password = (string) $request->input('password');
        // bcrypt wertet nur die ersten 72 Bytes aus; laengere Passwoerter waeren truegerisch.
        if (strlen($password) > 72) {
            return ['password' => Pages::t('password_too_long')];
        }
        $lower = mb_strtolower($password);
        if ($lower === $username || $lower === mb_strtolower($email)) {
            return ['password' => Pages::t('password_like_name')];
        }

        return [];
    }

    public static function noticeFor(string $reason): string
    {
        return match ($reason) {
            'closed' => 'geschlossen',
            'domain' => 'domain',
            'invite' => 'einladung',
            'link' => 'link',
            default => 'fehler',
        };
    }

    /**
     * Haelt die Antwortzeit auf einer festen Untergrenze. Ob eine Mail eingereiht wurde oder die
     * Adresse schon ein Konto hat, laesst sich so nicht an der Dauer ablesen (solange die Mails,
     * wie beim Panel ueblich, ueber die Warteschlange und nicht waehrend der Anfrage verschickt werden).
     */
    private static function settle(float $started): void
    {
        $remaining = self::FLOOR_MICROSECONDS - (int) ((microtime(true) - $started) * 1000000);
        if ($remaining > 0) {
            usleep($remaining);
        }
    }

    private function open(): bool
    {
        return Config::get('registration') === true;
    }
}
