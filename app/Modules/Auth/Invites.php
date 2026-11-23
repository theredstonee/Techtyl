<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Einladungscodes. In der Datenbank liegt nur der SHA-256-Wert des normalisierten Codes. */
class Invites
{
    public const TABLE = 'techtyl_auth_invites';

    /** Ohne leicht verwechselbare Zeichen (0/O, 1/I/L). */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** Grossbuchstaben und Ziffern, ohne Trennzeichen. */
    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    }

    public static function hash(string $code): string
    {
        return hash('sha256', self::normalize($code));
    }

    /**
     * Legt einen Code an und liefert den Klartext (20 Zeichen, rund 99 Bit) genau einmal zurueck.
     */
    public static function create(?string $label, int $maxUses, ?CarbonImmutable $expiresAt, ?int $createdBy): string
    {
        $raw = '';
        $last = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < 20; $i++) {
            $raw .= self::ALPHABET[random_int(0, $last)];
        }

        DB::table(self::TABLE)->insert([
            'code_hash' => self::hash($raw),
            'hint' => substr($raw, 0, 4),
            'label' => $label,
            'max_uses' => $maxUses,
            'uses' => 0,
            'expires_at' => $expiresAt,
            'created_by' => $createdBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return implode('-', str_split($raw, 5));
    }

    /** ID eines gueltigen (nicht widerrufenen, nicht abgelaufenen, nicht aufgebrauchten) Codes oder null. */
    public static function findUsable(string $code): ?int
    {
        $normalized = self::normalize($code);
        if (strlen($normalized) !== 20) {
            return null;
        }

        $id = self::usable()->where('code_hash', hash('sha256', $normalized))->value('id');

        return $id === null ? null : (int) $id;
    }

    /** Verbraucht eine Nutzung; false, wenn der Code inzwischen nicht mehr gilt. Atomar. */
    public static function consume(int $id): bool
    {
        return self::usable()->where('id', $id)->update([
            'uses' => DB::raw('uses + 1'),
            'updated_at' => now(),
        ]) === 1;
    }

    public static function revoke(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]) === 1;
    }

    private static function usable(): \Illuminate\Database\Query\Builder
    {
        return DB::table(self::TABLE)
            ->whereNull('revoked_at')
            ->whereColumn('uses', '<', 'max_uses')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
