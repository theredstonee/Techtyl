<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

/**
 * Gemeinsamer HTTPS-Aufruf aller Treiber: POST mit JSON, Antwort als Server-Sent Events.
 * Keine Weiterleitungen, feste Zeitgrenzen, Fehlertexte ohne Schluessel.
 */
class Transport
{
    private const CONNECT_TIMEOUT = 10;
    private const READ_STEP = 3;
    private const ERROR_BYTES = 4096;

    /**
     * @param int $timeout Sekunden, die der Anbieter hoechstens schweigen darf
     * @param string[] $secrets Zeichenketten, die nie in Fehlertexten stehen duerfen
     */
    public function __construct(private int $timeout, private array $secrets = [])
    {
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $body
     * @param callable(string, array<string, mixed>): bool $onEvent Ereignisname und JSON-Daten; false beendet das Lesen
     * @param callable(): bool $onIdle false beendet das Lesen
     * @return bool false, wenn das Lesen abgebrochen wurde
     * @throws ProviderException
     */
    public function stream(string $url, array $headers, array $body, callable $onEvent, callable $onIdle): bool
    {
        $started = microtime(true);
        try {
            $response = Http::withHeaders($headers + ['Accept' => 'text/event-stream'])
                ->withOptions([
                    'stream' => true,
                    'allow_redirects' => false,
                    'read_timeout' => self::READ_STEP,
                    'http_errors' => false,
                    // Unveraendert lesen: Der Datenstrom wird unten direkt als PHP-Ressource gelesen.
                    'decode_content' => false,
                ])
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->timeout($this->timeout)
                ->post($url, $body);
        } catch (ConnectionException $e) {
            $detail = $this->scrub($e->getMessage());
            // Je nach HTTP-Unterbau heisst eine Zeitueberschreitung nur "Connection refused"; die Dauer verraet sie.
            $slow = microtime(true) - $started >= $this->timeout - 1 || stripos($detail, 'timed out') !== false;
            throw new ProviderException($slow ? ProviderException::TIMEOUT : ProviderException::NETWORK, $detail);
        } catch (\Throwable $e) {
            throw new ProviderException(ProviderException::NETWORK, $this->scrub($e->getMessage()));
        }

        $stream = $response->toPsrResponse()->getBody();
        $status = $response->status();
        if ($status < 200 || $status >= 300) {
            $raw = '';
            try {
                $raw = $stream->read(self::ERROR_BYTES);
                $stream->close();
            } catch (\Throwable $e) {
                // Ohne lesbaren Fehlertext reicht der Statuscode.
            }
            throw ProviderException::fromStatus($status, $this->scrub(self::errorDetail($raw)));
        }

        // Die rohe Ressource lesen: So laesst sich eine Lesepause (der Anbieter denkt nach) von einem
        // Fehler unterscheiden, und waehrend der Pause kann der Abbruch durch den Nutzer geprueft werden.
        $handle = $stream->detach();
        if (!is_resource($handle)) {
            throw new ProviderException(ProviderException::NETWORK, 'kein lesbarer Datenstrom');
        }
        @stream_set_timeout($handle, self::READ_STEP);

        $buffer = '';
        $event = '';
        $data = [];
        $events = 0;
        $all = '';
        $last = microtime(true);
        $deadline = $last + $this->timeout * 3;
        $continue = true;

        try {
            while ($continue && !feof($handle)) {
                $chunk = @fread($handle, 4096);
                $now = microtime(true);
                if ($chunk === false || $chunk === '') {
                    if (feof($handle)) {
                        break;
                    }
                    if ($now - $last > $this->timeout || $now > $deadline) {
                        throw new ProviderException(ProviderException::TIMEOUT, 'keine Daten innerhalb der Zeitgrenze');
                    }
                    if (empty(stream_get_meta_data($handle)['timed_out'])) {
                        if ($chunk === false) {
                            throw new ProviderException(ProviderException::NETWORK, 'Lesefehler im Datenstrom');
                        }
                        usleep(50000);
                    }
                    $continue = $onIdle();
                    continue;
                }
                if ($now > $deadline) {
                    throw new ProviderException(ProviderException::TIMEOUT, 'Antwort ueberschreitet die Gesamtzeit');
                }
                $last = $now;
                $buffer .= $chunk;
                if ($events === 0 && strlen($all) < 262144) {
                    $all .= $chunk;
                }

                while ($continue && ($end = strpos($buffer, "\n")) !== false) {
                    $line = rtrim(substr($buffer, 0, $end), "\r");
                    $buffer = substr($buffer, $end + 1);
                    if ($line === '') {
                        if ($data !== []) {
                            $events++;
                            $continue = $this->dispatch($event, implode("\n", $data), $onEvent);
                        }
                        $event = '';
                        $data = [];
                    } elseif (str_starts_with($line, 'data:')) {
                        $data[] = ltrim(substr($line, 5), ' ');
                    } elseif (str_starts_with($line, 'event:')) {
                        $event = trim(substr($line, 6));
                    }
                    // Zeilen mit ":" am Anfang sind Kommentare (Lebenszeichen) und werden uebergangen.
                }
            }
            if ($continue && $data !== []) {
                $events++;
                $continue = $this->dispatch($event, implode("\n", $data), $onEvent);
            }
            // Server ohne Streaming antworten mit einem einzelnen JSON-Dokument.
            if ($continue && $events === 0) {
                $decoded = json_decode(trim($all), true);
                if (!is_array($decoded)) {
                    throw new ProviderException(ProviderException::UNAVAILABLE, 'Antwort ist weder ein Ereignisstrom noch JSON');
                }
                $continue = $onEvent('', $decoded);
            }
        } catch (ProviderException $e) {
            throw new ProviderException($e->kind, $this->scrub($e->getMessage()), $e->status);
        } catch (\Throwable $e) {
            throw new ProviderException(ProviderException::NETWORK, $this->scrub($e->getMessage()));
        } finally {
            // Schliesst die Verbindung; bei einem Abbruch hoert der Anbieter damit auf zu erzeugen.
            if (is_resource($handle)) {
                @fclose($handle);
            }
        }

        return $continue;
    }

    /** @param callable(string, array<string, mixed>): bool $onEvent */
    private function dispatch(string $event, string $data, callable $onEvent): bool
    {
        if ($data === '[DONE]') {
            return true;
        }
        $decoded = json_decode($data, true);

        return is_array($decoded) ? $onEvent($event, $decoded) : true;
    }

    /** Liest die Fehlermeldung aus den ueblichen Fehlerformaten der Anbieter. */
    public static function errorDetail(string $raw): string
    {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && array_is_list($decoded) && is_array($decoded[0] ?? null)) {
            $decoded = $decoded[0];
        }
        if (!is_array($decoded)) {
            return mb_substr($raw, 0, 200);
        }
        $error = $decoded['error'] ?? $decoded;
        if (is_string($error)) {
            return $error;
        }
        $parts = [];
        foreach (['type', 'code', 'status', 'message', 'detail'] as $key) {
            if (isset($error[$key]) && is_scalar($error[$key])) {
                $parts[] = (string) $error[$key];
            }
        }

        return implode(' | ', $parts);
    }

    /** Entfernt Schluessel und Steuerzeichen und kuerzt den Text fuers Log. */
    public function scrub(string $text): string
    {
        foreach ($this->secrets as $secret) {
            if ($secret !== '') {
                $text = str_replace($secret, '[schluessel]', $text);
            }
        }
        $text = preg_replace('/[\x00-\x1f\x7f]+/', ' ', mb_scrub($text)) ?? '';

        return mb_substr($text, 0, 300);
    }
}
