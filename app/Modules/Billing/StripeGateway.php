<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Billing;

/**
 * Alles, was das Modul bei Stripe aufruft. Die echte Umsetzung ist HttpStripeGateway; in Tests
 * lässt sich eine Attrappe im Container unter diesem Namen binden (siehe Billing::stripe()).
 *
 * Jede Methode liefert das dekodierte Stripe-Objekt und wirft StripeException bei Fehlern.
 */
interface StripeGateway
{
    /** GET /v1/balance – prüft nur, ob der Schlüssel angenommen wird. */
    public function ping(): array;

    /** POST /v1/customers */
    public function createCustomer(array $params, string $idempotencyKey): array;

    /** POST /v1/checkout/sessions */
    public function createCheckoutSession(array $params, string $idempotencyKey): array;

    /** POST /v1/billing_portal/sessions */
    public function createPortalSession(string $customerId, string $returnUrl): array;

    /** POST /v1/subscriptions/:id */
    public function updateSubscription(string $subscriptionId, array $params, string $idempotencyKey): array;

    /** DELETE /v1/subscriptions/:id – beendet das Abo sofort. */
    public function cancelSubscription(string $subscriptionId, string $idempotencyKey): array;
}
