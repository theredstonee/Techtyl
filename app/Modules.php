<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier};

use Illuminate\Http\Request;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Client\BlueprintClientLibrary;

/**
 * Verzeichnis der Techtyl-Module. Ein Modul liegt in app/Modules/<Ordner>/ und kann mitbringen:
 *   routes.client.php  Routen unter /api/client/extensions/techtyl/<schluessel>/...
 *   routes.web.php     Routen unter /extensions/techtyl/<schluessel>/...
 *   Admin.php          Klasse mit data(): array und handle(Request, string $action): string
 * Die Admin-Ansicht eines Moduls liegt in views/admin/<schluessel>.blade.php.
 * Abgeschaltete Module sind ueber ihre Routen nicht erreichbar (404).
 */
class Modules
{
    public const RECORD = 'modules';

    /** Schluessel => [Ordner, Bezeichnung, Beschreibung] */
    public const ALL = [
        'tickets' => ['Tickets', 'Tickets', 'Nutzer eroeffnen Support-Tickets, das Team antwortet im Admin-Bereich.'],
        'billing' => ['Billing', 'Abrechnung', 'Server-Abos und aufladbares Guthaben ueber Stripe.'],
        'auth' => ['Auth', 'Registrierung und Anmeldung', 'Registrierung mit E-Mail-Bestaetigung, Turnstile, Einladungscodes, Discord und Google.'],
        'tools' => ['Tools', 'Server-Werkzeuge', 'Plugins, Mods, Modpacks, Versionen, Spieler, Welten, Eigenschaften und Logs.'],
        'assistant' => ['Assistant', 'KI-Assistent', 'Erklaert Konsole und Fehler, mit frei waehlbarem KI-Anbieter.'],
    ];

    /** @var array<string, bool>|null */
    private static ?array $states = null;

    public static function exists(string $key): bool
    {
        return isset(self::ALL[$key]);
    }

    /** @return array<string, bool> Alle Module sind nach der Installation aus. */
    public static function states(): array
    {
        if (self::$states === null) {
            $states = array_fill_keys(array_keys(self::ALL), false);
            try {
                $stored = app(BlueprintClientLibrary::class)->dbGet(Design::TABLE, self::RECORD, '{}');
                $decoded = is_string($stored) ? json_decode($stored, true) : null;
                if (is_array($decoded)) {
                    foreach ($states as $key => $unused) {
                        $states[$key] = ($decoded[$key] ?? false) === true;
                    }
                }
            } catch (\Throwable $e) {
                // Ohne lesbare Einstellungen bleiben alle Module aus.
            }
            self::$states = $states;
        }

        return self::$states;
    }

    public static function enabled(string $key): bool
    {
        return self::states()[$key] ?? false;
    }

    /**
     * @param array<string, mixed> $input Schluessel => Wahrheitswert aus dem Formular
     * @return string JSON zum Speichern
     */
    public static function encode(array $input): string
    {
        $states = [];
        foreach (array_keys(self::ALL) as $key) {
            $states[$key] = filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        }
        self::$states = null;

        return json_encode($states);
    }

    public static function directory(string $key): string
    {
        return base_path('app/BlueprintFramework/Extensions/{identifier}/Modules/' . self::ALL[$key][0]);
    }

    /** Voll qualifizierter Name der Admin-Klasse eines Moduls, falls vorhanden. */
    public static function adminClass(string $key): ?string
    {
        if (!self::exists($key)) {
            return null;
        }
        $class = __NAMESPACE__ . '\\Modules\\' . self::ALL[$key][0] . '\\Admin';

        return class_exists($class) ? $class : null;
    }

    /** @return array<string, mixed> Daten fuer die Admin-Ansicht des Moduls. */
    public static function adminData(string $key): array
    {
        $class = self::adminClass($key);

        return $class !== null ? (array) $class::data() : [];
    }

    /** Fuehrt eine Admin-Aktion des Moduls aus und liefert die Erfolgsmeldung. */
    public static function adminHandle(string $key, Request $request, string $action): string
    {
        $class = self::adminClass($key);
        abort_if($class === null, 404);

        return (string) $class::handle($request, $action);
    }

    /** Bindet die Routen-Dateien aller Module ein; $kind ist "client" oder "web". */
    public static function routes(string $kind): void
    {
        foreach (array_keys(self::ALL) as $key) {
            $file = self::directory($key) . '/routes.' . $kind . '.php';
            if (is_file($file)) {
                \Illuminate\Support\Facades\Route::prefix('/' . $key)
                    ->middleware(Http\ModuleEnabled::class . ':' . $key)
                    ->group($file);
            }
        }
    }
}
