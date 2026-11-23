<?php

namespace Pterodactyl\Http\Controllers\Admin\Extensions\{identifier};

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Media;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Design;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Limits;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Settings;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\ImageProcessor;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Admin\BlueprintAdminLibrary;

class {identifier}ExtensionController extends Controller
{
    public function __construct(
        private ViewFactory $view,
        private BlueprintAdminLibrary $blueprint,
    ) {
    }

    public function index(Request $request): View
    {
        // Reiter: "design" (Standard), "modules" oder der Schluessel eines Moduls.
        $tab = (string) $request->query('tab', 'design');
        if ($tab !== 'modules' && !Modules::exists($tab)) {
            $tab = 'design';
        }
        $moduleData = [];
        if (Modules::exists($tab)) {
            try {
                $moduleData = Modules::adminData($tab);
            } catch (\Throwable $e) {
                Log::error('techtyl: Admin-Daten des Moduls nicht lesbar', ['module' => $tab, 'error' => $e->getMessage()]);
                $moduleData = ['moduleError' => true];
            }
        }

        // Holt den Egg-Import nach, falls er bei der Installation nicht moeglich war (sonst eine Abfrage).
        \Pterodactyl\BlueprintFramework\Extensions\{identifier}\Eggs::importOnce();

        $design = Design::fromStored($this->blueprint->dbGet(Design::TABLE, Design::RECORD, '{}'));
        $tablesReady = Schema::hasTable('techtyl_egg_banners');

        $eggBanners = [];
        if ($tablesReady) {
            foreach (DB::table('techtyl_egg_banners')->pluck('file', 'egg_id') as $eggId => $file) {
                $eggBanners[(int) $eggId] = Media::url($file);
            }
        }

        return $this->view->make('admin.extensions.{identifier}.index', [
            'root' => '/admin/extensions/{identifier}',
            'blueprint' => $this->blueprint,
            'design' => $design,
            'variants' => Design::VARIANTS,
            'navigation' => Design::NAVIGATION,
            'serverLayouts' => Design::SERVER_LAYOUT,
            'motion' => Design::MOTION,
            'limits' => Settings::limits(),
            'uploadMin' => Limits::MIN_UPLOAD_KB,
            'uploadMax' => Limits::MAX_UPLOAD_KB,
            'defaultBackground' => Media::url(Settings::media()['background_file']),
            'tablesReady' => $tablesReady,
            'eggs' => DB::table('eggs')->join('nests', 'nests.id', '=', 'eggs.nest_id')
                ->orderBy('nests.name')->orderBy('eggs.name')
                ->get(['eggs.id', 'eggs.name', 'nests.name as nest']),
            'eggBanners' => $eggBanners,
            'readableFormats' => array_keys(array_filter(ImageProcessor::readable())),
            'phpUploadLimit' => (string) ini_get('upload_max_filesize'),
            'tab' => $tab,
            'designerUrl' => '/extensions/{identifier}/designer/editor',
            'designerSummary' => [
                'announcement' => Settings::announcement()['enabled'],
                'links' => count(Settings::links()['website']) + count(Settings::links()['social']),
                'loginText' => $design['login_title'] !== '' || $design['login_subtitle'] !== '',
            ],
            'modules' => Modules::ALL,
            'moduleStates' => Modules::states(),
            'moduleView' => Modules::exists($tab) && view()->exists('blueprint.extensions.{identifier}.admin.' . $tab)
                ? 'blueprint.extensions.{identifier}.admin.' . $tab
                : null,
            'moduleData' => $moduleData,
        ]);
    }

    public function update({identifier}DesignFormRequest $request): RedirectResponse
    {
        // Mit dem gespeicherten Stand zusammenfuehren: Werte, die nur der Designer setzt
        // (Login-Texte, Login-Bild), gehen beim Speichern dieses Formulars nicht verloren.
        $design = Design::sanitize(array_merge(Settings::design(), $request->validated()));
        $this->blueprint->dbSet(Design::TABLE, Design::RECORD, json_encode($design));
        Settings::flush();

        // Wurde die Bildadresse von Hand geaendert, wird das hochgeladene Standardbild nicht mehr gebraucht.
        $uploaded = Settings::media()['background_file'];
        if ($uploaded !== '' && $design['background'] !== Media::url($uploaded)) {
            $this->storeDefaultBackground('');
            Media::delete($uploaded);
        }
        $this->prune();

        return $this->back('Design gespeichert.');
    }

