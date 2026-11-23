<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant;

use Illuminate\Http\Request;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant\Providers\Factory;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant\Providers\ProviderException;

/**
 * Admin-Reiter "KI-Assistent": Anbieter und Schluessel, Limits, Server-Kontext, Aufbewahrung
 * und der Verbrauch je Nutzer. Schluessel werden nie ausgegeben, nur "gesetzt / nicht gesetzt".
 */
class Admin
{
    /** @return array<string, mixed> */
    public static function data(): array
    {
        $settings = Config::all();
        $providers = [];
        foreach (Config::PROVIDERS as $id => $definition) {
            $row = $settings['providers'][$id] ?? [];
            $providers[$id] = [
                'label' => Config::label($id),
                'models' => $definition['models'],
                'default_model' => $definition['default_model'],
                'model' => (string) ($row['model'] ?? ''),
                'has_key' => $id !== 'demo' && ($row['key'] ?? '') !== '',
                'ready' => Config::ready($id),
                'endpoint' => (string) ($row['endpoint'] ?? ''),
                'api_version' => (string) ($row['api_version'] ?? ''),
                'url' => (string) ($row['url'] ?? ''),
            ];
        }

        $ready = Schema::hasTable(Quota::TABLE);
        $usage = [];
        $totals = ['today' => 0, 'month' => 0, 'conversations' => 0, 'users_today' => 0];
        if ($ready) {
            $today = Quota::day();
            $since = now()->subDays(29)->toDateString();
            $rows = DB::table(Quota::TABLE . ' as u')
                ->join('users', 'users.id', '=', 'u.user_id')
                ->where('u.day', '>=', $since)
                ->groupBy('u.user_id', 'users.username')
                ->orderByDesc('month_requests')
                ->limit(100)
                ->select('users.username')
                ->selectRaw('SUM(CASE WHEN u.day = ? THEN u.requests ELSE 0 END) as today_requests', [$today])
                ->selectRaw('SUM(u.requests) as month_requests')
                ->selectRaw('SUM(u.tokens_in) as tokens_in')
                ->selectRaw('SUM(u.tokens_out) as tokens_out')
                ->get();
            foreach ($rows as $row) {
                $usage[] = [
                    'username' => (string) $row->username,
                    'today' => (int) $row->today_requests,
                    'today_percent' => (int) round(min(100, (int) $row->today_requests / max(1, $settings['daily_limit']) * 100)),
                    'month' => (int) $row->month_requests,
                    'tokens_in' => (int) $row->tokens_in,
                    'tokens_out' => (int) $row->tokens_out,
                ];
                $totals['today'] += (int) $row->today_requests;
                $totals['month'] += (int) $row->month_requests;
                $totals['users_today'] += (int) $row->today_requests > 0 ? 1 : 0;
            }
            $totals['conversations'] = DB::table('techtyl_assistant_conversations')->count();
        }

        return [
            'assistantReady' => $ready,
            'assistant' => $settings,
            'assistantProviders' => $providers,
            'assistantNumbers' => Config::NUMBERS,
            'assistantConfigured' => Config::configured(),
            'assistantUsage' => $usage,
            'assistantTotals' => $totals,
        ];
    }

    public static function handle(Request $request, string $action): string
    {
        return match ($action) {
            'save' => self::save($request),
            'test' => self::test(),
            'prune' => self::prune(),
            default => throw new NotFoundHttpException(),
        };
    }

    private static function save(Request $request): string
    {
        $ids = array_keys(Config::PROVIDERS);
        $rules = [
            'provider' => ['nullable', 'string', Rule::in($ids)],
            'allow_private' => ['nullable', 'boolean'],
            'context_enabled' => ['nullable', 'boolean'],
            'fallbacks' => ['nullable', 'boolean'],
            'effort' => ['nullable', 'string', Rule::in(Config::EFFORT)],
            'extra_prompt' => ['nullable', 'string', 'max:1000'],
            'azure_endpoint' => ['nullable', 'string', 'max:200'],
            'azure_api_version' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}(-preview)?$/'],
            'custom_url' => ['nullable', 'string', 'max:200'],
        ];
        $messages = [
            'azure_api_version.regex' => I18n::t('assistant.azure_version_invalid'),
        ];
        foreach (Config::NUMBERS as $key => [$default, $min, $max]) {
            $rules[$key] = ['required', 'integer', 'min:' . $min, 'max:' . $max];
        }
        foreach ($ids as $id) {
            if ($id === 'demo') {
                continue;
            }
            $rules['model_' . $id] = ['nullable', 'string', 'max:120', 'regex:' . Config::MODEL_PATTERN];
            $rules['key_' . $id] = ['nullable', 'string', 'min:8', 'max:400', 'regex:/^[\x21-\x7e]+$/'];
            $rules['remove_key_' . $id] = ['nullable', 'boolean'];
            $messages['model_' . $id . '.regex'] = I18n::t('assistant.model_invalid', ['provider' => Config::label($id)]);
            $messages['key_' . $id . '.regex'] = I18n::t('assistant.key_invalid', ['provider' => Config::label($id)]);
        }
        $input = $request->validate($rules, $messages);

