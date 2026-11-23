<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Http\TurnstileLogin;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Http\OAuthController;

/** Admin-Reiter "Registrierung und Anmeldung". Der Admin-Controller hat die Admin-Rechte bereits geprueft. */
class Admin
{
    private const FLASH_INVITE = 'techtyl_auth_new_invite';

    public static function data(): array
    {
        $ready = Schema::hasTable(Registrations::TABLE) && Schema::hasTable(Invites::TABLE) && Schema::hasTable(OAuthController::TABLE);
        $settings = Config::all();

        $providers = [];
        foreach (Config::PROVIDERS as $key => $title) {
            $providers[$key] = [
                'title' => $title,
                'enabled' => $settings[$key],
                'register' => $settings[$key . '_register'],
                'client_id' => $settings[$key . '_client_id'],
                'secret_set' => Config::hasSecret($key . '_secret'),
                'redirect_uri' => Config::redirectUri($key),
                'linked' => $ready ? DB::table(OAuthController::TABLE)->where('provider', $key)->count() : 0,
            ];
        }

        $invites = [];
        $pending = 0;
        if ($ready) {
            try {
                Registrations::cleanup();
            } catch (\Throwable $e) {
                Log::warning('techtyl auth: Aufraeumen fehlgeschlagen', ['error' => $e->getMessage()]);
            }
            $pending = DB::table(Registrations::TABLE)->count();

            $now = now();
            foreach (DB::table(Invites::TABLE)->orderByDesc('id')->limit(100)->get() as $row) {
                $state = 'active';
                if ($row->revoked_at !== null) {
                    $state = 'revoked';
                } elseif ($row->expires_at !== null && $now->greaterThan($row->expires_at)) {
                    $state = 'expired';
                } elseif ((int) $row->uses >= (int) $row->max_uses) {
                    $state = 'used';
                }
                $invites[] = [
                    'id' => (int) $row->id,
                    'hint' => $row->hint,
                    'label' => (string) $row->label,
                    'uses' => (int) $row->uses,
                    'max_uses' => (int) $row->max_uses,
                    'percent' => (int) round(100 * (int) $row->uses / max(1, (int) $row->max_uses)),
                    'expires_at' => $row->expires_at === null ? null : CarbonImmutable::parse($row->expires_at)->format(I18n::locale() === 'de' ? 'd.m.Y H:i' : 'Y-m-d H:i'),
                    'state' => $state,
                ];
            }
        }

        $appUrl = (string) config('app.url');
        $panelRounds = (int) config('hashing.bcrypt.rounds', 10);

        return [
            't' => static fn (string $key, array $replace = []): string => Pages::t($key, $replace),
            'ready' => $ready,
            'settings' => [
                'registration' => $settings['registration'],
                'min_password' => $settings['min_password'],
                'invite_required' => $settings['invite_required'],
                'domains' => implode("\n", $settings['domains']),
                'turnstile' => $settings['turnstile'],
                'turnstile_login' => $settings['turnstile_login'],
                'turnstile_site_key' => $settings['turnstile_site_key'],
                'turnstile_secret_set' => Config::hasSecret('turnstile_secret'),
            ],
            'minPasswordFloor' => Config::MIN_PASSWORD_FLOOR,
            'minPasswordCeiling' => Config::MIN_PASSWORD_CEILING,
            'providers' => $providers,
            'invites' => $invites,
            'newInvite' => session(self::FLASH_INVITE),
            'pending' => $pending,
            'selfTest' => self::loginProtection(),
            'testKey' => Turnstile::usesTestKey(),
            'expectedHost' => Turnstile::expectedHost(),
            'appUrl' => $appUrl,
            'appUrlSecure' => str_starts_with($appUrl, 'https://'),
            'panelRounds' => $panelRounds,
            'effectiveRounds' => max(Registrations::BCRYPT_MIN_ROUNDS, $panelRounds),
            'hashDriver' => (string) config('hashing.driver', 'bcrypt'),
            'mailer' => (string) config('mail.default'),
            'queue' => (string) config('queue.default'),
            'panelRecaptcha' => (bool) config('recaptcha.enabled'),
            'registerUrl' => rtrim($appUrl, '/') . Pages::BASE . '/register',
            'linkLifetime' => Registrations::LIFETIME_MINUTES,
            'mailsPerHour' => Registrations::MAILS_PER_HOUR,
        ];
    }

