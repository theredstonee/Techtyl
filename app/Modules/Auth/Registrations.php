<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth;

use Pterodactyl\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Pterodactyl\Services\Users\UserCreationService;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Notifications\VerifyRegistration;

/**
 * Ausstehende Registrierungen und das Anlegen von Konten.
 *
 * Ein Eintrag in "users" entsteht erst, wenn der Link aus der E-Mail geoeffnet und ein Passwort
 * gesetzt wurde. Der Link traegt 32 Zufallsbytes; gespeichert wird nur deren SHA-256-Wert.
 */
class Registrations
{
    public const TABLE = 'techtyl_auth_pending';

    public const LIFETIME_MINUTES = 60;
    /** Hoechstens so viele Mails je Adresse und Stunde, mit Mindestabstand. */
    public const MAILS_PER_HOUR = 3;
    public const MAIL_GAP_SECONDS = 60;

    public const BCRYPT_MIN_ROUNDS = 12;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Startet oder erneuert eine Registrierung und verschickt die Bestaetigungs-Mail. Gibt nichts
     * zurueck: der Aufrufer antwortet immer gleich, egal ob die Adresse schon ein Konto hat,
     * das Limit erreicht ist oder wirklich eine Mail hinausging.
     *
     * @param array{email: string, username: string, name_first: string, name_last: string} $data
     */
    public static function start(array $data, ?int $inviteId, ?string $locale = null): void
    {
        // The mail is written in the language the registration was made in.
        $locale = in_array($locale, I18n::SUPPORTED, true) ? $locale : I18n::locale();
        $email = $data['email'];
        if (DB::table('users')->where('email', $email)->exists()) {
            Log::info('techtyl auth: Registrierung fuer bestehende Adresse ignoriert');

            return;
        }

        $row = DB::table(self::TABLE)->where('email', $email)->first();
        if ($row !== null && !self::maySend($row)) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $now = now();
        $windowOpen = $row !== null && $row->window_started_at !== null
            && $now->diffInSeconds($row->window_started_at, true) < 3600;

        $values = [
            'username' => $data['username'],
            'name_first' => $data['name_first'],
            'name_last' => $data['name_last'],
            'token_hash' => self::hashToken($token),
            'invite_id' => $inviteId,
            'locale' => $locale,
            'sent_count' => $windowOpen ? ((int) $row->sent_count) + 1 : 1,
            'window_started_at' => $windowOpen ? $row->window_started_at : $now,
            'last_sent_at' => $now,
            'expires_at' => $now->copy()->addMinutes(self::LIFETIME_MINUTES),
            'updated_at' => $now,
        ];

        if ($row === null) {
            DB::table(self::TABLE)->insert($values + ['email' => $email, 'created_at' => $now]);
        } else {
            DB::table(self::TABLE)->where('id', $row->id)->update($values);
        }

        self::send($email, $token, $locale);
    }

    /** Schickt fuer eine bestehende, ausstehende Registrierung einen neuen Link (der alte verfaellt). */
    public static function resend(string $email): void
    {
        $row = DB::table(self::TABLE)->where('email', $email)->first();
        if ($row === null) {
            return;
        }

        self::start([
            'email' => $row->email,
            'username' => $row->username,
            'name_first' => $row->name_first,
            'name_last' => $row->name_last,
        ], $row->invite_id === null ? null : (int) $row->invite_id, (string) ($row->locale ?? ''));
    }

    private static function maySend(object $row): bool
    {
        $now = now();
        if ($row->last_sent_at !== null && $now->diffInSeconds($row->last_sent_at, true) < self::MAIL_GAP_SECONDS) {
            return false;
        }
        $windowOpen = $row->window_started_at !== null && $now->diffInSeconds($row->window_started_at, true) < 3600;

        return !$windowOpen || (int) $row->sent_count < self::MAILS_PER_HOUR;
    }

    private static function send(string $email, string $token, string $locale): void
    {
        try {
            Notification::route('mail', $email)->notify(new VerifyRegistration($token, $locale));
        } catch (\Throwable $e) {
            // Der Aufrufer antwortet trotzdem gleich; der Betreiber sieht den Grund im Log.
            Log::error('techtyl auth: Bestaetigungs-Mail konnte nicht eingereiht werden', ['error' => $e->getMessage()]);
        }
    }

    /** Gueltiger, nicht abgelaufener Eintrag zu einem Link-Token oder null. */
    public static function find(mixed $token): ?object
    {
        if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }

