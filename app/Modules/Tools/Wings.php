<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\BadResponseException;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Gekapselter Zugriff auf die Dateien und den Zustand EINES Servers ueber Wings.
 * Jeder Pfad laeuft durch path()/filename(): kein "..", keine Backslashes, keine Steuerzeichen.
 * Verbindungsfehler werden zu ToolsException::daemon() (Details nur im Log).
 *
 * Grenzen von Wings, die hier beruecksichtigt sind: hoechstens 3 gleichzeitige Downloads je
 * Server, Downloads laufen im Hintergrund (Fortschritt ueber downloads()), keine Datei-Hashes
 * (deshalb hash(): Datei wird gestreamt und im Panel gehasht, ohne sie in den Speicher zu laden).
 */
class Wings
{
    public const MAX_PULLS = 3;

    public function __construct(private Server $server)
    {
    }

    public static function for(Server $server): self
    {
        return new self($server);
    }

    /**
     * Normalisiert einen Pfad relativ zum Server-Verzeichnis ("/plugins", "/plugins/a.jar").
     * Wirft bei "..", Backslash, Steuerzeichen oder ueberlangen Pfaden.
     */
    public static function path(string $path): string
    {
        if (strlen($path) > 1024 || preg_match('/[\x00-\x1f\x7f\\\\]/', $path)) {
            throw ToolsException::invalid('path_invalid');
        }
        $clean = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                throw ToolsException::invalid('path_invalid');
            }
            $clean[] = $segment;
        }

        return '/' . implode('/', $clean);
    }

    /** Ein einzelner Dateiname ohne Verzeichnisanteil. */
    public static function filename(string $name): string
    {
        if ($name === '' || strlen($name) > 191 || $name === '.' || $name === '..'
            || preg_match('/[\x00-\x1f\x7f\\\\\/]/', $name)) {
            throw ToolsException::invalid('filename_invalid');
        }

        return $name;
    }

    /**
     * Inhalt eines Verzeichnisses; ein fehlendes Verzeichnis ergibt eine leere Liste.
     *
     * @return array<int, array{name: string, size: int, file: bool, modified: string}>
     */
    public function list(string $directory): array
    {
        $directory = self::path($directory);
        try {
            $entries = $this->files()->getDirectory($directory);
        } catch (DaemonConnectionException $e) {
            // Wings answers 404 or 500 for a folder that does not exist. To tell that apart from a
            // real failure, look for the folder in its parent.
            if ($directory !== '/' && in_array($e->getStatusCode(), [404, 500], true)) {
                $cut = strrpos($directory, '/');
                $name = substr($directory, $cut + 1);
                $exists = false;
                foreach ($this->list($cut === 0 ? '/' : substr($directory, 0, $cut)) as $entry) {
                    if ($entry['name'] === $name && !$entry['file']) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    return [];
                }
            }
            throw $this->failed('Verzeichnis lesen', $e);
        }

        $list = [];
        foreach (is_array($entries) ? $entries : [] as $entry) {
            if (!is_array($entry) || !is_string($entry['name'] ?? null)) {
                continue;
            }
            $list[] = [
                'name' => $entry['name'],
                'size' => (int) ($entry['size'] ?? 0),
                'file' => (bool) ($entry['file'] ?? false),
                'modified' => (string) ($entry['modified'] ?? ''),
            ];
        }

        return $list;
    }

    /** @return array{name: string, size: int, file: bool, modified: string}|null */
    public function stat(string $directory, string $name): ?array
    {
        $name = self::filename($name);
        foreach ($this->list($directory) as $entry) {
            if ($entry['name'] === $name) {
                return $entry;
            }
        }

        return null;
    }

    public function ensureDirectory(string $directory): void
    {
        $directory = self::path($directory);
        if ($directory === '/') {
            return;
        }
        $cut = strrpos($directory, '/');
        $parent = $cut === 0 ? '/' : substr($directory, 0, $cut);
        $name = substr($directory, $cut + 1);
        foreach ($this->list($parent) as $entry) {
            if ($entry['name'] === $name) {
                if ($entry['file']) {
                    throw ToolsException::conflict('not_a_folder', ['name' => $name]);
                }

                return;
            }
        }
        try {
            $this->files()->createDirectory($name, $parent);
        } catch (DaemonConnectionException $e) {
            throw $this->failed('Ordner anlegen', $e);
        }
    }

    /**
     * Startet einen Download im Hintergrund und liefert dessen Kennung. Die Adresse muss vorher
     * durch ExternalHttp::downloadUrl() gegangen sein (wird hier noch einmal geprueft).
     */
    public function pull(string $url, string $directory, string $filename): string
    {
        $url = ExternalHttp::downloadUrl($url);
        $directory = self::path($directory);
        $filename = self::filename($filename);
        try {
            $response = $this->files()->pull($url, $directory, ['filename' => $filename, 'use_header' => false, 'foreground' => false]);
        } catch (DaemonConnectionException $e) {
            throw $this->failed('Download starten', $e);
        }
        $data = json_decode((string) $response->getBody(), true);
        $identifier = is_array($data) ? ($data['identifier'] ?? null) : null;
        if (!is_string($identifier) || $identifier === '') {
            Log::warning('techtyl tools: Wings lieferte keine Download-Kennung', ['server' => $this->server->uuid]);
            throw ToolsException::daemon();
        }

        return $identifier;
    }

    /** @return array<string, int> Laufende Downloads: Kennung => Fortschritt in Prozent. */
    public function downloads(): array
    {
        $data = $this->request('GET', '/files/pull', [], 'Downloads lesen');
        $list = [];
        foreach (is_array($data['downloads'] ?? null) ? $data['downloads'] : [] as $download) {
            $id = $download['Identifier'] ?? $download['identifier'] ?? null;
            if (is_string($id)) {
                $progress = (float) ($download['Progress'] ?? $download['progress'] ?? 0);
                $list[$id] = (int) max(0, min(100, round($progress * 100)));
            }
        }

        return $list;
    }

    public function cancelDownload(string $identifier): void
    {
        if (!preg_match('/^[A-Za-z0-9-]{8,64}$/', $identifier)) {
            return;
        }
        try {
            $this->request('DELETE', '/files/pull/' . $identifier, [], 'Download abbrechen');
        } catch (\Throwable $e) {
            // Der Download ist dann meist schon beendet.
        }
    }

    /** @param string[] $names Dateinamen im Verzeichnis */
    public function delete(string $directory, array $names): void
    {
        $directory = self::path($directory);
        $names = array_map([self::class, 'filename'], array_values($names));
        if ($names === []) {
            return;
        }
        try {
            $this->files()->deleteFiles($directory, $names);
        } catch (DaemonConnectionException $e) {
            throw $this->failed('Datei löschen', $e);
        }
    }

    /** Benennt eine Datei innerhalb eines Verzeichnisses um (Ziel darf noch nicht existieren). */
    public function rename(string $directory, string $from, string $to): void
    {
        $directory = self::path($directory);
        try {
            $this->files()->renameFiles($directory, [['from' => self::filename($from), 'to' => self::filename($to)]]);
        } catch (DaemonConnectionException $e) {
            throw $this->failed('Datei umbenennen', $e);
        }
    }

    public function put(string $path, string $content): void
    {
        try {
            $this->files()->putContent(self::path($path), $content);
        } catch (DaemonConnectionException $e) {
            throw $this->failed('Datei schreiben', $e);
        }
    }

    /** Liest eine kleine Textdatei; null, wenn sie fehlt oder groesser als $maxBytes ist. */
    public function read(string $path, int $maxBytes = 262144): ?string
    {
        try {
            return $this->files()->getContent(self::path($path), $maxBytes);
        } catch (DaemonConnectionException $e) {
            if (in_array($e->getStatusCode(), [400, 404], true)) {
                return null;
            }
            throw $this->failed('Datei lesen', $e);
        } catch (\Throwable $e) {
            // FileSizeTooLargeException und Aehnliches: Datei gilt als nicht lesbar.
            return null;
        }
    }

    /**
     * Pruefsumme einer Datei auf dem Server. Wings kennt keine Hashes, deshalb wird die Datei
     * in Stuecken gelesen und hier gehasht. Null, wenn die Datei fehlt oder zu gross ist.
     */
    public function hash(string $path, string $algorithm, int $maxBytes = 268435456): ?string
    {
        if (!in_array($algorithm, ['sha1', 'sha256', 'md5'], true)) {
            throw new \InvalidArgumentException('Unbekanntes Hash-Verfahren.');
        }
        try {
            $response = $this->files()->getHttpClient()->get(
                sprintf('/api/servers/%s/files/contents', $this->server->uuid),
                ['query' => ['file' => self::path($path)], 'stream' => true, 'timeout' => 120, 'read_timeout' => 30]
            );
            $context = hash_init($algorithm);
            $stream = $response->getBody();
            $total = 0;
            while (!$stream->eof()) {
                $chunk = $stream->read(1048576);
                $total += strlen($chunk);
                if ($total > $maxBytes) {
                    $stream->close();

                    return null;
                }
                hash_update($context, $chunk);
            }

            return hash_final($context);
        } catch (BadResponseException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return null;
            }
            throw $this->failed('Prüfsumme bilden', $e);
        } catch (GuzzleException $e) {
            throw $this->failed('Prüfsumme bilden', $e);
        }
    }

    /** Zustand laut Wings: offline, starting, running, stopping. */
    public function state(): string
    {
        try {
            $details = app(DaemonServerRepository::class)->setServer($this->server)->getDetails();
        } catch (DaemonConnectionException $e) {
            throw $this->failed('Zustand lesen', $e);
        }

        return is_string($details['state'] ?? null) ? $details['state'] : 'offline';
    }

    /** start, stop, restart oder kill. */
    public function power(string $action): void
    {
        if (!in_array($action, ['start', 'stop', 'restart', 'kill'], true)) {
            throw new \InvalidArgumentException('Unbekannte Aktion.');
        }
        try {
            app(DaemonPowerRepository::class)->setServer($this->server)->send($action);
        } catch (DaemonConnectionException $e) {
            throw $this->failed('Server schalten', $e);
        }
    }

    private function files(): DaemonFileRepository
    {
        return app(DaemonFileRepository::class)->setServer($this->server);
    }

    private function request(string $method, string $path, array $options, string $what): array
    {
        try {
            $response = $this->files()->getHttpClient()->request($method, sprintf('/api/servers/%s', $this->server->uuid) . $path, $options);
        } catch (GuzzleException $e) {
            throw $this->failed($what, $e);
        }
        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    private function failed(string $what, \Throwable $e): ToolsException
    {
        Log::warning('techtyl tools: Wings-Aufruf fehlgeschlagen (' . $what . ')', [
            'server' => $this->server->uuid,
            'error' => substr($e->getMessage(), 0, 500),
        ]);

        return ToolsException::daemon();
    }
}
