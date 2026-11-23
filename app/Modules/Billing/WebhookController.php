<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Http\Controllers\Controller;

/**
 * POST /extensions/techtyl/billing/webhook – Ziel für Stripe. Ohne Sitzung, ohne CSRF; die
 * einzige Berechtigung ist die Signatur über den rohen Request-Body.
 */
class WebhookController extends Controller
{
    private const MAX_BYTES = 1048576;

    public function handle(Request $request): JsonResponse
    {
        $payload = (string) $request->getContent();
        $secret = Config::webhookSecret();

        if ($secret === '' || strlen($payload) === 0 || strlen($payload) > self::MAX_BYTES
            || !Signature::valid($payload, $request->header('Stripe-Signature'), $secret)) {
            return new JsonResponse(['error' => 'Invalid signature.'], 400);
        }

        $event = json_decode($payload, true);
        if (!is_array($event)) {
            return new JsonResponse(['error' => 'Invalid payload.'], 400);
        }

        try {
            $outcome = Events::process($event);
        } catch (\Throwable $e) {
            // Nichts wurde verbucht (Transaktion zurückgerollt); Stripe stellt erneut zu.
            Log::error('techtyl billing: Ereignis konnte nicht verarbeitet werden', [
                'event' => is_string($event['id'] ?? null) ? substr($event['id'], 0, 100) : null,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(['error' => 'Processing failed.'], 500);
        }

        return new JsonResponse(['received' => true, 'result' => $outcome]);
    }
}
