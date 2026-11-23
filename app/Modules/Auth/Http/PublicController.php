<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Http;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Pages;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\Config;

class PublicController extends Controller
{
    /** Was die Anmeldeseite wissen muss: welche Wege es gibt und der oeffentliche Site-Key. */
    public function config(): JsonResponse
    {
        $providers = [];
        foreach (Config::PROVIDERS as $key => $title) {
            if (Config::providerOn($key)) {
                $providers[] = ['key' => $key, 'title' => $title];
            }
        }

        return new JsonResponse([
            'registration' => Config::get('registration') === true,
            'turnstile' => Config::turnstileProtectsLogin() ? (string) Config::get('turnstile_site_key') : null,
            'providers' => $providers,
        ]);
    }

    public function theme(): Response
    {
        return response(Pages::themeCss(), 200, ['Content-Type' => 'text/css; charset=utf-8']);
    }

    public function notice(string $code): Response
    {
        abort_unless(isset(Pages::NOTICES[$code]), 404);

        return Pages::notice($code);
    }
}
