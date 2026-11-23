<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant\Providers;

/**
 * Google Gemini API (POST /v1beta/models/<modell>:streamGenerateContent?alt=sse).
 * Anmeldung ueber die Kopfzeile x-goog-api-key (der Schluessel steht nie in der Adresse),
 * Systemnachricht in "systemInstruction", Rollen "user" und "model",
 * Obergrenze in generationConfig.maxOutputTokens.
 */
class GeminiDriver implements Driver
{
    private const REFUSED = ['SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT', 'SPII', 'LANGUAGE'];

    public function __construct(
        private Transport $transport,
        private string $key,
        private string $model,
        private string $base = 'https://generativelanguage.googleapis.com/v1beta',
    ) {
    }

    public function chat(string $system, array $messages, int $maxTokens, callable $onText, callable $onIdle): Result
    {
        $contents = [];
        foreach ($messages as $message) {
            $contents[] = [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message['content']]],
            ];
        }
        $body = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => $contents,
            'generationConfig' => ['maxOutputTokens' => $maxTokens],
        ];
        $url = $this->base . '/models/' . rawurlencode($this->model) . ':streamGenerateContent?alt=sse';

        $result = new Result();
        $complete = $this->transport->stream($url, ['x-goog-api-key' => $this->key], $body, function (string $event, array $data) use ($result, $onText): bool {
            if (isset($data['error'])) {
                $code = is_array($data['error']) ? ($data['error']['code'] ?? 0) : 0;
                $detail = Transport::errorDetail((string) json_encode($data));
                throw is_int($code) && $code >= 400
                    ? ProviderException::fromStatus($code, $detail)
                    : new ProviderException(ProviderException::UNAVAILABLE, $detail);
            }
            if (isset($data['promptFeedback']['blockReason'])) {
                throw new ProviderException(ProviderException::BLOCKED, 'blockReason ' . (string) $data['promptFeedback']['blockReason']);
            }
            if (is_array($data['usageMetadata'] ?? null)) {
                $usage = $data['usageMetadata'];
                $result->tokensIn = is_int($usage['promptTokenCount'] ?? null) ? $usage['promptTokenCount'] : $result->tokensIn;
                $result->tokensOut = is_int($usage['candidatesTokenCount'] ?? null) ? $usage['candidatesTokenCount'] : $result->tokensOut;
            }
            $candidate = $data['candidates'][0] ?? null;
            if (!is_array($candidate)) {
                return true;
            }
            $finish = $candidate['finishReason'] ?? null;
            if ($finish === 'MAX_TOKENS') {
                $result->stop = Result::LENGTH;
            } elseif (in_array($finish, self::REFUSED, true)) {
                $result->stop = Result::REFUSAL;
            }
            $text = '';
            foreach (is_array($candidate['content']['parts'] ?? null) ? $candidate['content']['parts'] : [] as $part) {
                // Teile mit "thought" sind Denk-Zusammenfassungen und gehoeren nicht in die Antwort.
                if (is_array($part) && is_string($part['text'] ?? null) && empty($part['thought'])) {
                    $text .= $part['text'];
                }
            }
            if ($text !== '') {
                $result->text .= $text;

                return $onText($text);
            }

            return true;
        }, $onIdle);

        if (!$complete) {
            $result->stop = Result::ABORTED;
        }

        return $result;
    }
}
