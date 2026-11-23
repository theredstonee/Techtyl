<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Permission;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Repositories\Wings\DaemonRepository;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;

/**
 * Server-Daten fuer ein Gespraech: Basisdaten und die letzten Konsolenzeilen. Wird nur gebaut,
 * wenn der Nutzer fuer dieses Gespraech zugestimmt hat und die Konsole des Servers sehen darf.
 * Alles wird vor dem Senden maskiert und in der Laenge begrenzt.
 */
class ServerContext
{
    public const MARKER = 'SERVER DATA';

    /** Server anhand der Kurz-ID oder UUID, aber nur wenn der Nutzer dessen Konsole sehen darf. */
    public static function find(User $user, string $identifier): ?Server
    {
        $server = Server::query()
            ->where(strlen($identifier) === 8 ? 'uuidShort' : 'uuid', $identifier)
            ->first();

        return $server !== null && self::allowed($user, $server) ? $server : null;
    }

    /** Dieselbe Pruefung wie die Konsole im Panel: Besitzer, Subuser mit control.console, Root-Admin. */
    public static function allowed(User $user, Server $server): bool
    {
        return $user->can(Permission::ACTION_CONTROL_CONSOLE, $server);
    }

    /**
     * Server, die der Nutzer auswaehlen kann (eigene und freigegebene mit Konsolen-Recht).
     *
     * @return array<int, array{id: string, name: string, egg: string}>
     */
    public static function selectable(User $user): array
    {
        $servers = $user->accessibleServers()->with('egg:id,name')->orderBy('servers.name')->limit(200)->get();
        $list = [];
        foreach ($servers as $server) {
            if (self::allowed($user, $server)) {
                $list[] = [
                    'id' => (string) $server->uuidShort,
                    'name' => (string) $server->name,
                    'egg' => (string) ($server->egg->name ?? ''),
                ];
            }
        }

        return $list;
    }

    /**
     * @return array{text: string, lines: int} maskierter Datenblock samt Zeilenzahl der Konsole
     */
    public static function build(Server $server, int $maxLines, int $maxChars): array
    {
        $server->loadMissing(['egg:id,name,nest_id', 'egg.nest:id,name']);

        $facts = [
            'Name: ' . self::line((string) $server->name, 80),
            'Software (egg): ' . self::line((string) ($server->egg->name ?? 'unknown'), 80)
                . (isset($server->egg->nest->name) ? ' / ' . self::line((string) $server->egg->nest->name, 60) : ''),
            'Docker image: ' . self::line((string) $server->image, 120),
            'Limits: ' . self::limit((int) $server->memory, 'MB RAM') . ', ' . self::limit((int) $server->disk, 'MB disk')
                . ', ' . self::limit((int) $server->cpu, '% CPU'),
        ];
        if ($server->status !== null) {
            $facts[] = 'Panel status: ' . self::line((string) $server->status, 40);
        }

        $console = [];
        try {
            $details = app(DaemonServerRepository::class)->setServer($server)->getDetails();
            $facts[] = 'State: ' . self::line((string) ($details['state'] ?? 'unknown'), 30);
            $usage = is_array($details['utilization'] ?? null) ? $details['utilization'] : [];
            if (isset($usage['memory_bytes']) && (int) $server->memory > 0) {
                $facts[] = 'RAM usage: ' . self::percent((float) $usage['memory_bytes'], (int) $server->memory * 1024 * 1024) . ' %';
            }
            if (isset($usage['cpu_absolute'])) {
                $facts[] = 'CPU usage: ' . round((float) $usage['cpu_absolute'], 1) . ' %';
            }
            if (isset($usage['disk_bytes']) && (int) $server->disk > 0) {
                $facts[] = 'Disk used: ' . self::percent((float) $usage['disk_bytes'], (int) $server->disk * 1024 * 1024) . ' %';
            }
            $console = self::console($server, $maxLines);
        } catch (\Throwable $e) {
            Log::info('techtyl assistant: Server-Daten von Wings nicht abrufbar', ['server' => $server->uuidShort, 'error' => mb_substr($e->getMessage(), 0, 200)]);
            $facts[] = 'State and console: not available right now (the node does not respond).';
        }

        // Juengste Zeilen behalten, bis die Zeichengrenze erreicht ist.
        $kept = [];
        $budget = $maxChars;
        foreach (array_reverse($console) as $row) {
            $row = mb_substr(Redactor::mask($row), 0, 600);
            $budget -= mb_strlen($row) + 1;
            if ($budget < 0) {
                break;
            }
            $kept[] = $row;
        }
        $kept = array_reverse($kept);

        $text = Redactor::mask(implode("\n", $facts));
        $text .= $kept === []
            ? "\nConsole: no lines available."
            : "\nConsole (last " . count($kept) . " lines, oldest first):\n" . implode("\n", $kept);

        // Der Inhalt darf den Datenblock nicht vorzeitig beenden oder einen neuen vortaeuschen.
        $text = str_replace(['[[', ']]'], ['[ [', '] ]'], $text);

        return ['text' => $text, 'lines' => count($kept)];
    }

    /** Verpackt den Datenblock mit einer zufaelligen Kennung, die im Inhalt nicht vorkommen kann. */
    public static function wrap(string $text): string
    {
        $id = bin2hex(random_bytes(4));

        return '[[' . self::MARKER . ' ' . $id . " BEGIN]]\n" . $text . "\n[[" . self::MARKER . ' ' . $id . ' END]]';
    }

    /** @return string[] */
    private static function console(Server $server, int $lines): array
    {
        $repository = new class (app()) extends DaemonRepository {
            /** @return string[] */
            public function logs(int $size): array
            {
                $response = $this->getHttpClient()->get(sprintf('/api/servers/%s/logs', $this->server->uuid), ['query' => ['size' => $size]]);
                $decoded = json_decode($response->getBody()->__toString(), true);

                return is_array($decoded['data'] ?? null) ? array_values(array_filter($decoded['data'], 'is_string')) : [];
            }
        };

        // Wings liefert hoechstens 100 Zeilen.
        return array_slice($repository->setServer($server)->logs(min(100, $lines)), -$lines);
    }

    private static function line(string $value, int $max): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $value)), 0, $max);
    }

    private static function limit(int $value, string $unit): string
    {
        return $value > 0 ? $value . ' ' . $unit : 'unlimited ' . $unit;
    }

    private static function percent(float $used, float $total): string
    {
        return (string) round($used / max(1.0, $total) * 100);
    }
}
