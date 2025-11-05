<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Azure OpenAI Service für Techtyl
 *
 * Verwendet Azure OpenAI statt Claude API
 */
class AzureOpenAIService
{
    private string $apiKey;
    private string $endpoint;
    private string $deployment;
    private string $apiVersion;

    public function __construct()
    {
        // ========================================
        // 🔷 AZURE OPENAI - KONFIGURATION
        // ========================================

        // Credentials aus .env laden (für Sicherheit)
        $this->apiKey = env('AZURE_OPENAI_API_KEY');
        $this->endpoint = env('AZURE_OPENAI_ENDPOINT');
        $this->deployment = env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o');
        $this->apiVersion = env('AZURE_OPENAI_API_VERSION', '2024-02-15-preview');

        // Validierung
        if (empty($this->apiKey) || empty($this->endpoint)) {
            throw new \Exception('Azure OpenAI credentials not configured. Please set AZURE_OPENAI_API_KEY and AZURE_OPENAI_ENDPOINT in .env file.');
        }
    }

    /**
     * Hilfe bei Server-Erstellung
     */
    public function helpWithServerCreation(array $context): array
    {
        $prompt = $this->buildServerCreationPrompt($context);
        return $this->sendMessage($prompt);
    }

    /**
     * Server-Konfigurationsempfehlungen
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
        $prompt .= "1. CPU-Kerne (1-8)\n";
        $prompt .= "2. RAM in MB (512-16384)\n";
        $prompt .= "3. Disk Space in MB (1024-102400)\n";
        $prompt .= "4. Netzwerk-Limits (optional)\n";
        $prompt .= "5. Kurze Erklärung\n\n";
        $prompt .= "Antwort NUR als valides JSON im Format:\n";
        $prompt .= '{"cpu": 2, "memory": 2048, "disk": 10240, "network_rx": 1000, "network_tx": 1000, "explanation": "Begründung hier"}';

        $response = $this->sendMessage($prompt);

        if ($response['success']) {
            try {
                $content = $response['message'];

                // Versuche JSON zu extrahieren
                if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $content, $matches)) {
                    $config = json_decode($matches[0], true);
                    if ($config && isset($config['cpu']) && isset($config['memory']) && isset($config['disk'])) {
                        return [
                            'success' => true,
                            'config' => $config
                        ];
                    }
                }

                // Fallback: Parse aus Text
                Log::warning('Could not parse JSON, trying text parsing', ['content' => $content]);

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
     * Troubleshooting-Hilfe
     */
    public function helpTroubleshoot(string $issue, array $serverInfo = []): array
    {
        $prompt = "Ein Benutzer hat folgendes Problem mit seinem Server:\n\n{$issue}\n\n";

        if (!empty($serverInfo)) {
            $prompt .= "Server-Informationen:\n";
            $prompt .= json_encode($serverInfo, JSON_PRETTY_PRINT) . "\n\n";
        }

        $prompt .= "Bitte gib konkrete, praktische Lösungsvorschläge in verständlicher deutscher Sprache.\n";
        $prompt .= "Strukturiere die Antwort mit Bullet Points und nummerierten Schritten.";

        return $this->sendMessage($prompt);
    }

    /**
     * Server-Namen vorschlagen
     */
    public function suggestServerNames(string $gameType, int $count = 5): array
    {
        $prompt = "Generiere {$count} kreative und passende Namen für einen {$gameType}-Server.\n";
        $prompt .= "Die Namen sollten:\n";
        $prompt .= "- Professionell aber einprägsam sein\n";
        $prompt .= "- Keine Sonderzeichen außer - und _ enthalten\n";
        $prompt .= "- 3-30 Zeichen lang sein\n\n";
        $prompt .= "Antwort NUR als JSON-Array: [\"Name1\", \"Name2\", \"Name3\", ...]";

        $response = $this->sendMessage($prompt);

        if ($response['success']) {
            try {
                if (preg_match('/\[[\s\S]*?\]/s', $response['message'], $matches)) {
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

    /**
     * Nachricht an Azure OpenAI senden
     */
    private function sendMessage(string $prompt): array
    {
        try {
            $url = "{$this->endpoint}/openai/deployments/{$this->deployment}/chat/completions?api-version={$this->apiVersion}";

            $response = Http::withHeaders([
                'api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($url, [
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Du bist ein hilfreicher Assistent für Server-Management und Gaming-Server-Konfiguration. Antworte auf Deutsch, präzise und hilfreich.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 1000,
                'top_p' => 0.95,
                'frequency_penalty' => 0,
                'presence_penalty' => 0,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['choices'][0]['message']['content'])) {
                    return [
                        'success' => true,
                        'message' => $data['choices'][0]['message']['content'],
                        'usage' => $data['usage'] ?? null
                    ];
                }
            }

            Log::error('Azure OpenAI API error', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => 'API request failed: ' . $response->status()
            ];

        } catch (\Exception $e) {
            Log::error('Azure OpenAI API exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Prompt für Server-Erstellung erstellen
     */
    private function buildServerCreationPrompt(array $context): string
    {
        $prompt = "Du bist ein hilfreicher Assistent für Server-Management.\n";
        $prompt .= "Ein Benutzer möchte einen Server erstellen.\n\n";

        if (isset($context['game_type'])) {
            $prompt .= "Spiel/Server-Typ: {$context['game_type']}\n";
        }

        if (isset($context['players'])) {
            $prompt .= "Erwartete Spieleranzahl: {$context['players']}\n";
        }

        if (isset($context['question'])) {
            $prompt .= "\nFrage des Benutzers: {$context['question']}\n\n";
        }

        $prompt .= "Bitte gib hilfreiche Empfehlungen und erkläre die wichtigsten Schritte auf Deutsch.";

        return $prompt;
    }
}
