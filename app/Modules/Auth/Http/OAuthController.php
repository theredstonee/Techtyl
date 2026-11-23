<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Http;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Login;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Pages;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Config;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Invites;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Turnstile;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Registrations;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\OAuth\Providers;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\RegistrationFailed;

/**
 * Anmeldung und Verknuepfung ueber Discord und Google.
 *
 * Regeln: Angemeldet wird ausschliesslich ueber eine gespeicherte Verknuepfung (Anbieter +
 * Anbieter-ID). Eine gleiche E-Mail-Adresse fuehrt nie zu einer Anmeldung oder Zusammenfuehrung.
 * Verknuepfen und Entknuepfen geht nur angemeldet und mit Passwort. Nutzer mit 2FA durchlaufen
 * den Checkpoint des Panels.
 */
class OAuthController extends Controller
{
    public const TABLE = 'techtyl_auth_identities';

    private const SESSION_FLOW = 'techtyl_auth_oauth';
    private const SESSION_SIGNUP = 'techtyl_auth_signup';
    private const FLOW_SECONDS = 600;
    private const SIGNUP_SECONDS = 900;

    /** Start der Anmeldung (nur fuer Gaeste). */
    public function redirect(Request $request, string $provider): Response
    {
        if (!Config::providerOn($provider)) {
            return redirect(Pages::BASE . '/hinweis/anbieter-aus');
        }

        return redirect()->away($this->begin($request, $provider, 'login', null));
    }

    /** Rueckkehr vom Anbieter, fuer Anmeldung und Verknuepfung. */
    public function callback(Request $request, string $provider): Response
    {
        // Der Ablauf ist einmalig: was auch passiert, der gespeicherte state ist danach verbraucht.
        $flow = $request->session()->pull(self::SESSION_FLOW);
        $state = $request->query('state');

        $valid = is_array($flow)
            && ($flow['provider'] ?? null) === $provider
            && (int) ($flow['expires'] ?? 0) >= time()
            && is_string($state) && $state !== ''
            && hash_equals((string) ($flow['state'] ?? ''), $state);
        if (!$valid) {
            return $this->fail($flow, 'anbieter-abbruch');
        }
        if (!Config::providerOn($provider)) {
            return $this->fail($flow, 'anbieter-aus');
        }

        $code = $request->query('code');
        if ($request->query('error') !== null || !is_string($code) || $code === '' || strlen($code) > 2048) {
            return $this->fail($flow, 'anbieter-abbruch');
        }

        $identity = Providers::identity($provider, $code, (string) $flow['verifier'], (string) $flow['nonce']);
        if ($identity === null) {
            return $this->fail($flow, 'anbieter-fehler');
        }

        return $flow['intent'] === 'link'
            ? $this->finishLink($request, $provider, $flow, $identity)
            : $this->finishLogin($request, $provider, $identity);
    }

    /** @param array<string, mixed> $identity */
    private function finishLogin(Request $request, string $provider, array $identity): Response
    {
        if ($request->user() !== null) {
            return redirect('/');
        }

        $row = DB::table(self::TABLE)->where('provider', $provider)->where('provider_user_id', $identity['id'])->first();
        if ($row !== null) {
            $user = User::query()->find($row->user_id);
            if ($user === null) {
                return redirect(Pages::BASE . '/hinweis/anbieter-unbekannt');
            }
            DB::table(self::TABLE)->where('id', $row->id)->update(['last_login_at' => now()]);

            if ($user->use_totp) {
                Login::checkpoint($request, $user);

                return redirect(Pages::BASE . '/two-factor');
            }
            Login::finish($request, $user);

            return redirect('/');
        }

        // Unbekannte Identitaet. Ein Konto mit derselben E-Mail wird NIE uebernommen oder verknuepft;
        // die Antwort ist dieselbe wie bei abgeschalteter Registrierung.
        $maySignUp = Config::get('registration') === true
            && Config::get($provider . '_register') === true
            && $identity['verified'] === true
            && $identity['email'] !== '';
        if (!$maySignUp || DB::table('users')->where('email', $identity['email'])->exists()) {
            return redirect(Pages::BASE . '/hinweis/anbieter-unbekannt');
        }
        if (!Config::domainAllowed($identity['email'])) {
            return redirect(Pages::BASE . '/hinweis/domain');
        }

        $request->session()->put(self::SESSION_SIGNUP, [
            'provider' => $provider,
            'id' => $identity['id'],
            'email' => $identity['email'],
            'name' => $identity['name'],
            'label' => $identity['label'],
            'expires' => time() + self::SIGNUP_SECONDS,
        ]);

        return redirect(Pages::BASE . '/oauth/complete');
    }

