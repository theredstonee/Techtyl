<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Overview;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Prologue\Alerts\Facades\Alert;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;

/**
 * Saves the card layout of the admin overview. Only reachable for signed-in root admins
 * (middleware in routes.web.php), every request is a normal form post with CSRF token and
 * redirects back to /admin. Works without JavaScript: the arrow buttons post a "move".
 */
class CardsController extends Controller
{
    /** Session flag that keeps the "customize" panel open after a reload. */
    public const OPEN_FLAG = 'techtyl_overview_customize';

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'order' => ['sometimes', 'array', 'max:80'],
            'order.*' => ['string', 'regex:' . Cards::ID_PATTERN],
            'visible' => ['sometimes', 'array', 'max:80'],
            'visible.*' => ['string', 'regex:' . Cards::ID_PATTERN],
            'move' => ['sometimes', 'string', 'max:40', 'regex:/^(?:[a-z][a-z_]{1,19}|module:[a-z]{2,20}|custom:[a-f0-9]{8})\|(?:up|down)$/'],
            'remove' => ['sometimes', 'string', 'regex:/^custom:[a-f0-9]{8}$/'],
            'reset' => ['sometimes', 'in:1'],
        ], [], [
            'order' => I18n::t('overview.cards_order'),
            'visible' => I18n::t('overview.cards_visible'),
        ]);

        $config = Cards::config();
        $keepOpen = true;

        try {
            if ($request->has('reset')) {
                // Default order and visibility; the admin's own cards are kept.
                Cards::store(['order' => [], 'hidden' => Cards::DEFAULT_HIDDEN, 'custom' => $config['custom']]);
                Alert::success(I18n::t('overview.cards_reset_done'))->flash();
            } elseif ($request->filled('remove')) {
                $id = substr((string) $request->input('remove'), 7);
                unset($config['custom'][$id]);
                $config['order'] = array_values(array_diff($config['order'], ['custom:' . $id]));
                $config['hidden'] = array_values(array_diff($config['hidden'], ['custom:' . $id]));
                Cards::store($config);
                Alert::success(I18n::t('overview.cards_removed'))->flash();
            } else {
                $order = array_values(array_unique(array_map('strval', (array) $request->input('order', []))));
                $visible = array_map('strval', (array) $request->input('visible', []));
                if ($request->filled('move')) {
                    [$id, $direction] = explode('|', (string) $request->input('move'), 2);
                    $order = self::move($order, $id, $direction);
                } else {
                    $keepOpen = false;
                }
                $config['order'] = $order;
                $config['hidden'] = array_values(array_diff($order, $visible));
                Cards::store($config);
                Alert::success(I18n::t('core.saved'))->flash();
            }
        } catch (\Throwable $e) {
            Log::error('Techtyl overview: saving the card layout failed', ['exception' => $e]);
            Alert::danger(I18n::t('core.error'))->flash();
        }

        return self::back($keepOpen);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:' . Cards::TITLE_MAX],
            'text' => ['nullable', 'string', 'max:' . Cards::TEXT_MAX],
            'link' => ['nullable', 'string', 'max:' . Cards::LINK_MAX],
            'icon' => ['required', 'string', 'in:' . implode(',', Cards::ICONS)],
        ], [], [
            'title' => I18n::t('overview.cards_field_title'),
            'text' => I18n::t('overview.cards_field_text'),
            'link' => I18n::t('overview.cards_field_link'),
            'icon' => I18n::t('overview.cards_field_icon'),
        ]);

        if (Cards::cleanLink($data['link'] ?? null) === null) {
            throw ValidationException::withMessages(['link' => I18n::t('overview.cards_link_invalid')]);
        }
        $card = Cards::cleanCustom($data);
        if ($card === null) {
            throw ValidationException::withMessages(['title' => I18n::t('overview.cards_title_required')]);
        }

        $config = Cards::config();
        if (count($config['custom']) >= Cards::MAX_CUSTOM) {
            throw ValidationException::withMessages(['title' => I18n::t('overview.cards_limit', ['count' => Cards::MAX_CUSTOM])]);
        }

        $id = Cards::newId($config);
        $config['custom'][$id] = $card;
        if ($config['order'] !== []) {
            $config['order'][] = 'custom:' . $id;
        }

        try {
            Cards::store($config);
            Alert::success(I18n::t('overview.cards_added'))->flash();
        } catch (\Throwable $e) {
            Log::error('Techtyl overview: adding a card failed', ['exception' => $e]);
            Alert::danger(I18n::t('core.error'))->flash();
        }

        return self::back(true);
    }

    /** @param list<string> $order */
    private static function move(array $order, string $id, string $direction): array
    {
        $index = array_search($id, $order, true);
        if ($index === false) {
            return $order;
        }
        $target = $direction === 'up' ? $index - 1 : $index + 1;
        if ($target < 0 || $target >= count($order)) {
            return $order;
        }
        [$order[$index], $order[$target]] = [$order[$target], $order[$index]];

        return $order;
    }

    private static function back(bool $keepOpen): RedirectResponse
    {
        if ($keepOpen) {
            session()->flash(self::OPEN_FLAG, true);
        }

        return redirect()->to(route('admin.index') . ($keepOpen ? '#techtyl-ov-customize' : ''));
    }
}
