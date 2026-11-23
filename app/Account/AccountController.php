<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Account;

use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Settings;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\ImageRejected;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\ImageProcessor;

/**
 * Kontobereich unter /api/client/extensions/techtyl/account. Alles bezieht sich auf den
 * angemeldeten Nutzer; fremde Konten lassen sich hier weder lesen noch aendern. E-Mail, Passwort,
 * Zwei-Faktor, API- und SSH-Schluessel laufen weiter ueber die Client-API des Panels.
 */
class AccountController extends Controller
{
    private const MAX_LOOKUP = 100;
    private const NAME_RULES = ['required', 'string', 'min:1', 'max:191', 'regex:/^[^\p{Cc}\p{Cf}<>]+$/u'];

    /** Alles, was die Kontoseiten beim Start brauchen. */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $settings = AccountSettings::get();
        $avatar = Avatar::describe($user->id);
        $custom = DB::table(Avatar::TABLE)->where('user_id', $user->id)->exists();

        return new JsonResponse([
            'object' => 'techtyl_account',
            'attributes' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'username' => $user->username,
                'email' => $user->email,
                'first_name' => $user->name_first,
                'last_name' => $user->name_last,
                'root_admin' => (bool) $user->root_admin,
                'use_totp' => (bool) $user->use_totp,
                'created_at' => optional($user->created_at)->toAtomString(),
                'avatar' => $avatar + ['custom' => $custom],
                'permissions' => [
                    'upload_avatar' => $settings['uploads'],
                    'change_name' => $settings['names'],
                    // Das Panel laesst Benutzernamen nur vom Admin aendern; daran aendert Techtyl nichts.
                    'change_username' => false,
                ],
                'limits' => [
                    'max_upload_bytes' => Settings::limits()['max_upload_kb'] * 1024,
                    'mime_types' => array_keys(array_filter(ImageProcessor::readable())),
                    'avatar_size' => Avatar::SIZE,
                ],
                'modules' => [
                    'tickets' => Modules::enabled('tickets'),
                    'billing' => Modules::enabled('billing'),
                    'assistant' => Modules::enabled('assistant'),
                ],
                'session' => $this->sessionInfo($request),
            ],
        ]);
    }

    /** Vor- und Nachname aendern, nur wenn der Admin das erlaubt. */
    public function updateProfile(Request $request): JsonResponse
    {
        if (!AccountSettings::get()['names']) {
            throw new AccessDeniedHttpException(I18n::t('account.names_locked'));
        }
        $data = $request->validate(['first_name' => self::NAME_RULES, 'last_name' => self::NAME_RULES], [
            'first_name.required' => I18n::t('account.first_name_required'),
            'last_name.required' => I18n::t('account.last_name_required'),
            'first_name.min' => I18n::t('account.first_name_required'),
            'last_name.min' => I18n::t('account.last_name_required'),
            'first_name.max' => I18n::t('account.name_too_long'),
            'last_name.max' => I18n::t('account.name_too_long'),
            'first_name.regex' => I18n::t('account.name_chars'),
            'last_name.regex' => I18n::t('account.name_chars'),
            'first_name.string' => I18n::t('account.first_name_required'),
            'last_name.string' => I18n::t('account.last_name_required'),
        ]);

        /** @var User $user */
        $user = $request->user();
        $old = trim($user->name_first . ' ' . $user->name_last);
        $first = trim($data['first_name']);
        $last = trim($data['last_name']);
        if ($first === '' || $last === '') {
            throw ValidationException::withMessages(['first_name' => [I18n::t('account.name_both')]]);
        }
        if ($first !== $user->name_first || $last !== $user->name_last) {
            $user->update(['name_first' => $first, 'name_last' => $last]);
            Activity::event('user:account.name-changed')
                ->subject($user)
                ->property(['old' => $old, 'new' => trim($first . ' ' . $last)])
                ->log();
        }

        return $this->me($request);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        if (!AccountSettings::get()['uploads']) {
            throw new AccessDeniedHttpException(I18n::t('account.uploads_off'));
        }
        $maxKilobytes = (int) Settings::limits()['max_upload_kb'];
        $request->validate([
            'image' => ['required', 'file', 'max:' . $maxKilobytes, 'mimetypes:' . implode(',', ImageProcessor::MIME_TYPES)],
        ], [
            'image.required' => I18n::t('account.image_required'),
            'image.file' => I18n::t('account.image_required'),
            'image.uploaded' => I18n::t('account.image_upload'),
            'image.max' => I18n::t('account.image_max', ['size' => $maxKilobytes]),
            'image.mimetypes' => I18n::t('account.image_type'),
        ]);

        try {
            $name = Avatar::store((string) $request->file('image')->getRealPath());
        } catch (ImageRejected $e) {
            throw ValidationException::withMessages(['image' => [$this->imageMessage($e->getMessage())]]);
        } catch (\Throwable $e) {
            Log::error('techtyl account: Profilbild konnte nicht gespeichert werden', ['error' => $e->getMessage()]);
            throw new HttpException(500, I18n::t('account.image_failed'));
        }

        /** @var User $user */
        $user = $request->user();
        try {
            $previous = DB::transaction(function () use ($user, $name) {
                $previous = DB::table(Avatar::TABLE)->where('user_id', $user->id)->lockForUpdate()->value('file');
                DB::table(Avatar::TABLE)->updateOrInsert(
                    ['user_id' => $user->id],
                    ['file' => $name, 'updated_at' => now(), 'created_at' => now()],
                );

                return $previous;
            });
        } catch (\Throwable $e) {
            Avatar::delete($name);
            Log::error('techtyl account: Profilbild konnte nicht eingetragen werden', ['error' => $e->getMessage()]);
            throw new HttpException(500, I18n::t('account.image_failed'));
        }
        Avatar::delete($previous);
        $this->pruneSometimes();

        return $this->avatarResponse($user);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $previous = DB::table(Avatar::TABLE)->where('user_id', $user->id)->value('file');
        if ($previous !== null) {
            DB::table(Avatar::TABLE)->where('user_id', $user->id)->delete();
            Avatar::delete($previous);
        }

        return $this->avatarResponse($user);
    }

    /**
     * Profilbilder anderer Nutzer: ?ids=1,2,3 (hoechstens 100). Geliefert werden nur Nutzer, mit
     * denen der Anfragende zu tun hat (er selbst, das Team = Root-Admins, Besitzer und Subuser
     * gemeinsamer Server); Root-Admins sehen alle. Unbekannte oder fremde IDs fehlen in der Antwort.
     */
    public function avatars(Request $request): JsonResponse
    {
        $raw = $request->query('ids', '');
        $parts = is_array($raw) ? $raw : explode(',', is_string($raw) ? $raw : '');
        if (count($parts) > self::MAX_LOOKUP) {
            throw ValidationException::withMessages(['ids' => [I18n::t('account.lookup_max', ['count' => self::MAX_LOOKUP])]]);
        }
        $ids = [];
        foreach ($parts as $part) {
            if (!is_string($part) && !is_int($part)) {
                throw ValidationException::withMessages(['ids' => [I18n::t('account.lookup_invalid')]]);
            }
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            if (!ctype_digit($part) || strlen($part) > 10 || (int) $part < 1) {
                throw ValidationException::withMessages(['ids' => [I18n::t('account.lookup_invalid')]]);
            }
            $ids[(int) $part] = (int) $part;
        }

        /** @var User $user */
        $user = $request->user();
        $visible = $this->visibleUserIds($user, array_values($ids));
        $out = [];
        foreach (Avatar::describeMany($visible) as $id => $avatar) {
            $out[(string) $id] = $avatar;
        }

        return new JsonResponse(['object' => 'techtyl_avatars', 'data' => (object) $out]);
    }

    /** Liefert ein gespeichertes Profilbild aus (nur fuer angemeldete Nutzer). */
    public function image(string $token): BinaryFileResponse
    {
        $found = Avatar::find($token);
        if ($found === null) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($found['path'], 200, [
            'Content-Type' => $found['mime'],
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
        // Jeder Upload bekommt einen neuen Namen; der Inhalt unter einem Namen aendert sich nie.
        $response->setCache(['private' => true, 'max_age' => 31536000, 'immutable' => true]);

        return $response;
    }

    /** Aktuelle Sitzung (Geraet, IP) fuer die Sicherheitsseite. */
    public function security(Request $request): JsonResponse
    {
        return new JsonResponse([
            'object' => 'techtyl_account_security',
            'attributes' => [
                'session' => $this->sessionInfo($request),
                // Das Panel speichert Sitzungen nicht je Nutzer abrufbar (Redis/Datei); eine Liste aller
                // Geraete gibt es deshalb nicht, wohl aber das Abmelden aller anderen Sitzungen.
                'can_logout_others' => $request->hasSession() && Auth::guard('web') instanceof SessionGuard,
            ],
        ]);
    }

    /**
     * Meldet alle anderen Browser-Sitzungen ab (Laravel logoutOtherDevices, wie es das Panel nach
     * einer Passwortaenderung selbst verwendet). Die aktuelle Sitzung bleibt angemeldet.
     * API-Schluessel sind davon nicht betroffen.
     */
    public function logoutOthers(Request $request): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'max:255']], [
            'password.required' => I18n::t('account.password_required'),
            'password.string' => I18n::t('account.password_required'),
            'password.max' => I18n::t('account.password_wrong'),
        ]);
        $guard = Auth::guard('web');
        /** @var User $user */
        $user = $request->user();
        if (!$request->hasSession() || !$guard instanceof SessionGuard || $guard->user()?->id !== $user->id) {
            throw new BadRequestHttpException(I18n::t('account.browser_only'));
        }
        if (!Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => [I18n::t('account.password_wrong')]]);
        }

        try {
            $guard->logoutOtherDevices($data['password']);
            // Neuen Passwort-Hash in die eigene Sitzung schreiben, damit sie angemeldet bleibt.
            $request->session()->put(
                'password_hash_' . Auth::getDefaultDriver(),
                method_exists($guard, 'hashPasswordForCookie') ? $guard->hashPasswordForCookie($guard->user()->getAuthPassword()) : $guard->user()->getAuthPassword(),
            );
        } catch (\Throwable $e) {
            Log::error('techtyl account: andere Sitzungen konnten nicht beendet werden', ['error' => $e->getMessage()]);
            throw new HttpException(500, I18n::t('account.sessions_failed'));
        }
        Activity::event('user:account.sessions-ended')->subject($user)->log();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Sets the panel language of the own account (users.language). Only "en" and "de"; the
     * interface follows immediately because both the frontend store and I18n read this value.
     */
    public function updateLanguage(Request $request): JsonResponse
    {
        $data = $request->validate(['language' => ['required', 'string', 'in:' . implode(',', I18n::SUPPORTED)]], [
            'language.required' => I18n::t('account.language_invalid'),
            'language.string' => I18n::t('account.language_invalid'),
            'language.in' => I18n::t('account.language_invalid'),
        ]);

        /** @var User $user */
        $user = $request->user();
        // The panel validates users.language against the folders in resources/lang on every save of
        // the user. Without resources/lang/de a German account could no longer change its password
        // or email, so the language is only accepted when the panel knows it.
        if (!array_key_exists($data['language'], $user->getAvailableLanguages())) {
            throw ValidationException::withMessages(['language' => [I18n::t('account.language_missing')]]);
        }
        if ($user->language !== $data['language']) {
            $old = (string) $user->language;
            $user->update(['language' => $data['language']]);
            Activity::event('user:account.language-changed')
                ->subject($user)
                ->property(['old' => $old, 'new' => $data['language']])
                ->log();
        }

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /* ------------------------------------------------------------------ intern */

    /** The shared image pipeline reports in German ASCII; map its messages to the user's language. */
    private function imageMessage(string $message): string
    {
        return match (true) {
            str_contains($message, 'zu klein') => I18n::t('account.image_small'),
            str_contains($message, 'zu viele Pixel') => I18n::t('account.image_pixels'),
            str_contains($message, 'Bildformat') => I18n::t('account.image_format'),
            str_contains($message, 'Erlaubt sind nur') => I18n::t('account.image_type'),
            default => I18n::t('account.image_invalid'),
        };
    }

    private function avatarResponse(User $user): JsonResponse
    {
        return new JsonResponse([
            'object' => 'techtyl_avatar',
            'attributes' => Avatar::describe($user->id) + [
                'custom' => DB::table(Avatar::TABLE)->where('user_id', $user->id)->exists(),
            ],
        ]);
    }

    /** @return array{ip: ?string, user_agent: string} */
    private function sessionInfo(Request $request): array
    {
        return [
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 400),
        ];
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, int>
     */
    private function visibleUserIds(User $user, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        if ($user->root_admin) {
            return $ids;
        }

        $visible = in_array($user->id, $ids, true) ? [$user->id] : [];
        // Das Team (Root-Admins), z. B. als Antwortende in Tickets.
        $visible = array_merge($visible, DB::table('users')->whereIn('id', $ids)->where('root_admin', true)->pluck('id')->all());

        // Besitzer und Subuser von Servern, die der Nutzer selbst sieht.
        $servers = DB::table('servers')->where('owner_id', $user->id)->pluck('id')
            ->merge(DB::table('subusers')->where('user_id', $user->id)->pluck('server_id'))
            ->unique()->values()->all();
        if ($servers !== []) {
            $visible = array_merge(
                $visible,
                DB::table('servers')->whereIn('id', $servers)->whereIn('owner_id', $ids)->pluck('owner_id')->all(),
                DB::table('subusers')->whereIn('server_id', $servers)->whereIn('user_id', $ids)->pluck('user_id')->all(),
            );
        }

        return array_values(array_unique(array_map('intval', $visible)));
    }

    /** Raeumt gelegentlich verwaiste Dateien weg (z. B. von geloeschten Nutzern). */
    private function pruneSometimes(): void
    {
        if (random_int(1, 20) !== 1) {
            return;
        }
        try {
            Avatar::prune();
        } catch (\Throwable $e) {
            Log::warning('techtyl account: Aufraeumen der Profilbilder fehlgeschlagen', ['error' => $e->getMessage()]);
        }
    }
}