    /**
     * @param array<string, mixed> $flow
     * @param array<string, mixed> $identity
     */
    private function finishLink(Request $request, string $provider, array $flow, array $identity): Response
    {
        $user = $request->user();
        if ($user === null || (int) $user->id !== (int) ($flow['user_id'] ?? 0)) {
            return redirect(Pages::BASE . '/hinweis/anbieter-abbruch');
        }

        $taken = DB::table(self::TABLE)->where('provider', $provider)->where('provider_user_id', $identity['id'])->first();
        if ($taken !== null) {
            return redirect('/account?techtyl-link=' . ((int) $taken->user_id === (int) $user->id ? 'ok' : 'vergeben'));
        }
        if (DB::table(self::TABLE)->where('user_id', $user->id)->where('provider', $provider)->exists()) {
            return redirect('/account?techtyl-link=doppelt');
        }

        try {
            DB::table(self::TABLE)->insert([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_user_id' => $identity['id'],
                'label' => $identity['label'] !== '' ? $identity['label'] : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('techtyl auth: Verknuepfung nicht gespeichert', ['error' => $e->getMessage()]);

            return redirect('/account?techtyl-link=fehler');
        }

        return redirect('/account?techtyl-link=ok');
    }

    /** Seite, auf der ein ueber den Anbieter neu registrierter Nutzer Benutzername und Passwort waehlt. */
    public function showComplete(Request $request): Response
    {
        $signup = $this->signup($request);
        if ($signup === null) {
            return redirect(Pages::BASE . '/hinweis/anbieter-abbruch');
        }

        return Pages::view('password', [
            'action' => Pages::BASE . '/oauth/complete',
            'token' => null,
            'email' => $signup['email'],
            'username' => self::suggestUsername($signup['name'], $signup['email']),
            'minPassword' => (int) Config::get('min_password'),
            'inviteField' => Config::get('invite_required') === true,
            'needsTurnstile' => Config::turnstileOn(),
            'provider' => Config::PROVIDERS[$signup['provider']],
        ], 200, true);
    }

    public function complete(Request $request): Response
    {
        $signup = $this->signup($request);
        if ($signup === null) {
            return redirect(Pages::BASE . '/hinweis/anbieter-abbruch');
        }

        $username = is_string($request->input('username')) ? strtolower(trim($request->input('username'))) : null;
        $back = fn (array $errors) => redirect(Pages::BASE . '/oauth/complete')->withErrors($errors)->withInput(['username' => $username]);

        if (Config::turnstileOn() && !Turnstile::verify($request)) {
            return $back(['turnstile' => Pages::t('turnstile_failed')]);
        }
        $errors = RegisterController::credentialErrors($request, $username, $signup['email']);
        if ($errors !== []) {
            return $back($errors);
        }

        $inviteId = null;
        if (Config::get('invite_required') === true) {
            $invite = $request->input('invite');
            $inviteId = is_string($invite) && strlen($invite) <= 40 ? Invites::findUsable($invite) : null;
            if ($inviteId === null) {
                return $back(['invite' => Pages::t('invite_invalid')]);
            }
        }

        try {
            $user = DB::transaction(function () use ($signup, $username, $inviteId, $request) {
                Registrations::guard($signup['email'], $username, $inviteId);
                if (Config::get($signup['provider'] . '_register') !== true) {
                    throw new RegistrationFailed('closed');
                }

                [$first, $last] = Registrations::splitName($signup['name'], $username);
                $user = Registrations::createUser([
                    'email' => $signup['email'],
                    'username' => $username,
                    'name_first' => $first,
                    'name_last' => $last,
                ], (string) $request->input('password'));

                // Schlaegt wegen des eindeutigen Schluessels fehl, falls die Identitaet inzwischen vergeben ist.
                DB::table(self::TABLE)->insert([
                    'user_id' => $user->id,
                    'provider' => $signup['provider'],
                    'provider_user_id' => $signup['id'],
                    'label' => $signup['label'] !== '' ? $signup['label'] : null,
                    'last_login_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $user;
            });
        } catch (RegistrationFailed $e) {
            if ($e->reason === 'username') {
                return $back(['username' => Pages::t('username_taken')]);
            }
            $request->session()->forget(self::SESSION_SIGNUP);

            return redirect(Pages::BASE . '/hinweis/' . ($e->reason === 'link' ? 'anbieter-unbekannt' : RegisterController::noticeFor($e->reason)));
        } catch (\Throwable $e) {
            Log::error('techtyl auth: Konto ueber Anbieter konnte nicht angelegt werden', ['error' => $e->getMessage()]);
            $request->session()->forget(self::SESSION_SIGNUP);

            return redirect(Pages::BASE . '/hinweis/fehler');
        }

        $request->session()->forget(self::SESSION_SIGNUP);
        Login::finish($request, $user);

        return redirect('/');
    }

    /** Kleine 2FA-Seite; ihr Formular schickt den Code an den Checkpoint des Panels. */
    public function twoFactor(Request $request): Response
    {
        $token = Login::pendingCheckpoint($request);
        if ($token === null) {
            return redirect('/auth/login');
        }

        return Pages::view('twofactor', ['token' => $token]);
    }

    /** Verknuepfungen des angemeldeten Nutzers (fuer die Konto-Seite). */
    public function links(Request $request): JsonResponse
    {
        $rows = DB::table(self::TABLE)->where('user_id', $request->user()->id)->get(['provider', 'label', 'created_at'])->keyBy('provider');

        $providers = [];
        foreach (Config::PROVIDERS as $key => $title) {
            $row = $rows->get($key);
            $enabled = Config::providerOn($key);
            if (!$enabled && $row === null) {
                continue;
            }
            $providers[] = [
                'key' => $key,
                'title' => $title,
                'enabled' => $enabled,
                'linked' => $row !== null,
                'label' => $row !== null ? $row->label : null,
                'linked_at' => $row !== null ? (string) $row->created_at : null,
            ];
        }

        return new JsonResponse(['providers' => $providers]);
    }

    /** Start der Verknuepfung: nur angemeldet und mit Passwort. Antwort: Adresse beim Anbieter. */
    public function linkStart(Request $request, string $provider): JsonResponse
    {
        if (!Config::providerOn($provider)) {
            return self::error(404, Pages::t('link_off'));
        }
        $user = $request->user();
        if (!self::passwordMatches($request, $user)) {
            return self::error(422, Pages::t('password_wrong'));
        }
        if (DB::table(self::TABLE)->where('user_id', $user->id)->where('provider', $provider)->exists()) {
            return self::error(409, Pages::t('already_linked'));
        }

        return new JsonResponse(['url' => $this->begin($request, $provider, 'link', (int) $user->id)]);
    }

    /** Verknuepfung loesen: nur angemeldet und mit Passwort. */
    public function unlink(Request $request, string $provider): JsonResponse
    {
        $user = $request->user();
        if (!self::passwordMatches($request, $user)) {
            return self::error(422, Pages::t('password_wrong'));
        }
        DB::table(self::TABLE)->where('user_id', $user->id)->where('provider', $provider)->delete();

        return new JsonResponse(['ok' => true]);
    }

    /** Legt state (und fuer Google PKCE-Verifier und nonce) in die Sitzung und liefert die Anbieter-Adresse. */
    private function begin(Request $request, string $provider, string $intent, ?int $userId): string
    {
        $flow = [
            'provider' => $provider,
            'intent' => $intent,
            'user_id' => $userId,
            'state' => Str::random(48),
            'verifier' => Str::random(64),
            'nonce' => Str::random(32),
            'expires' => time() + self::FLOW_SECONDS,
        ];
        $request->session()->put(self::SESSION_FLOW, $flow);

        return Providers::authorizeUrl($provider, $flow['state'], $flow['verifier'], $flow['nonce']);
    }

    /** @param mixed $flow Inhalt der Sitzung; bei einer Verknuepfung geht es zurueck zur Konto-Seite. */
    private function fail(mixed $flow, string $notice): Response
    {
        if (is_array($flow) && ($flow['intent'] ?? null) === 'link' && auth()->check()) {
            return redirect('/account?techtyl-link=fehler');
        }

        return redirect(Pages::BASE . '/hinweis/' . $notice);
    }

    /** @return array<string, mixed>|null */
    private function signup(Request $request): ?array
    {
        $signup = $request->session()->get(self::SESSION_SIGNUP);
        if (!is_array($signup) || (int) ($signup['expires'] ?? 0) < time() || !isset(Config::PROVIDERS[$signup['provider'] ?? ''])) {
            $request->session()->forget(self::SESSION_SIGNUP);

            return null;
        }

        return $signup;
    }

    private static function passwordMatches(Request $request, User $user): bool
    {
        $password = $request->input('password');

        return is_string($password) && $password !== '' && strlen($password) <= 1024
            && password_verify($password, (string) $user->password);
    }

    private static function suggestUsername(string $name, string $email): string
    {
        $base = strtolower($name !== '' ? $name : (string) strstr($email, '@', true));
        $base = trim(preg_replace('/[^a-z0-9._-]+/', '', $base) ?? '', '._-');

        $base = rtrim(substr($base, 0, 32), '._-');

        return strlen($base) >= 3 ? $base : '';
    }

    private static function error(int $status, string $detail): JsonResponse
    {
        return new JsonResponse(['errors' => [['code' => 'TechtylAuthError', 'status' => (string) $status, 'detail' => $detail]]], $status);
    }
}
