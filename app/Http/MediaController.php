<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Http;

use Pterodactyl\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Media;

/**
 * Liefert hochgeladene Bilder aus. Die Adresse enthaelt nur das zufaellige Kennzeichen der Datei;
 * der Inhaltstyp steht fest und wird nicht vom Browser erraten.
 */
class MediaController extends Controller
{
    public function show(string $file): BinaryFileResponse
    {
        $found = Media::find($file);
        if ($found === null) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($found['path'], 200, [
            'Content-Type' => $found['mime'],
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
        // Der Name aendert sich mit jedem Upload, der Inhalt unter einem Namen nie.
        $response->setCache(['public' => true, 'max_age' => 31536000, 'immutable' => true]);

        return $response;
    }
}