    /** Grenzen speichern und Standardbilder hochladen; das Feld "action" waehlt den Vorgang. */
    public function post(Request $request): RedirectResponse
    {
        $action = $request->validate(['action' => ['required', 'string', 'max:60', 'regex:/^[a-z]+([.-][a-z]+)*$/']])['action'];

        // Module schalten.
        if ($action === 'modules') {
            $this->blueprint->dbSet(Design::TABLE, Modules::RECORD, Modules::encode((array) $request->input('modules', [])));

            return $this->backTo('modules', 'Module gespeichert.');
        }

        // Account area: profile pictures, Gravatar fallback, editable names.
        if ($action === 'account') {
            \Pterodactyl\BlueprintFramework\Extensions\{identifier}\Account\AccountSettings::save($request->only(['uploads', 'gravatar', 'names']));

            return $this->backTo('modules', \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::t('core.saved'));
        }

        // Standardsprache fuer Gaeste und Konten ohne eigene Sprachwahl.
        if ($action === 'locale') {
            $locale = $request->validate(['locale' => 'required|string|in:' . implode(',', \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::SUPPORTED)])['locale'];
            $this->blueprint->dbSet(Design::TABLE, \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::RECORD, $locale);

            return $this->backTo('modules', \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::t('core.saved', [], $locale));
        }

        // Aktionen eines Moduls heissen "<schluessel>.<aktion>" und werden an dessen Admin-Klasse gereicht.
        if (str_contains($action, '.')) {
            [$module, $moduleAction] = explode('.', $action, 2);
            abort_unless(Modules::exists($module), 404);

            return $this->backTo($module, Modules::adminHandle($module, $request, $moduleAction));
        }

        abort_unless(in_array($action, ['limits', 'background', 'egg-banner'], true), 404);

        if ($action === 'limits') {
            $limits = Limits::sanitize($request->validate([
                'user_background' => 'nullable|boolean',
                'user_banners' => 'nullable|boolean',
                'user_appearance' => 'nullable|boolean',
                'user_layout' => 'nullable|boolean',
                'max_upload_kb' => 'required|integer|min:' . Limits::MIN_UPLOAD_KB . '|max:' . Limits::MAX_UPLOAD_KB,
            ]));
            $this->blueprint->dbSet(Design::TABLE, Limits::RECORD, json_encode($limits));
            Settings::flush();

            return $this->back('Grenzen gespeichert.');
        }

        if ($action === 'background') {
            $name = Media::receive($request, 'background', Limits::MAX_UPLOAD_KB);
            $previous = Settings::media()['background_file'];

            $design = Settings::design();
            $design['background'] = Media::url($name);
            $this->blueprint->dbSet(Design::TABLE, Design::RECORD, json_encode(Design::sanitize($design)));
            $this->storeDefaultBackground($name);
            Media::delete($previous);

            return $this->back('Standard-Hintergrund gespeichert.');
        }

        $eggId = (int) $request->validate(['egg_id' => 'required|integer|exists:eggs,id'])['egg_id'];
        $name = Media::receive($request, 'banner', Limits::MAX_UPLOAD_KB);
        try {
            $previous = DB::table('techtyl_egg_banners')->where('egg_id', $eggId)->value('file');
            DB::table('techtyl_egg_banners')->updateOrInsert(['egg_id' => $eggId], ['file' => $name, 'updated_at' => now()]);
        } catch (\Throwable $e) {
            Media::delete($name);
            throw $e;
        }
        Media::delete($previous);

        return $this->back('Standard-Banner gespeichert.');
    }

    /** DELETE /admin/extensions/techtyl/background/0 und /egg-banner/<Egg-ID>. */
    public function delete(Request $request, string $target, string $id): RedirectResponse
    {
        if ($target === 'background') {
            $previous = Settings::media()['background_file'];
            if ($previous !== '') {
                $design = Settings::design();
                if ($design['background'] === Media::url($previous)) {
                    $design['background'] = '';
                    $this->blueprint->dbSet(Design::TABLE, Design::RECORD, json_encode(Design::sanitize($design)));
                }
                $this->storeDefaultBackground('');
                Media::delete($previous);
            }

            return $this->back('Standard-Hintergrund entfernt.');
        }

        if ($target === 'egg-banner' && ctype_digit($id) && strlen($id) <= 10) {
            $previous = DB::table('techtyl_egg_banners')->where('egg_id', (int) $id)->value('file');
            if ($previous !== null) {
                DB::table('techtyl_egg_banners')->where('egg_id', (int) $id)->delete();
                Media::delete($previous);
            }

            return $this->back('Standard-Banner entfernt.');
        }

        throw new NotFoundHttpException();
    }

    private function storeDefaultBackground(string $name): void
    {
        $this->blueprint->dbSet(Design::TABLE, Settings::MEDIA_RECORD, json_encode(['background_file' => $name]));
        Settings::flush();
    }

    /** Raeumt verwaiste Bilddateien weg; darf das Speichern nie scheitern lassen. */
    private function prune(): void
    {
        try {
            Media::prune();
        } catch (\Throwable $e) {
            Log::warning('techtyl: Aufraeumen der Bilder fehlgeschlagen', ['error' => $e->getMessage()]);
        }
    }

    private function backTo(string $tab, string $message): RedirectResponse
    {
        \Prologue\Alerts\Facades\Alert::success(e($message))->flash();

        return redirect('/admin/extensions/{identifier}?tab=' . $tab);
    }

    private function back(string $message): RedirectResponse
    {
        \Prologue\Alerts\Facades\Alert::success(e($message))->flash();

        return redirect()->route('admin.extensions.{identifier}.index');
    }
}

class {identifier}DesignFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'variant' => 'required|string|in:' . implode(',', Design::VARIANTS),
            'accent' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'radius' => 'required|integer|min:0|max:22',
            'navigation' => 'required|string|in:' . implode(',', Design::NAVIGATION),
            'server_layout' => 'required|string|in:' . implode(',', Design::SERVER_LAYOUT),
            'motion' => 'required|string|in:' . implode(',', Design::MOTION),
            'banners' => 'nullable|boolean',
            'banner_height' => 'required|integer|min:48|max:160',
            'background' => 'nullable|string|max:500',
            'dim' => 'required|integer|min:0|max:90',
            'opacity' => 'required|integer|min:35|max:100',
            'blur' => 'required|integer|min:0|max:24',
        ];
    }
}
