<?php

namespace Pterodactyl\Http\Controllers\Admin\Extensions\{identifier};

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Design;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Admin\BlueprintAdminLibrary;

class {identifier}ExtensionController extends Controller
{
    public function __construct(
        private ViewFactory $view,
        private BlueprintAdminLibrary $blueprint,
    ) {
    }

    public function index(): View
    {
        $design = Design::fromStored($this->blueprint->dbGet(Design::TABLE, Design::RECORD, '{}'));

        return $this->view->make('admin.extensions.{identifier}.index', [
            'root' => '/admin/extensions/{identifier}',
            'blueprint' => $this->blueprint,
            'design' => $design,
            'variants' => Design::VARIANTS,
            'navigation' => Design::NAVIGATION,
            'serverLayouts' => Design::SERVER_LAYOUT,
            'motion' => Design::MOTION,
        ]);
    }

    public function update({identifier}DesignFormRequest $request): RedirectResponse
    {
        $design = Design::sanitize($request->validated());
        $this->blueprint->dbSet(Design::TABLE, Design::RECORD, json_encode($design));

        return redirect()->route('admin.extensions.{identifier}.index')->with('success', 'Design gespeichert.');
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