    public static function handle(Request $request, string $action): string
    {
        return match ($action) {
            'settings' => self::saveSettings($request),
            'turnstile' => self::saveTurnstile($request),
            'turnstile-check' => self::checkTurnstile(),
            'providers' => self::saveProviders($request),
            'invite-create' => self::createInvite($request),
            'invite-revoke' => self::revokeInvite($request),
            default => abort(404),
        };
    }

    private static function saveSettings(Request $request): string
    {
        $data = $request->validate([
            'registration' => 'nullable|boolean',
            'invite_required' => 'nullable|boolean',
            'min_password' => 'required|integer|min:' . Config::MIN_PASSWORD_FLOOR . '|max:' . Config::MIN_PASSWORD_CEILING,
            'domains' => 'nullable|string|max:8000',
        ]);

        Config::save([
            'registration' => (bool) ($data['registration'] ?? false),
            'invite_required' => (bool) ($data['invite_required'] ?? false),
            'min_password' => (int) $data['min_password'],
            'domains' => Config::parseDomains((string) ($data['domains'] ?? '')),
        ]);

        return Pages::t('adm_saved_registration');
    }

    private static function saveTurnstile(Request $request): string
    {
        $data = $request->validate([
            'turnstile' => 'nullable|boolean',
            'turnstile_login' => 'nullable|boolean',
            'turnstile_site_key' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'turnstile_secret' => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        $values = [
            'turnstile' => (bool) ($data['turnstile'] ?? false),
            'turnstile_login' => (bool) ($data['turnstile_login'] ?? false),
            'turnstile_site_key' => (string) ($data['turnstile_site_key'] ?? ''),
        ];
        if (!empty($data['turnstile_secret'])) {
            $values['turnstile_secret'] = encrypt($data['turnstile_secret']);
        }

        $secretSet = isset($values['turnstile_secret']) || Config::hasSecret('turnstile_secret');
        if ($values['turnstile'] && ($values['turnstile_site_key'] === '' || !$secretSet)) {
            // Ohne Schluessel wuerde jede Pruefung scheitern und niemand koennte sich mehr anmelden.
            throw ValidationException::withMessages(['turnstile' => Pages::t('adm_turnstile_needs_keys')]);
        }

        Config::save($values);

        return Pages::t('adm_saved_turnstile');
    }

    /** Fragt Cloudflare mit einem absichtlich ungueltigen Token: so zeigt sich, ob das Secret angenommen wird. */
    private static function checkTurnstile(): string
    {
        $secret = Config::secret('turnstile_secret');
        if ($secret === '') {
            throw ValidationException::withMessages(['turnstile' => Pages::t('adm_no_secret')]);
        }

        try {
            $result = Http::asForm()->timeout(6)->connectTimeout(4)->withoutRedirecting()
                ->post(Turnstile::ENDPOINT, ['secret' => $secret, 'response' => 'techtyl-selbsttest'])
                ->json();
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['turnstile' => Pages::t('adm_cf_unreachable')]);
        }

        $codes = is_array($result) ? (array) ($result['error-codes'] ?? []) : [];
        if (in_array('invalid-input-secret', $codes, true) || in_array('missing-input-secret', $codes, true)) {
            throw ValidationException::withMessages(['turnstile' => Pages::t('adm_cf_secret_bad')]);
        }
        if (!is_array($result)) {
            throw ValidationException::withMessages(['turnstile' => Pages::t('adm_cf_unexpected')]);
        }

        return Pages::t('adm_cf_ok');
    }

    private static function saveProviders(Request $request): string
    {
        $rules = [];
        foreach (array_keys(Config::PROVIDERS) as $key) {
            $rules[$key] = 'nullable|boolean';
            $rules[$key . '_register'] = 'nullable|boolean';
            $rules[$key . '_client_id'] = ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9._-]+$/'];
            $rules[$key . '_secret'] = ['nullable', 'string', 'max:300', 'regex:/^[\x21-\x7E]+$/'];
        }
        $data = $request->validate($rules);

        $values = [];
        foreach (Config::PROVIDERS as $key => $title) {
            $values[$key] = (bool) ($data[$key] ?? false);
            $values[$key . '_register'] = (bool) ($data[$key . '_register'] ?? false);
            $values[$key . '_client_id'] = (string) ($data[$key . '_client_id'] ?? '');
            if (!empty($data[$key . '_secret'])) {
                $values[$key . '_secret'] = encrypt($data[$key . '_secret']);
            }
            $secretSet = isset($values[$key . '_secret']) || Config::hasSecret($key . '_secret');
            if ($values[$key] && ($values[$key . '_client_id'] === '' || !$secretSet)) {
                throw ValidationException::withMessages([$key => Pages::t('adm_provider_needs_keys', ['provider' => $title])]);
            }
        }

        Config::save($values);

        return Pages::t('adm_saved_providers');
    }

