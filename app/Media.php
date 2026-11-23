<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier};

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Ablage der hochgeladenen Bilder. Sie liegen ausserhalb des Webroots unter
 * storage/.extensions/techtyl/uploads mit zufaelligem Namen und werden nur ueber die Route
 * /extensions/techtyl/media ausgeliefert.
 *
 * Die von Blueprint vorgesehenen Disks ("blueprint:techtyl", "blueprint_private:techtyl") werden
 * bewusst nicht verwendet: Blueprint mischt ihre Konfiguration bei jedem Start erneut in die
 * Laravel-Konfiguration; ist diese zwischengespeichert (config:cache), werden die Eintraege zu
 * Arrays und der Zugriff auf die Disk schlaegt fehl.
 */
class Media
{
    /** Ordner unterhalb von storage/, an der Stelle, die Blueprint fuer private Extension-Daten vorsieht. */
    public const STORAGE_PATH = '.extensions/{identifier}/uploads';
    public const URL_PREFIX = '/extensions/{identifier}/media/';

    public const TYPES = ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'png' => 'image/png'];

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

    public static function isName(mixed $name): bool
    {
        return is_string($name) && preg_match(self::NAME_PATTERN, $name) === 1;
    }

    /** Oeffentliche Adresse eines gespeicherten Bildes; leer, wenn es keins gibt. */
    public static function url(mixed $name): string
    {
        return self::isName($name) ? self::URL_PREFIX . substr($name, 0, 40) : '';
    }

    /**
     * Nimmt das Feld "image" einer Anfrage entgegen, prueft es und speichert die neu kodierte
     * Fassung. Gibt den Dateinamen zurueck.
     *
     * @throws ValidationException
     */
    public static function receive(Request $request, string $kind, int $maxKilobytes): string
    {
        $request->validate([
            'image' => ['required', 'file', 'max:' . $maxKilobytes, 'mimetypes:' . implode(',', ImageProcessor::MIME_TYPES)],
        ], [
            'image.required' => I18n::t('core.image_required'),
            'image.file' => I18n::t('core.image_required'),
            'image.uploaded' => I18n::t('core.image_upload'),
            'image.max' => I18n::t('core.image_max', ['size' => $maxKilobytes]),
            'image.mimetypes' => I18n::t('core.image_type'),
        ]);

        try {
            $image = ImageProcessor::process($request->file('image')->getRealPath(), $kind);
            $name = bin2hex(random_bytes(20)) . '.' . $image['ext'];
            if (!self::disk()->put($name, $image['data'])) {
                throw new \RuntimeException('Schreiben nach storage/' . self::STORAGE_PATH . ' fehlgeschlagen.');
            }

            return $name;
        } catch (ImageRejected $e) {
            throw ValidationException::withMessages(['image' => [$e->getMessage()]]);
        } catch (\Throwable $e) {
            Log::error('techtyl: Bild-Upload fehlgeschlagen', ['kind' => $kind, 'error' => $e->getMessage()]);
            throw new HttpException(500, I18n::t('core.image_store'));
        }
    }

    public static function delete(mixed $name): void
    {
        if (!self::isName($name)) {
            return;
        }
        try {
            self::disk()->delete($name);
        } catch (\Throwable $e) {
            Log::warning('techtyl: Bild konnte nicht geloescht werden', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Sucht die Datei zu einem Kennzeichen aus der Adresse (40 Hex-Zeichen).
     *
     * @return array{path: string, mime: string}|null
     */
    public static function find(string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{40}$/', $token) !== 1) {
            return null;
        }
        foreach (self::TYPES as $ext => $mime) {
            $relative = $token . '.' . $ext;
            if (self::disk()->exists($relative)) {
                return ['path' => self::disk()->path($relative), 'mime' => $mime];
            }
        }

        return null;
    }

    /**
     * Loescht Dateien, auf die nichts mehr verweist (z. B. nach dem Loeschen eines Servers oder
     * Nutzers). Frische Dateien bleiben liegen, damit ein gerade laufender Upload nicht getroffen wird.
     */
    public static function prune(): int
    {
        $used = array_flip(array_filter(array_merge(
            DB::table(Settings::USER_TABLE)->whereNotNull('background_file')->pluck('background_file')->all(),
            DB::table('techtyl_server_banners')->pluck('file')->all(),
            DB::table('techtyl_egg_banners')->pluck('file')->all(),
            [Settings::media()['background_file']],
        )));

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
