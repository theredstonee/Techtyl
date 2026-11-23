<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Billing;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Services\Servers\ServerCreationService;
use Pterodactyl\Services\Deployment\FindViableNodesService;
use Pterodactyl\Services\Deployment\AllocationSelectionService;
use Pterodactyl\Exceptions\Service\Deployment\NoViableNodeException;
use Pterodactyl\Exceptions\Service\Deployment\NoViableAllocationException;

/**
 * Legt den Server zu einem bezahlten Abo an. Läuft nie innerhalb einer Datenbank-Transaktion
 * (Wings fragt den Server während der Installation beim Panel ab).
 *
 * Schutz vor doppeltem Anlegen:
 *  1. Der Zustand wird mit EINEM UPDATE von "none" auf "provisioning" gesetzt; nur wer diese Zeile
 *     ändert, legt an.
 *  2. Der Server trägt die eindeutige external_id "techtyl-billing-<Abo>"; ein zweiter Server mit
 *     derselben Kennung ist in der Panel-Datenbank nicht möglich. Ein vorhandener wird übernommen.
 */
class Provisioner
{
    /** Nach dieser Zeit gilt ein hängender Versuch als abgebrochen. */
    public const STALE_MINUTES = 10;

    /**
     * @param bool $retry true = auch nach "failed" erneut versuchen (Admin-Aktion)
     * @return string "done", "failed" oder "skipped" (nicht zuständig oder läuft bereits)
     */
    public static function provision(int $subscriptionId, bool $retry = false): string
    {
        $states = $retry ? ['none', 'failed'] : ['none'];
        $claimed = DB::table(Billing::SUBSCRIPTIONS)
            ->where('id', $subscriptionId)
            ->where('status', 'active')
            ->whereNull('server_id')
            ->whereNull('server_deleted_at')
            ->where(function ($query) use ($states, $retry) {
                $query->whereIn('provision_state', $states);
                if ($retry) {
                    $query->orWhere(function ($stale) {
                        $stale->where('provision_state', 'provisioning')
                            ->where('provision_started_at', '<', now()->subMinutes(self::STALE_MINUTES));
                    });
                }
            })
            ->update(['provision_state' => 'provisioning', 'provision_started_at' => now(), 'provision_error' => '', 'updated_at' => now()]);
        if ($claimed !== 1) {
            return 'skipped';
        }

        $subscription = DB::table(Billing::SUBSCRIPTIONS)->where('id', $subscriptionId)->first();

        try {
            // Gab es schon einen Versuch, der den Server angelegt, aber nicht mehr vermerkt hat?
            $server = Server::query()->where('external_id', Billing::externalId($subscriptionId))->first();
            if ($server !== null && (int) $server->owner_id !== (int) $subscription->user_id) {
                throw new \RuntimeException('Vorhandener Server gehört einem anderen Nutzer.');
            }
            if ($server === null) {
                $server = self::create($subscription);
            }
        } catch (\Throwable $e) {
            $noSpace = $e instanceof NoViableNodeException || $e instanceof NoViableAllocationException;
            // Stored as a code; translated where it is shown.
            $message = $noSpace ? 'no_space' : 'error';
            Log::error('techtyl billing: Bereitstellung fehlgeschlagen', [
                'subscription' => $subscriptionId,
                'exception' => get_class($e),
                'error' => $e->getMessage(),
            ]);
            DB::table(Billing::SUBSCRIPTIONS)->where('id', $subscriptionId)->where('provision_state', 'provisioning')
                ->update(['provision_state' => 'failed', 'provision_error' => $message, 'updated_at' => now()]);
            Billing::log('provision-failed', $noSpace ? 'No node with enough memory, disk space and a free allocation.' : 'Server could not be created (see panel log).', $subscriptionId, (int) $subscription->user_id);

            return 'failed';
        }

        DB::table(Billing::SUBSCRIPTIONS)->where('id', $subscriptionId)
            ->update(['server_id' => $server->id, 'provision_state' => 'done', 'provision_error' => '', 'updated_at' => now()]);
        Billing::log('provision', 'Server ' . $server->uuidShort . ' created.', $subscriptionId, (int) $subscription->user_id, (int) $server->id);

        return 'done';
    }

    private static function create(object $subscription): Server
    {
        $spec = json_decode((string) $subscription->spec, true);
        if (!is_array($spec)) {
            throw new \RuntimeException('Paketdaten des Abos nicht lesbar.');
        }

        /** @var Egg $egg */
        $egg = Egg::query()->with('variables')->findOrFail((int) $subscription->egg_id);
        $images = array_values((array) $egg->docker_images);
        if ($images === []) {
            throw new \RuntimeException('Egg ohne Docker-Image.');
        }
        $environment = [];
        foreach ($egg->variables as $variable) {
            $environment[$variable->env_variable] = $variable->default_value;
        }

        // Node suchen: genug Arbeitsspeicher und Speicherplatz (Panel-Dienst), eingeschränkt auf die
        // Locations bzw. Nodes des Pakets; danach eine freie Allocation auf einem dieser Nodes.
        $nodes = app(FindViableNodesService::class)
            ->setLocations(Billing::ids($spec['location_ids'] ?? []))
            ->setMemory((int) $spec['memory'])
            ->setDisk((int) $spec['disk'])
            ->handle();
        $nodeIds = $nodes->pluck('id')->map(fn ($id) => (int) $id)->all();
        $allowed = Billing::ids($spec['node_ids'] ?? []);
        if ($allowed !== []) {
            $nodeIds = array_values(array_intersect($nodeIds, $allowed));
        }
        if ($nodeIds === []) {
            throw new NoViableNodeException('Kein erlaubter Node mit genug Platz.');
        }
        $allocation = app(AllocationSelectionService::class)
            ->setDedicated(false)
            ->setNodes($nodeIds)
            ->setPorts([])
            ->handle();

        return app(ServerCreationService::class)->handle([
            'external_id' => Billing::externalId((int) $subscription->id),
            'name' => (string) $subscription->server_name,
            'description' => '',
            'owner_id' => (int) $subscription->user_id,
            'egg_id' => (int) $egg->id,
            'nest_id' => (int) $egg->nest_id,
            'node_id' => (int) $allocation->node_id,
            'allocation_id' => (int) $allocation->id,
            'image' => (string) $images[0],
            'startup' => (string) $egg->startup,
            'environment' => $environment,
            'memory' => (int) $spec['memory'],
            'swap' => 0,
            'disk' => (int) $spec['disk'],
            'io' => 500,
            'cpu' => (int) $spec['cpu'],
            'threads' => null,
            'oom_disabled' => true,
            'database_limit' => (int) $spec['databases'],
            'allocation_limit' => (int) $spec['allocations'],
            'backup_limit' => (int) $spec['backups'],
            'skip_scripts' => false,
            'start_on_completion' => false,
        ]);
    }
}
