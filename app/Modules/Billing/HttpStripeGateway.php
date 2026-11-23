<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Billing;

use Illuminate\Support\Facades\Http;

/**
 * Spricht die Stripe-REST-API direkt an (kein SDK). Fester Host, feste API-Version, https, keine
 * Weiterleitungen, Timeout, Idempotency-Key bei jedem schreibenden Aufruf.
 */
class HttpStripeGateway implements StripeGateway
{
    public const BASE = 'https://api.stripe.com';
    public const API_VERSION = '2026-11-18.endive';

    private const TIMEOUT = 15;
    private const CONNECT_TIMEOUT = 5;

    public function __construct(private string $secretKey)
    {
    }

    public function ping(): array
    {
        return $this->request('GET', '/v1/balance');
    }

    public function createCustomer(array $params, string $idempotencyKey): array
    {
        return $this->request('POST', '/v1/customers', $params, $idempotencyKey);
    }

    public function createCheckoutSession(array $params, string $idempotencyKey): array
    {
        return $this->request('POST', '/v1/checkout/sessions', $params, $idempotencyKey);
    }

    public function createPortalSession(string $customerId, string $returnUrl): array
    {
        return $this->request('POST', '/v1/billing_portal/sessions', [
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ], 'techtyl-portal-' . bin2hex(random_bytes(12)));
    }

    public function updateSubscription(string $subscriptionId, array $params, string $idempotencyKey): array
    {
        return $this->request('POST', '/v1/subscriptions/' . self::id($subscriptionId), $params, $idempotencyKey);
    }

    public function cancelSubscription(string $subscriptionId, string $idempotencyKey): array
    {
        return $this->request('DELETE', '/v1/subscriptions/' . self::id($subscriptionId), [], $idempotencyKey);
    }

    /** Stripe-IDs bestehen nur aus Buchstaben, Ziffern und Unterstrich; alles andere kommt nicht in den Pfad. */
    private static function id(string $id): string
    {
        if (preg_match('/^[A-Za-z0-9_]{3,120}$/', $id) !== 1) {
            throw new StripeException('Ungültige Stripe-ID.');
        }

        return $id;
    }

    /** @param array<string, mixed> $params */
    private function request(string $method, string $path, array $params = [], ?string $idempotencyKey = null): array
    {
        if ($this->secretKey === '') {
            throw new StripeException('Kein Stripe-Schlüssel hinterlegt.');
        }
        if (preg_match('#^/v1/[a-z_]+(/[A-Za-z0-9_]+)*$#', $path) !== 1) {
            throw new StripeException('Unerlaubter Stripe-Pfad.');
        }

        $headers = ['Stripe-Version' => self::API_VERSION];
        if ($method !== 'GET') {
            if ($idempotencyKey === null || $idempotencyKey === '') {
                throw new StripeException('Schreibender Stripe-Aufruf ohne Idempotency-Key.');
            }
            $headers['Idempotency-Key'] = substr($idempotencyKey, 0, 200);
        }

        $client = Http::withToken($this->secretKey)
            ->withHeaders($headers)
            ->acceptJson()
            ->asForm()
            ->timeout(self::TIMEOUT)
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->withOptions(['allow_redirects' => false]);

        $url = self::BASE . $path;
        try {
            $response = match ($method) {
                'GET' => $client->get($url, self::normalize($params)),
                'POST' => $client->post($url, self::normalize($params)),
                'DELETE' => $client->delete($url, self::normalize($params)),
                default => throw new StripeException('Unerlaubte Methode.'),
            };
        } catch (StripeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Die Meldung der Verbindung kann die Adresse enthalten, aber nie den Schlüssel (Header).
            throw new StripeException('Stripe nicht erreichbar: ' . class_basename($e));
        }

        $body = $response->json();
        if (!$response->successful() || !is_array($body)) {
            $error = is_array($body) && is_array($body['error'] ?? null) ? $body['error'] : [];
            throw new StripeException(
                'Stripe-Fehler ' . $response->status() . ': ' . substr((string) ($error['message'] ?? 'ohne Meldung'), 0, 300),
                $response->status(),
                (string) ($error['code'] ?? ($error['type'] ?? ''))
            );
        }

        return $body;
    }

    /**
     * Stripe erwartet Wahrheitswerte als "true"/"false" (http_build_query würde 1/0 senden).
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private static function normalize(array $params): array
    {
        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $params[$key] = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $params[$key] = self::normalize($value);
            }
        }

        return $params;
    }
}
