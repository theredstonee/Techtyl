<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant;

use Illuminate\Support\Facades\Log;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Client\BlueprintClientLibrary;

/**
 * Admin-Einstellungen des KI-Assistenten (ein JSON-Eintrag "assistant_settings").
 * API-Schluessel liegen darin nur verschluesselt (encrypt()) und verlassen diese Klasse
 * ausschliesslich ueber secret(), also nie in Richtung Browser oder Log.
 */
class Config
{
    public const TABLE = 'techtyl';
    public const RECORD = 'assistant_settings';

    /**
     * Anbieter. family waehlt den Treiber, base ist die feste Adresse (https), token_param der
     * Name der Obergrenze fuer die Antwortlaenge laut Doku des Anbieters (Stand 2026-11).
     */
    public const PROVIDERS = [
        'anthropic' => [
            'label' => 'Anthropic (Claude)',
            'family' => 'anthropic',
            'base' => 'https://api.anthropic.com',
            'default_model' => 'claude-opus-5-5',
            'models' => ['claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5', 'claude-fable-5-2'],
        ],
        'openai' => [
            'label' => 'OpenAI',
            'family' => 'openai',
            'base' => 'https://api.openai.com/v1',
            'token_param' => 'max_completion_tokens',
            'default_model' => 'gpt-6-luna',
            'models' => ['gpt-6-luna', 'gpt-6.1-terra', 'gpt-6.2-sol', 'gpt-6.2-astra'],
        ],
        'gemini' => [
            'label' => 'Google Gemini',
            'family' => 'gemini',
            'base' => 'https://generativelanguage.googleapis.com/v1beta',
            'default_model' => 'gemini-3.8-flash',
            'models' => ['gemini-3.8-flash', 'gemini-3.6-flash', 'gemini-3.5-flash-lite', 'gemini-4-hydrogen'],
        ],
        'azure' => [
            'label' => 'Azure OpenAI',
            'family' => 'azure',
            'token_param' => 'max_completion_tokens',
            'default_model' => '',
            'models' => [],
        ],
        'mistral' => [
            'label' => 'Mistral',
            'family' => 'openai',
            'base' => 'https://api.mistral.ai/v1',
            'token_param' => 'max_tokens',
            'default_model' => 'mistral-small-latest',
            'models' => ['mistral-small-latest', 'mistral-large-latest'],
        ],
        'groq' => [
            'label' => 'Groq',
            'family' => 'openai',
            'base' => 'https://api.groq.com/openai/v1',
            'token_param' => 'max_completion_tokens',
            'default_model' => 'llama-3.3-70b-versatile',
            'models' => ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant', 'openai/gpt-oss-120b', 'openai/gpt-oss-20b'],
        ],
        'openrouter' => [
            'label' => 'OpenRouter',
            'family' => 'openai',
            'base' => 'https://openrouter.ai/api/v1',
            'token_param' => 'max_tokens',
            'default_model' => 'anthropic/claude-sonnet-4.6',
            'models' => ['anthropic/claude-sonnet-4.6', 'openai/gpt-5.2'],
        ],
        'custom' => [
            'label' => 'Eigener Server (OpenAI-kompatibel, z. B. Ollama)',
            'family' => 'openai',
            'token_param' => 'max_tokens',
            'default_model' => '',
            'models' => ['llama3.2', 'qwen3', 'mistral'],
        ],
        'demo' => [
            'label' => 'Demo (Testantworten ohne Anbieter)',
            'family' => 'demo',
            'default_model' => 'demo',
            'models' => [],
        ],
    ];