        return DB::table(self::TABLE)
            ->where('token_hash', self::hashToken($token))
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Schliesst eine Registrierung ab: Eintrag einmalig einloesen, Einladung verbrauchen, Konto anlegen.
     * Schlaegt ein Schritt fehl, wird alles zurueckgerollt und der Link bleibt gueltig.
     *
     * @throws RegistrationFailed
     */
    public static function complete(object $pending, string $username, string $password): User
    {
        return DB::transaction(function () use ($pending, $username, $password) {
            $claimed = DB::table(self::TABLE)
                ->where('id', $pending->id)
                ->where('token_hash', $pending->token_hash)
                ->where('expires_at', '>', now())
                ->delete();
            if ($claimed !== 1) {
                throw new RegistrationFailed('link');
            }

            self::guard($pending->email, $username, $pending->invite_id === null ? null : (int) $pending->invite_id);

            return self::createUser([
                'email' => $pending->email,
                'username' => $username,
                'name_first' => $pending->name_first,
                'name_last' => $pending->name_last,
            ], $password);
        });
    }

    /**
     * Prueft unmittelbar vor dem Anlegen noch einmal alle Regeln (sie koennen sich seit dem Start
     * geaendert haben) und verbraucht die Einladung. Innerhalb einer Transaktion aufrufen.
     *
     * @throws RegistrationFailed
     */
    public static function guard(string $email, string $username, ?int $inviteId): void
    {
        if (Config::get('registration') !== true || !Config::moduleOn()) {
            throw new RegistrationFailed('closed');
        }
        if (!Config::domainAllowed($email)) {
            throw new RegistrationFailed('domain');
        }
        if (DB::table('users')->where('email', $email)->exists()) {
            throw new RegistrationFailed('link');
        }
        if (DB::table('users')->where('username', $username)->exists()) {
            throw new RegistrationFailed('username');
        }
        if (Config::get('invite_required') === true && ($inviteId === null || !Invites::consume($inviteId))) {
            throw new RegistrationFailed('invite');
        }
    }

    /**
     * Legt das Konto ueber den Dienst des Panels an (nie als Admin) und stellt sicher, dass das
     * Passwort mit bcrypt und mindestens 12 Runden gehasht ist.
     *
     * Weg: UserCreationService hasht selbst ueber den Hasher des Containers. Dessen bcrypt-Treiber
     * bekommt fuer diesen einen Aufruf mindestens 12 Runden und danach wieder den Panel-Wert. Zur
     * Sicherheit wird der gespeicherte Hash geprueft und bei Bedarf gezielt neu geschrieben.
     *
     * @param array{email: string, username: string, name_first: string, name_last: string} $data
     */
    public static function createUser(array $data, string $password): User
    {
        $panelRounds = (int) config('hashing.bcrypt.rounds', 10);
        $rounds = max(self::BCRYPT_MIN_ROUNDS, $panelRounds);
        $driver = Hash::driver('bcrypt');

        $driver->setRounds($rounds);
        try {
            $user = app(UserCreationService::class)->handle([
                'email' => $data['email'],
                'username' => $data['username'],
                'name_first' => $data['name_first'],
                'name_last' => $data['name_last'],
                'password' => $password,
                'root_admin' => false,
            ]);
        } finally {
            $driver->setRounds($panelRounds);
        }

        $info = password_get_info((string) $user->password);
        $algo = (string) ($info['algoName'] ?? '');
        $strongEnough = str_starts_with($algo, 'argon2')
            || ($algo === 'bcrypt' && (int) ($info['options']['cost'] ?? 0) >= self::BCRYPT_MIN_ROUNDS);
        if (!$strongEnough) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => $rounds]);
            DB::table('users')->where('id', $user->id)->update(['password' => $hash]);
            $user->setRawAttributes(array_merge($user->getAttributes(), ['password' => $hash]), true);
        }

        if ($user->root_admin) {
            // Darf nie eintreten; lieber kein Konto als ein Admin-Konto.
            throw new \RuntimeException('techtyl auth: neues Konto waere Admin');
        }

        return $user;
    }

    /** Macht aus dem optionalen Feld "Name" Vor- und Nachname; fehlende Teile werden vorbelegt. */
    public static function splitName(?string $name, string $username): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $name) ?? '');
        if ($name === '') {
            return [$username, '-'];
        }
        $space = mb_strrpos($name, ' ');
        if ($space === false) {
            return [$name, '-'];
        }

        return [mb_substr($name, 0, $space), mb_substr($name, $space + 1)];
    }

    /** Loescht abgelaufene Eintraege. Laeuft als geplanter Job und beim Oeffnen des Admin-Reiters. */
    public static function cleanup(): int
    {
        return DB::table(self::TABLE)->where('expires_at', '<', now())->delete();
    }
}
