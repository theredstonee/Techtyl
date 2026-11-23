<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant\Providers;

/**
 * Azure OpenAI. Anmeldung ueber die Kopfzeile "api-key". Ohne API-Version wird die v1-API
 * genutzt (https://<ressource>/openai/v1/chat/completions, Deployment-Name im Feld "model"),
 * mit API-Version der aeltere Pfad /openai/deployments/<deployment>/chat/completions.
 */
class AzureDriver extends OpenAiCompatibleDriver
{
    public function __construct(Transport $transport, string $endpoint, string $key, string $deployment, string $apiVersion = '')
    {
        $url = $apiVersion === ''
            ? $endpoint . '/openai/v1/chat/completions'
            : $endpoint . '/openai/deployments/' . rawurlencode($deployment) . '/chat/completions?api-version=' . rawurlencode($apiVersion);

        parent::__construct($transport, $url, ['api-key' => $key], $deployment, 'max_completion_tokens', true);
    }
}