        $settings = Config::all();
        $settings['provider'] = (string) ($input['provider'] ?? '');
        foreach (['allow_private', 'context_enabled', 'fallbacks'] as $flag) {
            $settings[$flag] = filter_var($input[$flag] ?? false, FILTER_VALIDATE_BOOLEAN);
        }
        $settings['effort'] = (string) ($input['effort'] ?? '');
        $settings['extra_prompt'] = trim((string) ($input['extra_prompt'] ?? ''));
        foreach (array_keys(Config::NUMBERS) as $key) {
            $settings[$key] = (int) $input[$key];
        }

        foreach ($ids as $id) {
            if ($id === 'demo') {
                continue;
            }
            $row = $settings['providers'][$id];
            $model = trim((string) ($input['model_' . $id] ?? ''));
            $row['model'] = $model !== '' ? $model : Config::PROVIDERS[$id]['default_model'];
            if (filter_var($input['remove_key_' . $id] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $row['key'] = '';
            }
            $key = (string) ($input['key_' . $id] ?? '');
            if ($key !== '') {
                $row['key'] = encrypt($key);
            }
            $settings['providers'][$id] = $row;
        }

        // Frei eintragbare Adressen pruefen (https, kein internes Netz ohne ausdrueckliche Freigabe).
        try {
            $endpoint = trim((string) ($input['azure_endpoint'] ?? ''));
            $settings['providers']['azure']['endpoint'] = $endpoint !== '' ? Endpoint::azure($endpoint) : '';
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['azure_endpoint' => [$e->getMessage()]]);
        }
        $settings['providers']['azure']['api_version'] = (string) ($input['azure_api_version'] ?? '');
        try {
            $url = trim((string) ($input['custom_url'] ?? ''));
            $settings['providers']['custom']['url'] = $url !== '' ? Endpoint::custom($url, $settings['allow_private']) : '';
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['custom_url' => [$e->getMessage()]]);
        }

        Config::save($settings);
        Log::info('techtyl assistant: Einstellungen geaendert', ['admin' => $request->user()->id, 'provider' => $settings['provider']]);

        if ($settings['provider'] === '') {
            return I18n::t('assistant.saved_none');
        }
        if (!Config::ready($settings['provider'])) {
            return I18n::t('assistant.saved_incomplete', ['provider' => Config::label($settings['provider'])]);
        }

        return I18n::t('assistant.saved', ['provider' => Config::label($settings['provider'])]);
    }

    /** Schickt eine winzige Anfrage an den gewaehlten Anbieter, um Schluessel und Modell zu pruefen. */
    private static function test(): string
    {
        if (!Config::configured()) {
            throw ValidationException::withMessages(['provider' => [I18n::t('assistant.test_not_configured')]]);
        }
        if (!RateLimiter::attempt('techtyl-assistant:test', 6, static fn () => true, 60)) {
            throw ValidationException::withMessages(['provider' => [I18n::t('assistant.test_too_many')]]);
        }

        $provider = Config::provider();
        try {
            $result = Factory::make($provider)->chat(
                'This is a connection test. Reply with the single word OK.',
                [['role' => 'user', 'content' => 'Reply with OK only.']],
                min(512, Config::int('max_tokens')),
                static fn (string $piece): bool => true,
                static fn (): bool => true,
            );
        } catch (ProviderException $e) {
            Log::warning('techtyl assistant: Verbindungstest fehlgeschlagen', ['provider' => $provider, 'kind' => $e->kind, 'status' => $e->status, 'detail' => $e->getMessage()]);
            throw ValidationException::withMessages(['provider' => [
                I18n::t('assistant.test_failed', ['provider' => Config::label($provider), 'status' => $e->status > 0 ? ', HTTP ' . $e->status : '', 'message' => $e->userMessage()])
                . ($e->getMessage() !== '' ? ' ' . I18n::t('assistant.test_failed_detail', ['detail' => $e->getMessage()]) : ''),
            ]]);
        }

        if (trim($result->text) === '') {
            return I18n::t('assistant.test_empty', ['provider' => Config::label($provider)]);
        }

        return I18n::t('assistant.test_ok', ['provider' => Config::label($provider), 'model' => Config::model($provider)]);
    }

    private static function prune(): string
    {
        $deleted = Retention::prune();

        return $deleted === 1 ? I18n::t('assistant.pruned_one') : I18n::t('assistant.pruned', ['count' => $deleted]);
    }
}
