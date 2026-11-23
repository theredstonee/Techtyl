<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant\Providers;

/**
 * Chat Completions nach OpenAI-Art (POST <basis>/chat/completions, gestreamt). Gilt fuer OpenAI,
 * Mistral, Groq, OpenRouter und eigene Server wie Ollama. Die Systemnachricht ist die erste
 * Nachricht mit der Rolle "system"; die Obergrenze heisst je nach Anbieter max_completion_tokens
 * (OpenAI, Groq, Azure - dort ist max_tokens veraltet) oder max_tokens.
 */
class OpenAiCompatibleDriver implements Driver
{
    /**
     * @param string $url vollstaendige Adresse des Endpunkts
     * @param array<string, string> $headers Kopfzeilen fuer die Anmeldung
     * @param string $tokenParam "max_tokens" oder "max_completion_tokens"
     * @param bool $usage ob stream_options.include_usage mitgeschickt wird
     */
    public function __construct(
        protected Transport $transport,
        protected string $url,
        protected array $headers,
        protected string $model,
        protected string $tokenParam = 'max_tokens',
        protected bool $usage = false,
    ) {
    }

    public function chat(string $system, array $messages, int $maxTokens, callable $onText, callable $onIdle): Result
    {
        $body = [
            'model' => $this->model,
            'messages' => array_merge([['role' => 'system', 'content' => $system]], $messages),
            'stream' => true,
            $this->tokenParam => $maxTokens,
        ];
        if ($this->usage) {
            $body['stream_options'] = ['include_usage' => true];
        }

        $result = new Result();
        try {
            $complete = $this->transport->stream($this->url, $this->headers, $body, function (string $event, array $data) use ($result, $onText): bool {
                if (isset($data['error'])) {
                    $code = is_array($data['error']) ? ($data['error']['code'] ?? 0) : 0;
                    $detail = Transport::errorDetail((string) json_encode($data));
                    throw is_int($code) && $code >= 400
                        ? ProviderException::fromStatus($code, $detail)
                        : new ProviderException(ProviderException::UNAVAILABLE, $detail);
                }
                if (is_array($data['usage'] ?? null)) {
                    $result->tokensIn = is_int($data['usage']['prompt_tokens'] ?? null) ? $data['usage']['prompt_tokens'] : $result->tokensIn;
                    $result->tokensOut = is_int($data['usage']['completion_tokens'] ?? null) ? $data['usage']['completion_tokens'] : $result->tokensOut;
                }
                $choice = $data['choices'][0] ?? null;
                if (!is_array($choice)) {
                    return true;
                }
                $finish = $choice['finish_reason'] ?? null;
                if ($finish === 'length') {
                    $result->stop = Result::LENGTH;
                } elseif ($finish === 'content_filter') {
                    $result->stop = Result::REFUSAL;
                }
                // Gestreamt steht der Text in "delta", ohne Streaming in "message".
                $text = self::text($choice['delta']['content'] ?? ($choice['message']['content'] ?? null));
                if ($text !== '') {
                    $result->text .= $text;

                    return $onText($text);
                }

                return true;
            }, $onIdle);
        } catch (ProviderException $e) {
            // Azure und andere melden einen Inhaltsfilter als 400 mit dem Code "content_filter".
            if ($e->kind === ProviderException::BAD_REQUEST && str_contains($e->getMessage(), 'content_filter')) {
                throw new ProviderException(ProviderException::BLOCKED, $e->getMessage(), $e->status);
            }
            throw $e;
        }

        if (!$complete) {
            $result->stop = Result::ABORTED;
        }

        return $result;
    }

    /** Inhalt ist meist eine Zeichenkette, bei manchen Anbietern eine Liste von Textteilen. */
    private static function text(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }
        $text = '';
        foreach (is_array($content) ? $content : [] as $part) {
            if (is_array($part) && ($part['type'] ?? '') === 'text' && is_string($part['text'] ?? null)) {
                $text .= $part['text'];
            }
        }

        return $text;
    }
}