    private static function createInvite(Request $request): string
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:80', 'regex:/^[^\x00-\x1F\x7F<>]*$/u'],
            'max_uses' => 'required|integer|min:1|max:100000',
            'expires_days' => 'nullable|integer|min:1|max:730',
        ]);

        $expires = isset($data['expires_days']) ? CarbonImmutable::now()->addDays((int) $data['expires_days']) : null;
        $code = Invites::create($data['label'] ?? null, (int) $data['max_uses'], $expires, $request->user()?->id);

        // Der Klartext existiert nur in dieser einen Antwort.
        session()->flash(self::FLASH_INVITE, $code);

        return Pages::t('adm_invite_created');
    }

    private static function revokeInvite(Request $request): string
    {
        $id = (int) $request->validate(['invite_id' => 'required|integer|min:1'])['invite_id'];
        if (!Invites::revoke($id)) {
            throw ValidationException::withMessages(['invite_id' => Pages::t('adm_invite_gone')]);
        }

        return Pages::t('adm_invite_revoked');
    }

    /**
     * Selbsttest: laeuft die Turnstile-Pruefung wirklich vor dem Login des Panels?
     * Geprueft wird die Middleware-Kette, die der Router fuer POST /auth/login und
     * POST /auth/password tatsaechlich ausfuehrt.
     *
     * @return array{ok: bool, wired: bool, active: bool, reasons: string[]}
     */
    public static function loginProtection(): array
    {
        $reasons = [];
        $wired = true;
        try {
            $router = app('router');
            foreach (['/auth/login', '/auth/password'] as $path) {
                $route = $router->getRoutes()->match(Request::create($path, 'POST'));
                if (!in_array(TurnstileLogin::class, $router->gatherRouteMiddleware($route), true)) {
                    $wired = false;
                    $reasons[] = Pages::t('adm_reason_route', ['path' => $path]);
                }
            }
        } catch (\Throwable $e) {
            $wired = false;
            $reasons[] = Pages::t('adm_reason_routes');
        }
        if (app()->routesAreCached()) {
            $wired = false;
            $reasons[] = Pages::t('adm_reason_cache');
        }

        $active = Config::turnstileProtectsLogin();
        if (!Config::moduleOn()) {
            $reasons[] = Pages::t('adm_reason_module');
        } elseif (Config::get('turnstile') !== true) {
            $reasons[] = Pages::t('adm_reason_off');
        } elseif (Config::get('turnstile_login') !== true) {
            $reasons[] = Pages::t('adm_reason_login_off');
        }
        if ($active && !Config::hasSecret('turnstile_secret')) {
            $reasons[] = Pages::t('adm_reason_secret');
        }

        return ['ok' => $wired && $active && Config::hasSecret('turnstile_secret'), 'wired' => $wired, 'active' => $active, 'reasons' => $reasons];
    }
}
