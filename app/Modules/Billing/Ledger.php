<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Billing;

use Illuminate\Support\Facades\DB;

/**
 * Guthaben. Jede Bewegung ist eine unveränderliche Buchungszeile; der Kontostand im Konto wird
 * in derselben Transaktion unter Zeilensperre fortgeschrieben und entspricht immer der Summe der
 * Zeilen. Die eindeutige "reference" macht jede Buchung wiederholbar, ohne doppelt zu buchen.
 */
class Ledger
{
    public const ACCOUNTS = 'techtyl_billing_accounts';
    public const TABLE = 'techtyl_billing_ledger';

    /** Gründe einer Buchung (für Anzeige und Auswertung). */
    public const REASONS = ['topup', 'daily', 'admin', 'refund'];

    /** Legt das Konto an, falls es fehlt. */
    public static function ensureAccount(int $userId): void
    {
        DB::table(self::ACCOUNTS)->insertOrIgnore([
            'user_id' => $userId,
            'balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function balance(int $userId): int
    {
        return (int) (DB::table(self::ACCOUNTS)->where('user_id', $userId)->value('balance') ?? 0);
    }

    /**
     * Bucht einen Betrag (positiv = Gutschrift, negativ = Abbuchung).
     *
     * @return bool true = gebucht, false = diese Referenz war schon gebucht (nichts geändert)
     * @throws InsufficientFunds wenn eine Abbuchung das Guthaben unter null brächte
     */
    public static function post(int $userId, int $amount, string $reason, string $reference, string $note = '', ?int $actorId = null): bool
    {
        if ($amount === 0 || abs($amount) > Money::MAX_CENTS * 10) {
            throw new \InvalidArgumentException('Ungültiger Buchungsbetrag.');
        }
        if (!in_array($reason, self::REASONS, true) || $reference === '' || strlen($reference) > 120) {
            throw new \InvalidArgumentException('Ungültige Buchung.');
        }

        self::ensureAccount($userId);

        return DB::transaction(function () use ($userId, $amount, $reason, $reference, $note, $actorId) {
            // Zeilensperre: parallele Buchungen desselben Nutzers laufen nacheinander.
            $account = DB::table(self::ACCOUNTS)->where('user_id', $userId)->lockForUpdate()->first();
            if ($account === null) {
                throw new \RuntimeException('Konto fehlt.');
            }
            if (DB::table(self::TABLE)->where('reference', $reference)->exists()) {
                return false;
            }
            $after = (int) $account->balance + $amount;
            if ($amount < 0 && $after < 0) {
                throw new InsufficientFunds();
            }

            DB::table(self::TABLE)->insert([
                'user_id' => $userId,
                'amount' => $amount,
                'balance_after' => $after,
                'reason' => $reason,
                'reference' => $reference,
                'note' => mb_substr($note, 0, 300),
                'actor_id' => $actorId,
                'created_at' => now(),
            ]);
            DB::table(self::ACCOUNTS)->where('user_id', $userId)->update(['balance' => $after, 'updated_at' => now()]);

            return true;
        }, 3);
    }

    /**
     * Bucht höchstens das vorhandene Guthaben ab (für Erstattungen bereits verbrauchter Aufladungen).
     *
     * @return int tatsächlich abgebuchter Betrag (positiv), 0 wenn schon gebucht oder nichts da
     */
    public static function debitUpTo(int $userId, int $amount, string $reason, string $reference, string $note = ''): int
    {
        self::ensureAccount($userId);

        return DB::transaction(function () use ($userId, $amount, $reason, $reference, $note) {
            $account = DB::table(self::ACCOUNTS)->where('user_id', $userId)->lockForUpdate()->first();
            $take = min($amount, max(0, (int) ($account->balance ?? 0)));
            if ($take <= 0 || DB::table(self::TABLE)->where('reference', $reference)->exists()) {
                return 0;
            }

            return self::post($userId, -$take, $reason, $reference, $note) ? $take : 0;
        }, 3);
    }

    /** Prüfsumme: stimmt der Kontostand mit der Summe der Buchungszeilen überein? */
    public static function consistent(int $userId): bool
    {
        return self::balance($userId) === (int) DB::table(self::TABLE)->where('user_id', $userId)->sum('amount');
    }
}
