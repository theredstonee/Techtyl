<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant;

use Illuminate\Support\Facades\DB;

/** Tageszaehler je Nutzer in techtyl_assistant_usage. */
class Quota
{
    public const TABLE = 'techtyl_assistant_usage';

    public static function day(): string
    {
        return now()->toDateString();
    }

    public static function used(int $userId): int
    {
        return (int) DB::table(self::TABLE)->where('user_id', $userId)->where('day', self::day())->value('requests');
    }

    /**
     * Zaehlt eine Anfrage, wenn das Tageslimit noch nicht erreicht ist. Die Zeile wird gesperrt,
     * damit gleichzeitige Anfragen das Limit nicht ueberschreiten.
     */
    public static function reserve(int $userId, int $limit): bool
    {
        $day = self::day();
        DB::table(self::TABLE)->insertOrIgnore([
            'user_id' => $userId, 'day' => $day, 'requests' => 0, 'tokens_in' => 0, 'tokens_out' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table(self::TABLE)
            ->where('user_id', $userId)->where('day', $day)->where('requests', '<', $limit)
            ->increment('requests', 1, ['updated_at' => now()]) === 1;
    }

    /** Gibt eine gezaehlte Anfrage zurueck, wenn der Anbieter gar nichts geliefert hat. */
    public static function refund(int $userId): void
    {
        DB::table(self::TABLE)
            ->where('user_id', $userId)->where('day', self::day())->where('requests', '>', 0)
            ->decrement('requests', 1, ['updated_at' => now()]);
    }

    public static function addTokens(int $userId, ?int $in, ?int $out): void
    {
        if (($in ?? 0) <= 0 && ($out ?? 0) <= 0) {
            return;
        }
        DB::table(self::TABLE)->where('user_id', $userId)->where('day', self::day())->incrementEach(
            ['tokens_in' => max(0, (int) $in), 'tokens_out' => max(0, (int) $out)],
            ['updated_at' => now()],
        );
    }
}
