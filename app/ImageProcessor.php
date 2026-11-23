<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier};

/**
 * Prueft ein hochgeladenes Bild und kodiert es neu. Gespeichert wird nie die Originaldatei,
 * sondern nur das Ergebnis dieser Klasse: Pixel werden in eine neue Flaeche kopiert und neu
 * geschrieben, Metadaten und angehaengte Nutzlast gehen dabei verloren.
 */
class ImageProcessor
{
    /** Groesste Ausgabemasse je Verwendungszweck (Breite, Hoehe). Groessere Bilder werden verkleinert. */
    public const KINDS = [
        'background' => [2560, 1440],
        'banner' => [1920, 1080],
    ];

    public const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public const MIN_SIDE = 16;
    public const MAX_SIDE = 8000;
    public const MAX_PIXELS = 25000000;

    /**
     * Welche der erlaubten Formate dieser Server lesen kann (haengt davon ab, wie GD gebaut wurde).
     *
     * @return array<string, bool>
     */
    public static function readable(): array
    {
        return [
            'image/jpeg' => function_exists('imagecreatefromjpeg'),
            'image/png' => function_exists('imagecreatefrompng'),
            'image/webp' => function_exists('imagecreatefromwebp'),
        ];
    }

    /** Format, in dem gespeichert wird: [Dateiendung, MIME-Typ]. */
    public static function outputFormat(): array
    {
        if (function_exists('imagewebp')) {
            return ['webp', 'image/webp'];
        }
        if (function_exists('imagejpeg')) {
            return ['jpg', 'image/jpeg'];
        }

        return ['png', 'image/png'];
    }

    /**
     * @return array{data: string, ext: string, mime: string, width: int, height: int}
     *
     * @throws ImageRejected
     */
    public static function process(string $path, string $kind): array
    {
        if (!isset(self::KINDS[$kind]) || !function_exists('imagecreatetruecolor')) {
            throw new ImageRejected(I18n::t('core.image_unsupported'));
        }
        if (!is_file($path) || !is_readable($path)) {
            throw new ImageRejected(I18n::t('core.image_unreadable'));
        }

        $type = self::detect($path);
        if ($type === null) {
            throw new ImageRejected(I18n::t('core.image_type'));
        }

        $info = @getimagesize($path);
        if ($info === false || image_type_to_mime_type($info[2]) !== $type) {
            throw new ImageRejected(I18n::t('core.image_invalid'));
        }
        [$width, $height] = [(int) $info[0], (int) $info[1]];
        if ($width < self::MIN_SIDE || $height < self::MIN_SIDE) {
            throw new ImageRejected(I18n::t('core.image_small', ['size' => self::MIN_SIDE]));
        }
        if ($width > self::MAX_SIDE || $height > self::MAX_SIDE || $width * $height > self::MAX_PIXELS) {
            throw new ImageRejected(I18n::t('core.image_pixels', ['side' => self::MAX_SIDE]));
        }
        if (!self::readable()[$type]) {
            throw new ImageRejected(I18n::t('core.image_format'));
        }

        [$maxWidth, $maxHeight] = self::KINDS[$kind];
        $scale = min(1, $maxWidth / $width, $maxHeight / $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        // GD haelt das Bild unkomprimiert im Speicher; vorher pruefen, statt den Prozess abstuerzen zu lassen.
        if (!self::reserveMemory(($width * $height + 2 * $targetWidth * $targetHeight) * 5 + 16 * 1024 * 1024)) {
            throw new ImageRejected(I18n::t('core.image_server'));
        }

        $source = match ($type) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
        };
        if (!$source instanceof \GdImage) {
            throw new ImageRejected(I18n::t('core.image_invalid'));
        }

        try {
            if ($type === 'image/jpeg') {
                $source = self::upright($source, $path);
                if (imagesx($source) !== $width) {
                    [$targetWidth, $targetHeight] = [$targetHeight, $targetWidth];
                }
            }

            [$ext, $mime] = self::outputFormat();
            $target = imagecreatetruecolor($targetWidth, $targetHeight);
            if (!$target instanceof \GdImage) {
                throw new ImageRejected(I18n::t('core.image_process'));
            }

            if ($ext === 'jpg') {
                // JPEG kennt keine Transparenz: auf dunklem Grund zusammenfuehren.
                imagefill($target, 0, 0, imagecolorallocate($target, 16, 18, 20));
                imagealphablending($target, true);
            } else {
                imagealphablending($target, false);
                imagesavealpha($target, true);
                imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
            }
            imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, imagesx($source), imagesy($source));

            ob_start();
            try {
                $written = match ($ext) {
                    'webp' => imagewebp($target, null, 82),
                    'jpg' => imagejpeg($target, null, 84),
                    default => imagepng($target, null, 9),
                };
            } finally {
                $data = (string) ob_get_clean();
            }
            if (!$written || $data === '') {
                throw new ImageRejected(I18n::t('core.image_process'));
            }

            return ['data' => $data, 'ext' => $ext, 'mime' => $mime, 'width' => $targetWidth, 'height' => $targetHeight];
        } finally {
            unset($source, $target);
        }
    }

    /**
     * Bestimmt den Typ aus den ersten Bytes UND ueber fileinfo; beide muessen uebereinstimmen.
     * Dateiname und der vom Browser gemeldete Typ spielen keine Rolle.
     */
    public static function detect(string $path): ?string
    {
        $head = (string) @file_get_contents($path, false, null, 0, 16);
        $magic = match (true) {
            str_starts_with($head, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($head, "\x89PNG\r\n\x1A\n") => 'image/png',
            strlen($head) >= 12 && substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP' => 'image/webp',
            default => null,
        };
        if ($magic === null) {
            return null;
        }
        $sniffed = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        return $sniffed === $magic ? $magic : null;
    }

    /** Dreht ein JPEG nach seiner EXIF-Ausrichtung, weil diese Angabe beim Neukodieren wegfaellt. */
    private static function upright(\GdImage $image, string $path): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        $angle = match (is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof \GdImage ? $rotated : $image;
    }

    /** Stellt sicher, dass genug Arbeitsspeicher frei ist, und hebt die Grenze dafuer bei Bedarf an. */
    private static function reserveMemory(int $bytes): bool
    {
        $needed = memory_get_usage() + $bytes;
        if (self::memoryLimit() >= $needed) {
            return true;
        }
        @ini_set('memory_limit', (string) min($needed, 768 * 1024 * 1024));

        return self::memoryLimit() >= $needed;
    }

    private static function memoryLimit(): int
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return PHP_INT_MAX;
        }
        $value = (int) $raw;

        return match (strtolower(substr($raw, -1))) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
