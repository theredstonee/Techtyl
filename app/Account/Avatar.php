<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Account;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\ImageRejected;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\ImageProcessor;

/**
 * Profilbilder. Fuer andere Teile von Techtyl (Tickets, Admin-Listen, Navigation):
 *
 *   Avatar::url(int $userId): ?string          Adresse des Bildes oder null (dann Initialen zeigen)
 *   Avatar::forUsers(array $ids): array        [userId => ?string] mit EINER Abfrage
 *   Avatar::describe(int $userId): array       ['url' => ?string, 'initials' => 'AB', 'color' => '#rrggbb']
 *   Avatar::describeMany(array $ids): array    dasselbe fuer viele Nutzer, eine Abfrage
 *   Avatar::html($user, int $px, string $class) fertiges HTML fuer Blade-Seiten (Bild oder Initialen)
 *
 * Die Bilder liegen in einem eigenen Ordner ausserhalb des Webroots (nicht in der allgemeinen
 * Bildablage: Media::prune() kennt diese Tabelle nicht und wuerde sie loeschen). Ausgeliefert
 * werden sie nur an angemeldete Nutzer ueber die Client-API (Sitzungs-Cookie reicht fuer <img>).
 */
class Avatar
{
    public const TABLE = 'techtyl_account_avatars';
    public const STORAGE_PATH = '.extensions/{identifier}/avatars';
    public const URL_PREFIX = '/api/client/extensions/{identifier}/account/avatars/';

    /** Kantenlaenge der gespeicherten Fassung in Pixeln. */
    public const SIZE = 256;

    /** Farben fuer Initialen; dunkle Schrift (INK) ist auf allen lesbar, in allen vier Varianten. */
    public const PALETTE = ['#f0a33b', '#5fc4b0', '#f0857a', '#9fd67e', '#b49cf0', '#6fb3ee', '#e7c66a', '#ee8fb4'];
    public const INK = '#10161b';

