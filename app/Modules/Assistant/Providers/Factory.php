<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant\Providers;

use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant\Config;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant\Endpoint;

/** Baut den Treiber fuer einen Anbieter aus den Admin-Einstellungen. */
class Factory
{
    /** @throws ProviderException */
    public static function make(string $provider): Driver
    {
        $definition = Config::PROVIDERS[$provider] ?? null;
        if ($definition === null || !Config::ready($provider)) {
            throw new ProviderException(ProviderException::BAD_REQUEST, 'Anbieter nicht eingerichtet: ' . $provider);
        }
        if ($definition['family'] === 'demo') {
            return new DemoDriver();
        }

        $settings = Config::providerSettings($provider);
        $key = Config::secret($provider);
        $model = Config::model($provider);
        $transport = new Transport(Config::int('timeout'), [$key]);

        try {
            return match ($definition['family']) {
                'anthropic' => new AnthropicDriver($transport, $key, $model, (string) Config::all()['effort'], (bool) Config::all()['fallbacks'], $definition['base']),
                'gemini' => new GeminiDriver($transport, $key, $model, $definition['base']),
                'azure' => new AzureDriver($transport, Endpoint::azure((string) $settings['endpoint']), $key, $model, (string) $settings['api_version']),
                default => new OpenAiCompatibleDriver(
                    $transport,
                    ($provider === 'custom'
                        ? Endpoint::custom((string) $settings['url'], (bool) Config::all()['allow_private'])
                        : $definition['base']) . '/chat/completions',
                    $key !== '' ? ['Authorization' => 'Bearer ' . $key] : [],
                    $model,
                    $definition['token_param'],
                    in_array($provider, ['openai', 'groq'], true),
                ),
            };
        } catch (\InvalidArgumentException $e) {
            // Die Adresse genuegt den Regeln nicht mehr (z. B. geaenderter DNS-Eintrag oder Schalter).
            throw new ProviderException(ProviderException::ADDRESS, $e->getMessage());
        }
    }
}
