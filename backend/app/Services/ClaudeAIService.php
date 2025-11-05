<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeAIService
{
    private string $apiKey;
    private string $model;
    private int $maxTokens;

    public function __construct()
    {
        $this->apiKey = config('services.claude.api_key');
        $this->model = config('services.claude.model', 'claude-3-5-sonnet-20241022');
        $this->maxTokens = config('services.claude.max_tokens', 4096);
    }

    /**
     * Ask Claude AI for help with server creation
     */
    public function helpWithServerCreation(array $context): array
    {
        $prompt = $this->buildServerCreationPrompt($context);
        return $this->sendMessage($prompt);
    }

    /**
     * Get AI suggestions for server configuration
     */
    public function getServerConfigSuggestions(string $gameType, array $requirements = []): array
    {
        $prompt = "Als Serverexperte, schlage optimale Konfigurationen für einen {$gameType}-Server vor.\n\n";

        if (!empty($requirements)) {
            $prompt .= "Anforderungen:\n";
            foreach ($requirements as $key => $value) {
                $prompt .= "- {$key}: {$value}\n";
            }
        }

        $prompt .= "\nBitte gib Empfehlungen für:\n";
        $prompt .= "1. CPU-Kerne\n";
        $prompt .= "2. RAM in MB\n";
        $prompt .= "3. Disk Space in MB\n";
        $prompt .= "4. Netzwerk-Limits\n";
        $prompt .= "5. Zusätzliche Einstellungen\n\n";
        $prompt .= "Antwort als JSON im Format: {\"cpu\": number, \"memory\": number, \"disk\": number, \"network_rx\": number, \"network_tx\": number, \"explanation\": \"string\"}";

        $response = $this->sendMessage($prompt);

        if ($response['success']) {
            try {
                $content = $response['message'];
                // Extract JSON from response
                if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                    $config = json_decode($matches[0], true);
                    if ($config) {
                        return [
                            'success' => true,
                            'config' => $config
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to parse AI response', ['error' => $e->getMessage()]);
            }
        }

        return [
            'success' => false,
            'error' => 'Could not generate configuration'
        ];
    }

    /**
     * Get AI help for troubleshooting
     */
    public function helpTroubleshoot(string $issue, array $serverInfo = []): array
    {
        $prompt = "Ein Benutzer hat folgendes Problem mit seinem Server:\n\n{$issue}\n\n";

        if (!empty($serverInfo)) {
            $prompt .= "Server-Informationen:\n";
            $prompt .= json_encode($serverInfo, JSON_PRETTY_PRINT) . "\n\n";
        }

        $prompt .= "Bitte gib konkrete Lösungsvorschläge in verständlicher Sprache.";

        return $this->sendMessage($prompt);
    }

    /**
     * Send a message to Claude AI
     */
    private function sendMessage(string $prompt): array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message' => $data['content'][0]['text'] ?? '',
                    'usage' => $data['usage'] ?? null
                ];
            }

            Log::error('Claude API error', ['response' => $response->body()]);
            return [
                'success' => false,
                'error' => 'API request failed'
            ];

        } catch (\Exception $e) {
            Log::error('Claude API exception', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Build prompt for server creation assistance
     */
    private function buildServerCreationPrompt(array $context): string
    {
        $prompt = "Du bist ein hilfreicher Assistent für Server-Management. ";
        $prompt .= "Ein Benutzer möchte einen Server erstellen.\n\n";

        if (isset($context['game_type'])) {
            $prompt .= "Spiel/Server-Typ: {$context['game_type']}\n";
        }

        if (isset($context['players'])) {
            $prompt .= "Erwartete Spieleranzahl: {$context['players']}\n";
        }

        if (isset($context['question'])) {
            $prompt .= "Frage: {$context['question']}\n\n";
        }

        $prompt .= "Bitte gib hilfreiche Empfehlungen und erkläre die wichtigsten Schritte.";

        return $prompt;
    }

    /**
     * Generate server name suggestions
     */
    public function suggestServerNames(string $gameType, int $count = 5): array
    {
        $prompt = "Generiere {$count} kreative und passende Namen für einen {$gameType}-Server. ";
        $prompt .= "Die Namen sollten professionell aber einprägsam sein. ";
        $prompt .= "Antwort als JSON-Array: [\"name1\", \"name2\", ...]";

        $response = $this->sendMessage($prompt);

        if ($response['success']) {
            try {
                if (preg_match('/\[[\s\S]*\]/', $response['message'], $matches)) {
                    $names = json_decode($matches[0], true);
                    if ($names && is_array($names)) {
                        return [
                            'success' => true,
                            'names' => $names
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to parse server names', ['error' => $e->getMessage()]);
            }
        }

        return [
            'success' => false,
            'error' => 'Could not generate names'
        ];
    }
}