    private const TYPES = ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'png' => 'image/png'];
    private const NAME_PATTERN = '/^[a-f0-9]{40}\.(webp|jpg|png)$/';

    /** @var array<int, ?string> Bereits nachgeschlagene Adressen dieses Seitenaufrufs. */
    private static array $cache = [];

    public static function disk(): Filesystem
    {
        return Storage::build([
            'driver' => 'local',
            'root' => storage_path(self::STORAGE_PATH),
            'visibility' => 'private',
            'throw' => false,
        ]);
    }

    /** Adresse des Profilbildes eines Nutzers, null wenn er keins hat (dann Initialen anzeigen). */
    public static function url(int $userId): ?string
    {
        if (!array_key_exists($userId, self::$cache)) {
            self::forUsers([$userId]);
        }

        return self::$cache[$userId] ?? null;
    }

    /**
     * Adressen fuer mehrere Nutzer mit einer Abfrage.
     *
     * @param array<int, int|string> $ids
     * @return array<int, ?string> Nutzer-ID => Adresse oder null
     */
    public static function forUsers(array $ids): array
    {
        $ids = self::ids($ids);
        if ($ids === []) {
            return [];
        }
        $rows = self::rows($ids);
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = isset($rows[$id]) ? self::urlFromRow($rows[$id]) : null;
            self::$cache[$id] = $out[$id];
        }

        return $out;
    }

    /** @return array{url: ?string, initials: string, color: string} */
    public static function describe(int $userId): array
    {
        return self::describeMany([$userId])[$userId] ?? ['url' => null, 'initials' => '?', 'color' => self::PALETTE[0]];
    }

    /**
     * @param array<int, int|string> $ids
     * @return array<int, array{url: ?string, initials: string, color: string}>
     */
    public static function describeMany(array $ids): array
    {
        $ids = self::ids($ids);
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach (self::rows($ids) as $id => $row) {
            $url = self::urlFromRow($row);
            self::$cache[$id] = $url;
            $out[$id] = [
                'url' => $url,
                'initials' => self::initials((string) $row->name_first, (string) $row->name_last, (string) $row->username),
                'color' => self::color(self::seed((string) $row->name_first, (string) $row->name_last, (string) $row->username)),
            ];
        }

        return $out;
    }

    /** Laedt die Profilbilder einer Liste von Nutzern vorab (z. B. fuer eine Tabelle im Admin-Bereich). */
    public static function preload(iterable $users): void
    {
        $ids = [];
        foreach ($users as $user) {
            if (isset($user->id)) {
                $ids[] = (int) $user->id;
            }
        }
        try {
            self::forUsers($ids);
        } catch (\Throwable $e) {
            Log::warning('techtyl account: Profilbilder nicht lesbar', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Fertiges, escaptes HTML fuer Blade-Seiten: Kreis mit Initialen, darueber das Bild (falls vorhanden).
     * Faellt bei jedem Fehler still auf die Initialen zurueck, damit keine Admin-Seite daran scheitert.
     */
    public static function html(mixed $user, int $px = 20, string $class = ''): string
    {
        $px = max(12, min(160, $px));
        $first = (string) ($user->name_first ?? '');
        $last = (string) ($user->name_last ?? '');
        $username = (string) ($user->username ?? '');
        $url = null;
        try {
            $url = isset($user->id) ? self::url((int) $user->id) : null;
        } catch (\Throwable $e) {
            Log::warning('techtyl account: Profilbild nicht lesbar', ['error' => $e->getMessage()]);
        }

        $initials = self::initials($first, $last, $username);
        $style = sprintf(
            'position:relative;display:inline-flex;align-items:center;justify-content:center;width:%1$dpx;height:%1$dpx;'
            . 'border-radius:50%%;overflow:hidden;vertical-align:middle;background:%2$s;color:%3$s;'
            . 'font:600 %4$dpx/1 system-ui,sans-serif;letter-spacing:0;user-select:none;',
            $px,
            self::color(self::seed($first, $last, $username)),
            self::INK,
            max(7, (int) round($px * 0.4)),
        );
        $image = $url === null ? '' : sprintf(
            '<img src="%s" alt="" loading="lazy" style="position:absolute;inset:0;width:100%%;height:100%%;object-fit:cover;margin:0;border:0;border-radius:0;">',
            e($url),
        );

        return sprintf(
            '<span class="%s" style="%s" title="%s" aria-hidden="true">%s%s</span>',
            e(trim('techtyl-avatar ' . $class)),
            $style,
            e(trim($first . ' ' . $last) ?: $username),
            e($initials),
            $image,
        );
    }

    public static function initials(string $first, string $last, string $username = ''): string
    {
        $pick = fn (string $value) => mb_strtoupper(mb_substr(trim($value), 0, 1));
        $out = $pick($first) . $pick($last);
        if ($out === '') {
            $out = mb_strtoupper(mb_substr(trim($username), 0, 2));
        }

        return $out !== '' ? $out : '?';
    }

    /** Grundlage der Farbe: der Name, sonst der Benutzername (gleiche Regel wie im Browser). */
    public static function seed(string $first, string $last, string $username = ''): string
    {
        $name = mb_strtolower(trim(trim($first) . ' ' . trim($last)));

        return $name !== '' ? $name : mb_strtolower(trim($username));
    }

    /** Farbe aus der Palette per FNV-1a (32 Bit) ueber die UTF-8-Bytes; identisch zu Avatar.tsx. */
    public static function color(string $seed): string
    {
        $hash = 0x811C9DC5;
        $length = strlen($seed);
        for ($i = 0; $i < $length; ++$i) {
            $hash ^= ord($seed[$i]);
            $hash = ($hash * 0x01000193) & 0xFFFFFFFF;
        }

        return self::PALETTE[$hash % count(self::PALETTE)];
    }

    /* ------------------------------------------------------------------ Dateien */

    /**
     * Prueft das hochgeladene Bild mit der gemeinsamen Bild-Pipeline (Typ aus dem Inhalt, Pixel-
     * Grenzen, Neukodierung ohne Metadaten), schneidet es mittig quadratisch zu und speichert
     * eine 256 x 256 grosse Fassung. Gibt den Dateinamen zurueck.
     *
     * @throws ImageRejected
     */
    public static function store(string $path): string
    {
        // Schritt 1: vorhandene, gepruefte Pipeline (lehnt alles ab, was kein echtes JPEG/PNG/WebP ist).
        $clean = ImageProcessor::process($path, 'banner');

        // Schritt 2: das bereits neu kodierte Ergebnis quadratisch zuschneiden und verkleinern.
        $source = @imagecreatefromstring($clean['data']);
        if (!$source instanceof \GdImage) {
            throw new ImageRejected('Das Bild konnte nicht verarbeitet werden.');
        }
        try {
            $width = imagesx($source);
            $height = imagesy($source);
            $side = min($width, $height);
            $x = intdiv($width - $side, 2);
            $y = intdiv($height - $side, 2);

            [$ext] = ImageProcessor::outputFormat();
            $target = imagecreatetruecolor(self::SIZE, self::SIZE);
            if (!$target instanceof \GdImage) {
                throw new ImageRejected('Das Bild konnte nicht verarbeitet werden.');
            }
            if ($ext === 'jpg') {
                imagefill($target, 0, 0, imagecolorallocate($target, 16, 22, 27));
                imagealphablending($target, true);
            } else {
                imagealphablending($target, false);
                imagesavealpha($target, true);
                imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
            }
            imagecopyresampled($target, $source, 0, 0, $x, $y, self::SIZE, self::SIZE, $side, $side);

            ob_start();
            try {
                $written = match ($ext) {
                    'webp' => imagewebp($target, null, 86),
                    'jpg' => imagejpeg($target, null, 88),
                    default => imagepng($target, null, 9),
                };
            } finally {
                $data = (string) ob_get_clean();
            }
            if (!$written || $data === '') {
                throw new ImageRejected('Das Bild konnte nicht verarbeitet werden.');
            }
        } finally {
            unset($source, $target);
        }

        $name = bin2hex(random_bytes(20)) . '.' . $ext;
        if (!self::disk()->put($name, $data)) {
            throw new \RuntimeException('Schreiben nach storage/' . self::STORAGE_PATH . ' fehlgeschlagen.');
        }

        return $name;
    }

    /** @return array{path: string, mime: string}|null */
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

    public static function delete(mixed $name): void
    {
        if (!is_string($name) || preg_match(self::NAME_PATTERN, $name) !== 1) {
            return;
        }
        try {
            self::disk()->delete($name);
        } catch (\Throwable $e) {
            Log::warning('techtyl account: Profilbild konnte nicht geloescht werden', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Loescht Dateien ohne Eintrag in der Tabelle (z. B. nach dem Loeschen eines Nutzers, dessen
     * Zeile per Fremdschluessel wegfaellt). Frische Dateien bleiben, damit kein laufender Upload
     * getroffen wird.
     */
    public static function prune(): int
    {
        $used = array_flip(DB::table(self::TABLE)->pluck('file')->all());
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

    /* ------------------------------------------------------------------ intern */

    /**
     * @param array<int, int|string> $ids
     * @return array<int, int>
     */
    private static function ids(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            if ((is_int($id) || (is_string($id) && ctype_digit($id))) && (int) $id > 0) {
                $out[(int) $id] = (int) $id;
            }
        }

        return array_values($out);
    }

    /**
     * Eine Abfrage: Nutzer samt Profilbild (und E-Mail, falls Gravatar als Rueckfall erlaubt ist).
     *
     * @param array<int, int> $ids
     * @return array<int, object>
     */
    private static function rows(array $ids): array
    {
        $rows = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $found = DB::table('users')
                ->leftJoin(self::TABLE . ' as avatars', 'avatars.user_id', '=', 'users.id')
                ->whereIn('users.id', $chunk)
                ->get(['users.id', 'users.email', 'users.username', 'users.name_first', 'users.name_last', 'avatars.file']);
            foreach ($found as $row) {
                $rows[(int) $row->id] = $row;
            }
        }

        return $rows;
    }

    private static function urlFromRow(object $row): ?string
    {
        $file = $row->file ?? null;
        if (is_string($file) && preg_match(self::NAME_PATTERN, $file) === 1) {
            return self::URL_PREFIX . substr($file, 0, 40);
        }
        if (AccountSettings::get()['gravatar'] && is_string($row->email ?? null) && $row->email !== '') {
            // d=blank: ohne Gravatar-Bild kommt ein durchsichtiges Bild, darunter bleiben die Initialen sichtbar.
            return 'https://www.gravatar.com/avatar/' . md5(mb_strtolower(trim($row->email))) . '?s=' . self::SIZE . '&d=blank';
        }

        return null;
    }
}