    public const MODEL_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:\/@-]{0,119}$/';
    public const EFFORT = ['', 'low', 'medium', 'high'];

    /** Zahlen-Einstellungen: Schluessel => [Standard, Minimum, Maximum]. */
    public const NUMBERS = [
        'daily_limit' => [40, 1, 5000],
        'per_minute' => [6, 1, 60],
        'max_input_chars' => [4000, 200, 20000],
        'max_tokens' => [2048, 128, 16000],
        'timeout' => [120, 20, 600],
        'context_lines' => [60, 10, 100],
        'context_chars' => [6000, 500, 20000],
        'retention_days' => [90, 0, 3650],
    ];

    /** Wie viele fruehere Nachrichten eines Gespraechs mitgeschickt werden (Kostenbremse). */
    public const HISTORY_MESSAGES = 12;
    public const HISTORY_CHARS = 24000;
    public const MAX_CONVERSATIONS = 200;

    /** @var array<string, mixed>|null */
    private static ?array $loaded = null;

    /** @return array<string, mixed> Vollstaendige, bereinigte Einstellungen (Schluessel bleiben verschluesselt). */
    public static function all(): array
    {
        if (self::$loaded === null) {
            $decoded = null;
            try {
                $stored = app(BlueprintClientLibrary::class)->dbGet(self::TABLE, self::RECORD, '{}');
                $decoded = is_string($stored) ? json_decode($stored, true) : null;
            } catch (\Throwable $e) {
                Log::warning('techtyl assistant: Einstellungen nicht lesbar', ['error' => $e->getMessage()]);
            }
            self::$loaded = self::normalize(is_array($decoded) ? $decoded : []);
        }

        return self::$loaded;
    }

    /** @param array<string, mixed> $settings */
    public static function save(array $settings): void
    {
        $clean = self::normalize($settings);
        app(BlueprintClientLibrary::class)->dbSet(self::TABLE, self::RECORD, json_encode($clean));
        self::$loaded = $clean;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $out = [
            'provider' => isset(self::PROVIDERS[$input['provider'] ?? '']) ? (string) $input['provider'] : '',
            'allow_private' => ($input['allow_private'] ?? false) === true,
            'context_enabled' => ($input['context_enabled'] ?? true) === true,
            'fallbacks' => ($input['fallbacks'] ?? true) === true,
            'effort' => in_array($input['effort'] ?? 'low', self::EFFORT, true) ? (string) ($input['effort'] ?? 'low') : 'low',
            'extra_prompt' => mb_substr(trim((string) ($input['extra_prompt'] ?? '')), 0, 1000),
            'providers' => [],
        ];
        foreach (self::NUMBERS as $key => [$default, $min, $max]) {
            $value = $input[$key] ?? $default;
            $out[$key] = is_numeric($value) ? max($min, min($max, (int) $value)) : $default;
        }

        $stored = is_array($input['providers'] ?? null) ? $input['providers'] : [];
        foreach (self::PROVIDERS as $id => $definition) {
            if ($id === 'demo') {
                continue;
            }
            $row = is_array($stored[$id] ?? null) ? $stored[$id] : [];
            $model = (string) ($row['model'] ?? '');
            $entry = [
                'key' => is_string($row['key'] ?? null) ? $row['key'] : '',
                'model' => preg_match(self::MODEL_PATTERN, $model) === 1 ? $model : $definition['default_model'],
            ];
            if ($id === 'azure') {
                $entry['endpoint'] = self::text($row['endpoint'] ?? '', 200);
                $version = (string) ($row['api_version'] ?? '');
                $entry['api_version'] = preg_match('/^\d{4}-\d{2}-\d{2}(-preview)?$/', $version) === 1 ? $version : '';
            }
            if ($id === 'custom') {
                $entry['url'] = self::text($row['url'] ?? '', 200);
            }
            $out['providers'][$id] = $entry;
        }

        return $out;
    }

    private static function text(mixed $value, int $max): string
    {
        return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
    }

    public static function int(string $key): int
    {
        return (int) self::all()[$key];
    }

    /** Gewaehlter Anbieter oder '' wenn keiner gewaehlt ist. */
    public static function provider(): string
    {
        return (string) self::all()['provider'];
    }

    /** @return array<string, mixed> Gespeicherte Werte eines Anbieters (Schluessel verschluesselt). */
    public static function providerSettings(string $id): array
    {
        return self::all()['providers'][$id] ?? [];
    }

    public static function hasKey(string $id): bool
    {
        return (self::providerSettings($id)['key'] ?? '') !== '';
    }

    /** Entschluesselter API-Schluessel; nur fuer den Aufruf beim Anbieter. */
    public static function secret(string $id): string
    {
        $encrypted = self::providerSettings($id)['key'] ?? '';
        if ($encrypted === '') {
            return '';
        }
        try {
            return (string) decrypt($encrypted);
        } catch (\Throwable $e) {
            // Z. B. nach einem Wechsel von APP_KEY: der Schluessel muss neu eingetragen werden.
            Log::warning('techtyl assistant: gespeicherter Schluessel nicht entschluesselbar', ['provider' => $id]);

            return '';
        }
    }

    /** Ist fuer diesen Anbieter alles Noetige eingetragen? */
    public static function ready(string $id): bool
    {
        if (!isset(self::PROVIDERS[$id])) {
            return false;
        }
        if ($id === 'demo') {
            return true;
        }
        $row = self::providerSettings($id);
        if (($row['model'] ?? '') === '') {
            return false;
        }

        return match ($id) {
            'custom' => ($row['url'] ?? '') !== '',
            'azure' => self::hasKey($id) && ($row['endpoint'] ?? '') !== '',
            default => self::hasKey($id),
        };
    }

    /** Der Assistent ist nutzbar, wenn ein Anbieter gewaehlt und vollstaendig eingerichtet ist. */
    public static function configured(): bool
    {
        return self::provider() !== '' && self::ready(self::provider());
    }

    public static function model(string $id): string
    {
        return $id === 'demo' ? 'demo' : (string) (self::providerSettings($id)['model'] ?? '');
    }

    public static function label(string $id): string
    {
        if ($id === 'custom' || $id === 'demo') {
            return \Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n::t('assistant.provider_' . $id);
        }

        return self::PROVIDERS[$id]['label'] ?? '';
    }

    public static function flush(): void
    {
        self::$loaded = null;
    }
}
