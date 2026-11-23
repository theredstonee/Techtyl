<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant\Providers;

/**
 * Anthropic Messages API (POST /v1/messages, gestreamt).
 * Stand der Doku 2026-11: Kopfzeilen x-api-key und anthropic-version, Systemnachricht im Feld
 * "system", keine Sampling-Parameter (temperature usw. sind in dieser API-Version nicht erlaubt).
 * Aktuelle Modelle denken immer mit; max_tokens begrenzt Denken und Antwort zusammen, die Tiefe
 * steuert output_config.effort.
 */
class AnthropicDriver implements Driver
{
    public const VERSION = '2026-10-21';
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    public function __construct(
        private Transport $transport,
        private string $key,
        private string $model,
        private string $effort = 'low',
        private bool $fallbacks = true,
        private string $base = 'https://api.anthropic.com',
    ) {
    }

    public function chat(string $system, array $messages, int $maxTokens, callable $onText, callable $onIdle): Result
    {
        $headers = [
            'x-api-key' => $this->key,
            'anthropic-version' => self::VERSION,
        ];
        $body = [
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => $messages,
            'stream' => true,
        ];
        if ($this->effort !== '') {
            $body['output_config'] = ['effort' => $this->effort];
        }
        // Lehnt ein Sicherheitsfilter die Anfrage ab, laesst Anthropic sie auf Wunsch von einem
        // empfohlenen Ausweichmodell beantworten. Nur bei Modellen, die das unterstuetzen.
        if ($this->fallbacks && self::supportsFallback($this->model)) {
            $headers['anthropic-beta'] = self::FALLBACK_BETA;
            $body['fallbacks'] = 'default';
        }

        $result = new Result();
        $complete = $this->transport->stream($this->base . '/v1/messages', $headers, $body, function (string $event, array $data) use ($result, $onText): bool {
            switch ($data['type'] ?? $event) {
                case 'message_start':
                    $result->tokensIn = self::int($data['message']['usage']['input_tokens'] ?? null);
                    break;
                case 'content_block_delta':
                    if (($data['delta']['type'] ?? '') === 'text_delta' && is_string($data['delta']['text'] ?? null) && $data['delta']['text'] !== '') {
                        $result->text .= $data['delta']['text'];

                        return $onText($data['delta']['text']);
                    }
                    break;
                case 'message_delta':
                    $result->tokensOut = self::int($data['usage']['output_tokens'] ?? null) ?? $result->tokensOut;
                    $result->stop = match ($data['delta']['stop_reason'] ?? null) {
                        'max_tokens', 'model_context_window_exceeded' => Result::LENGTH,
                        'refusal' => Result::REFUSAL,
                        default => Result::END,
                    };
                    break;
                case 'error':
                    $type = (string) ($data['error']['type'] ?? '');
                    throw new ProviderException(match ($type) {
                        'overloaded_error', 'api_error' => ProviderException::UNAVAILABLE,
                        'rate_limit_error', 'billing_error' => ProviderException::RATE,
                        'authentication_error', 'permission_error' => ProviderException::AUTH,
                        'timeout_error' => ProviderException::TIMEOUT,
                        default => ProviderException::BAD_REQUEST,
                    }, Transport::errorDetail((string) json_encode($data)));
            }

            return true;
        }, $onIdle);

        if (!$complete) {
            $result->stop = Result::ABORTED;
        }

        return $result;
    }

    public static function supportsFallback(string $model): bool
    {
        return preg_match('/^claude-(fable-5|opus-5|sonnet-5-5)/', $model) === 1;
    }

    private static function int(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }
}
