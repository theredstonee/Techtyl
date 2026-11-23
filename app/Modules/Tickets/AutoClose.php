<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tickets;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tickets\Notifications\TicketNotification;

/**
 * Zeitgesteuerte Aufgabe: schliesst Tickets, bei denen der Nutzer seit N Tagen am Zug ist und
 * nicht geantwortet hat, und raeumt verwaiste Anhaenge weg.
 */
class AutoClose
{
    private const BATCH = 200;

    /** @return array{closed: int, pruned: int, skipped: string|null} */
    public static function run(): array
    {
        if (!Modules::enabled('tickets') || !Schema::hasTable(Tickets::TICKETS)) {
            return ['closed' => 0, 'pruned' => 0, 'skipped' => I18n::t('tickets.skipped_off')];
        }

        $closed = 0;
        $days = TicketSettings::get()['autoclose_days'];
        if ($days > 0) {
            $ids = DB::table(Tickets::TICKETS)
                ->whereIn('status', [Tickets::ANSWERED, Tickets::WAITING])
                ->whereNotNull('awaiting_user_since')
                ->where('awaiting_user_since', '<', now()->subDays($days))
                ->orderBy('id')->limit(self::BATCH)->pluck('id');
            foreach ($ids as $id) {
                try {
                    // Erneut unter Sperre pruefen: der Nutzer koennte in diesem Moment antworten.
                    $done = DB::transaction(function () use ($id, $days) {
                        $still = DB::table(Tickets::TICKETS)->where('id', $id)
                            ->whereIn('status', [Tickets::ANSWERED, Tickets::WAITING])
                            ->where('awaiting_user_since', '<', now()->subDays($days))
                            ->lockForUpdate()->exists();
                        if ($still) {
                            Tickets::setStatus((int) $id, Tickets::CLOSED, 'auto', null);
                        }

                        return $still;
                    });
                    if ($done) {
                        ++$closed;
                        Tickets::notifyUser((int) $id, TicketNotification::AUTO_CLOSED);
                    }
                } catch (\Throwable $e) {
                    Log::warning('techtyl tickets: automatisches Schliessen fehlgeschlagen', ['ticket' => $id, 'error' => $e->getMessage()]);
                }
            }
        }

        $pruned = 0;
        try {
            $pruned = Attachments::prune();
        } catch (\Throwable $e) {
            Log::warning('techtyl tickets: Aufraeumen der Anhaenge fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        return ['closed' => $closed, 'pruned' => $pruned, 'skipped' => null];
    }
}
