<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Aufbewahrung: Gespraeche, die laenger als die eingestellte Zahl von Tagen unberuehrt sind,
 * werden geloescht (Nachrichten haengen per Fremdschluessel daran). 0 Tage = unbegrenzt.
 */
class Retention
{
    private const USAGE_DAYS = 400;

    /** @return int Zahl der geloeschten Gespraeche */
    public static function prune(): int
    {
        $deleted = 0;
        $days = Config::int('retention_days');
        if ($days > 0) {
            $deleted = DB::table('techtyl_assistant_conversations')->where('updated_at', '<', now()->subDays($days))->delete();
        }
        DB::table(Quota::TABLE)->where('day', '<', now()->subDays(self::USAGE_DAYS)->toDateString())->delete();

        return $deleted;
    }

    /** Raeumt bei etwa jedem zwanzigsten Aufruf auf; ein Fehler darf die Seite nie stoeren. */
    public static function sometimes(): void
    {
        if (random_int(1, 20) !== 1) {
            return;
        }
        try {
            self::prune();
        } catch (\Throwable $e) {
            Log::warning('techtyl assistant: Aufraeumen des Verlaufs fehlgeschlagen', ['error' => $e->getMessage()]);
        }
    }
}
