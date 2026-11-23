<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tickets;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\ImageRejected;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\ImageProcessor;

/**
 * Bildanhaenge von Tickets. Jedes Bild laeuft durch die gepruefte Bild-Pipeline (Typ aus dem
 * Inhalt bestimmt, neu kodiert, Metadaten entfernt) und liegt danach in einem EIGENEN Ordner
 * ausserhalb des Webroots.
 *
 * Bewusst getrennt von der allgemeinen Bildablage (Media): deren Auslieferung ist oeffentlich und
 * nur durch nicht erratbare Namen geschuetzt. Fuer Ticket-Anhaenge (Screenshots mit Zugangsdaten,
 * Rechnungen) reicht das nicht - eine weitergegebene oder im Verlauf gespeicherte Adresse bliebe
 * fuer jeden abrufbar. Anhaenge werden deshalb nur ueber eine Route ausgeliefert, die Anmeldung
 * und Zugehoerigkeit zum Ticket prueft. Ausserdem wuerde Media::prune() fremde Dateien loeschen.
 */
class Attachments
{
    public const STORAGE_PATH = '.extensions/{identifier}/tickets';
    public const MAX_PER_MESSAGE = 3;

    /** Groesste Ausgabemasse wie bei Bannern (1920 x 1080). */
    private const KIND = 'banner';

    private const TYPES = ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'png' => 'image/png'];
    private const NAME_PATTERN = '/^[a-f0-9]{40}\.(webp|jpg|png)$/';

    public static function disk(): Filesystem
    {
        return Storage::build([
            'driver' => 'local',
            'root' => storage_path(self::STORAGE_PATH),
            'visibility' => 'private',
            'throw' => false,
        ]);
    }

    /**
     * Prueft und speichert die hochgeladenen Bilder einer Nachricht.
     *
     * @param array<int, UploadedFile> $files
     * @return array<int, array{file: string, width: int, height: int, size: int}>
     *
     * @throws ValidationException
     */
    public static function store(array $files): array
    {
        $stored = [];
        try {
            foreach ($files as $file) {
                $image = ImageProcessor::process((string) $file->getRealPath(), self::KIND);
                $name = bin2hex(random_bytes(20)) . '.' . $image['ext'];
                if (!self::disk()->put($name, $image['data'])) {
                    throw new \RuntimeException('Schreiben nach storage/' . self::STORAGE_PATH . ' fehlgeschlagen.');
                }
                $stored[] = [
                    'file' => $name,
                    'width' => $image['width'],
                    'height' => $image['height'],
                    'size' => strlen($image['data']),
                ];
            }
        } catch (ImageRejected $e) {
            self::discard($stored);
            throw ValidationException::withMessages(['images' => [$e->getMessage()]]);
        } catch (\Throwable $e) {
            self::discard($stored);
            Log::error('techtyl tickets: Anhang konnte nicht gespeichert werden', ['error' => $e->getMessage()]);
            throw new HttpException(500, I18n::t('tickets.v_attachment_store'));
        }

        return $stored;
    }

    /** @param array<int, array{file: string}> $stored */
    public static function discard(array $stored): void
    {
        foreach ($stored as $entry) {
            self::delete($entry['file']);
        }
    }

    public static function delete(mixed $name): void
    {
        if (!is_string($name) || preg_match(self::NAME_PATTERN, $name) !== 1) {
            return;
        }
        try {
            self::disk()->delete($name);
        } catch (\Throwable $e) {
            Log::warning('techtyl tickets: Anhang konnte nicht geloescht werden', ['error' => $e->getMessage()]);
        }
    }

    /** @return array{path: string, mime: string}|null */
    public static function locate(mixed $name): ?array
    {
        if (!is_string($name) || preg_match(self::NAME_PATTERN, $name) !== 1 || !self::disk()->exists($name)) {
            return null;
        }

        return [
            'path' => self::disk()->path($name),
            'mime' => self::TYPES[pathinfo($name, PATHINFO_EXTENSION)],
        ];
    }

    /**
     * Loescht Dateien ohne Eintrag in der Tabelle (z. B. nachdem ein Nutzer samt Tickets geloescht
     * wurde). Frische Dateien bleiben liegen, damit ein laufender Upload nicht getroffen wird.
     */
    public static function prune(): int
    {
        $used = array_flip(DB::table(Tickets::ATTACHMENTS)->pluck('file')->all());
        $removed = 0;
        foreach (self::disk()->files() as $relative) {
            $name = basename($relative);
            if (isset($used[$name]) || self::disk()->lastModified($relative) > time() - 3600) {
                continue;
            }
            self::disk()->delete($relative);
            ++$removed;
        }

        return $removed;
    }
}
