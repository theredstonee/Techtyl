<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Designer;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use Pterodactyl\Services\Helpers\SoftwareVersionService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Media;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Design;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Limits;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Settings;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\ImageProcessor;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Client\BlueprintClientLibrary;

/**
 * Vollbild-Designer fuer Root-Admins unter /extensions/techtyl/designer.
 * Die Routen laufen im Web-Router (Sitzung + CSRF); RootAdminOnly laesst nur Root-Admins durch.
 * Gespeichert wird alles in einem Schritt: Design, Nutzer-Grenzen, Ankuendigung und Links.
 */
class DesignerController extends Controller
{
    public const ADMIN_URL = '/admin/extensions/{identifier}';
    private const ASSET_DIR = 'extensions/{identifier}/designer';
    private const PREVIEW_PAGES = ['dashboard', 'console', 'login', 'admin'];

    public function __construct(private BlueprintClientLibrary $blueprint)
    {
    }

    /** Die Designer-Seite. Strenge CSP: nur eigene Skripte/Stile, Vorschau nur von hier. */
    public function page(Request $request): Response
    {
        $html = view('blueprint.extensions.{identifier}.designer.page', [
            'state' => $this->state(),
            'assetVersion' => $this->assetVersion(),
            'adminUrl' => self::ADMIN_URL,
            'locale' => I18n::locale(),
        ])->render();

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => implode('; ', [
                "default-src 'none'",
                "script-src 'self'",
                "style-src 'self'",
                "font-src 'self'",
                "img-src 'self' data: https:",
                "connect-src 'self'",
                "frame-src 'self'",
                "form-action 'self'",
                "base-uri 'none'",
                "frame-ancestors 'none'",
                "object-src 'none'",
            ]),
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** Speichert den kompletten Stand des Designers. */
    public function save(Request $request): JsonResponse
    {
        $request->validate(array_merge(
            [
                'design' => ['required', 'array'],
                'limits' => ['required', 'array'],
                'announcement' => ['required', 'array'],
                'links' => ['required', 'array'],
            ],
            Design::adminRules('design.'),
            [
                'limits.user_background' => ['required', 'boolean'],
                'limits.user_banners' => ['required', 'boolean'],
                'limits.user_appearance' => ['required', 'boolean'],
                'limits.user_layout' => ['required', 'boolean'],
                'limits.max_upload_kb' => ['required', 'integer', 'min:' . Limits::MIN_UPLOAD_KB, 'max:' . Limits::MAX_UPLOAD_KB],
            ],
            Announcement::rules('announcement.'),
            Links::rules('links.'),
        ), [
            'design.accent.regex' => I18n::t('designer.v_accent'),
        ]);

        $design = Design::sanitize((array) $request->input('design'));
        $design['motif'] = '';
        foreach (['background' => 'label_background', 'login_image' => 'label_login_image'] as $key => $label) {
            $raw = $request->input('design.' . $key);
            if (is_string($raw) && trim($raw) !== '' && $design[$key] === '') {
                throw ValidationException::withMessages(['design.' . $key => [
                    I18n::t('designer.v_image', ['label' => I18n::t('designer.' . $label)]),
                ]]);
            }
        }

        $previous = Settings::media()['background_file'];
        $mediaFile = $this->checkedMedia($design['background'], $previous, 'design.background');
        $loginMedia = $this->mediaName($design['login_image']);
        if ($loginMedia !== null && ($loginMedia === '' || $loginMedia !== $mediaFile)) {
            throw ValidationException::withMessages(['design.login_image' => [I18n::t('designer.v_login_media')]]);
        }

        $limits = Limits::sanitize((array) $request->input('limits'));
        $announcement = Announcement::sanitize((array) $request->input('announcement'));
        $links = Links::sanitize((array) $request->input('links'));

        $this->blueprint->dbSetMany(Design::TABLE, [
            Design::RECORD => json_encode($design),
            Limits::RECORD => json_encode($limits),
            Announcement::RECORD => json_encode($announcement),
            Links::RECORD => json_encode($links),
            Settings::MEDIA_RECORD => json_encode(['background_file' => $mediaFile]),
        ]);
        Settings::flush();
        if ($previous !== '' && $previous !== $mediaFile) {
            Media::delete($previous);
        }

        return new JsonResponse(['object' => 'techtyl_designer', 'attributes' => $this->saved()]);
    }

    /**
     * Nimmt ein eigenes Hintergrundbild an (gleiche Pruefung und Neukodierung wie der Admin-Upload).
     * Es wird erst mit dem Speichern verwendet; ungespeicherte Bilder raeumt Media::prune spaeter weg.
     */
    public function upload(Request $request): JsonResponse
    {
        $name = Media::receive($request, 'background', Limits::MAX_UPLOAD_KB);

        return new JsonResponse(['object' => 'techtyl_upload', 'attributes' => ['url' => Media::url($name)]], JsonResponse::HTTP_CREATED);
    }

    /**
     * Seiten fuer die Live-Vorschau im iframe. Das Panel verbietet Rahmen sonst ganz
     * (X-Frame-Options: DENY); hier ist es nur fuer dieselbe Herkunft erlaubt.
     */
    public function preview(Request $request, string $page): Response
    {
        if (!in_array($page, self::PREVIEW_PAGES, true)) {
            throw new NotFoundHttpException();
        }

        if ($page === 'admin') {
            $html = view('admin.index', ['version' => app(SoftwareVersionService::class)])->render();
            $script = '<script src="/' . self::ASSET_DIR . '/receiver.js?v=' . $this->assetVersion() . '" defer></script>';
            $html = preg_replace('~</head>~i', $script . '</head>', $html, 1) ?? $html;
        } else {
            $html = view('blueprint.extensions.{identifier}.designer.preview', [
                'previewPath' => match ($page) {
                    'console' => $this->consolePath((string) $request->query('server', '')),
                    'login' => '/auth/login',
                    default => '/',
                },
                'guest' => $page === 'login',
                'assetVersion' => $this->assetVersion(),
            ])->render();
        }

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "frame-ancestors 'self'",
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** @return array<string, mixed> Alles, was der Designer zum Start braucht. */
    private function state(): array
    {
        $defaults = Design::defaults();
        unset($defaults['motif']);

        $websiteKinds = [];
        foreach (array_keys(Links::WEBSITE_KINDS) as $kind) {
            $websiteKinds[$kind] = I18n::t('designer.link_' . $kind);
        }

        return [
            'locale' => I18n::locale(),
            'texts' => $this->texts(),
            'saved' => $this->saved(),
            'defaults' => [
                'design' => $defaults,
                'limits' => Limits::defaults(),
                'announcement' => Announcement::defaults(),
                'links' => Links::defaults(),
            ],
            'motifs' => Motifs::publicList(),
            'servers' => DB::table('servers')->orderBy('id')->limit(50)->get(['uuidShort', 'name'])
                ->map(fn ($server) => ['id' => $server->uuidShort, 'name' => Clean::line($server->name, 60)])->all(),
            'upload' => [
                'max_kb' => Limits::MAX_UPLOAD_KB,
                'php_limit' => (string) ini_get('upload_max_filesize'),
                'formats' => array_keys(array_filter(ImageProcessor::readable())),
            ],
            'options' => [
                'variants' => Design::VARIANTS,
                'navigation' => Design::NAVIGATION,
                'server_layout' => Design::SERVER_LAYOUT,
                'motion' => Design::MOTION,
                'login_title_max' => Design::LOGIN_TITLE_MAX,
                'login_subtitle_max' => Design::LOGIN_SUBTITLE_MAX,
                'upload_min_kb' => Limits::MIN_UPLOAD_KB,
                'upload_max_kb' => Limits::MAX_UPLOAD_KB,
                'announcement' => [
                    'kinds' => Announcement::KINDS,
                    'icons' => Announcement::ICONS,
                    'positions' => Announcement::POSITIONS,
                    'text_max' => Announcement::TEXT_MAX,
                    'max_lines' => Announcement::MAX_LINES,
                ],
                'links' => [
                    'website' => $websiteKinds,
                    'social' => Links::SOCIAL_NETWORKS,
                    'positions' => Links::POSITIONS,
                    'align' => Links::ALIGN,
                    'max_website' => Links::MAX_WEBSITE,
                    'max_social' => Links::MAX_SOCIAL,
                    'label_max' => Links::LABEL_MAX,
                    'url_max' => Links::URL_MAX,
                ],
            ],
        ];
    }

    /** @return array<string, mixed> Gespeicherter Stand (nach dem Speichern neu gelesen). */
    private function saved(): array
    {
        $design = Settings::design();
        unset($design['motif']);

        return [
            'design' => $design,
            'limits' => Settings::limits(),
            'announcement' => Settings::announcement(),
            'links' => Settings::links(),
            'uploaded' => Media::url(Settings::media()['background_file']),
        ];
    }

    /**
     * Name der Bilddatei, wenn $url auf ein hochgeladenes Bild zeigt: null = keine Upload-Adresse,
     * '' = Upload-Adresse, aber die Datei gibt es nicht.
     */
    private function mediaName(string $url): ?string
    {
        if (!str_starts_with($url, Media::URL_PREFIX)) {
            return null;
        }
        $found = Media::find(substr($url, strlen(Media::URL_PREFIX)));

        return $found !== null ? basename($found['path']) : '';
    }

    /**
     * Prueft einen Hintergrund, der auf ein hochgeladenes Bild zeigt: erlaubt sind das bisherige
     * Standardbild und frische Uploads, die noch nirgends verwendet werden (nicht etwa das Bild
     * eines Nutzers). Gibt den Dateinamen fuer den Media-Eintrag zurueck ('' = kein Upload).
     */
    private function checkedMedia(string $url, string $previous, string $field): string
    {
        $name = $this->mediaName($url);
        if ($name === null) {
            return '';
        }
        if ($name === '') {
            throw ValidationException::withMessages([$field => [I18n::t('designer.v_media_missing')]]);
        }
        if ($name !== $previous) {
            try {
                $used = DB::table(Settings::USER_TABLE)->where('background_file', $name)->exists()
                    || DB::table('techtyl_server_banners')->where('file', $name)->exists()
                    || DB::table('techtyl_egg_banners')->where('file', $name)->exists();
            } catch (\Throwable $e) {
                Log::warning('techtyl: Bildpruefung im Designer fehlgeschlagen', ['error' => $e->getMessage()]);
                $used = true;
            }
            if ($used) {
                throw ValidationException::withMessages([$field => [I18n::t('designer.v_media_used')]]);
            }
        }

        return $name;
    }

    private function consolePath(string $wanted): string
    {
        $query = DB::table('servers')->orderBy('id');
        if (preg_match('/^[a-f0-9]{8}$/', $wanted) === 1) {
            $id = DB::table('servers')->where('uuidShort', $wanted)->value('uuidShort');
            if (is_string($id)) {
                return '/server/' . $id;
            }
        }
        $id = $query->value('uuidShort');

        return is_string($id) && preg_match('/^[a-f0-9]{8}$/', $id) === 1 ? '/server/' . $id : '/';
    }

    /** @return array<string, string> Texts of the designer page in the active language (English as fallback). */
    private function texts(): array
    {
        $file = base_path('app/BlueprintFramework/Extensions/{identifier}/lang/designer.php');
        $texts = is_file($file) ? require $file : [];
        if (!is_array($texts)) {
            return [];
        }
        $english = is_array($texts['en'] ?? null) ? $texts['en'] : [];
        $local = is_array($texts[I18n::locale()] ?? null) ? $texts[I18n::locale()] : [];

        return array_merge($english, $local);
    }

    /** Version fuer Skript-/Stil-Adressen: aendert sich mit jeder Aenderung an den Dateien. */
    private function assetVersion(): string
    {
        $stamp = 0;
        foreach (['designer.js', 'designer.css', 'receiver.js', 'preview-path.js'] as $file) {
            $path = public_path(self::ASSET_DIR . '/' . $file);
            $stamp = max($stamp, is_file($path) ? (int) filemtime($path) : 0);
        }

        return (string) $stamp;
    }
}
