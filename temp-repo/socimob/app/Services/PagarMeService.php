<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Tenant;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class PagarMeService
{
    protected $client;
    protected $apiKey;
    protected $baseUrl = 'https://api.pagar.me/core/v5';

    public function __construct()
    {
        $this->apiKey = env('PAGAR_ME_API_KEY');
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':'),
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Criar cliente no Pagar.me
     *
     * @param  array  $data
     * @return array
     */
    public function createCustomer(array $data): array
    {
        try {
            $response = $this->client->post('/customers', [
                'json' => [
                    'name' => $data['name'] ?? '',
                    'email' => $data['email'] ?? '',
                    'document' => $data['document'] ?? '',
                    'type' => 'individual',
                    'phones' => [
                        'mobile_phone' => [
                            'country_code' => '55',
                            'number' => $this->formatPhone($data['phone'] ?? ''),
                            'area_code' => '11',
                        ],
                    ],
                    'address' => [
                        'street' => $data['street'] ?? '',
                        'number' => $data['number'] ?? '',
                        'complement' => $data['complement'] ?? '',
                        'zip_code' => $this->formatZipCode($data['zip_code'] ?? ''),
                        'city' => $data['city'] ?? '',
                        'state' => $data['state'] ?? '',
                        'country' => 'BR',
                    ],
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Pagar.me create customer error', [
                'message' => $e->getMessage(),
                'response' => $e->getResponse()?->getBody(),
            ]);

            throw $e;
        }
    }

    /**
     * Criar cartão de crédito
     *
     * @param  string  $customerId
     * @param  array  $cardData
     * @return array
     */
    public function createCard(string $customerId, array $cardData): array
    {
        try {
            $response = $this->client->post("/customers/{$customerId}/cards", [
                'json' => [
                    'number' => $cardData['number'],
                    'holder_name' => $cardData['holder_name'],
                    'exp_month' => (int)$cardData['exp_month'],
                    'exp_year' => (int)$cardData['exp_year'],
                    'cvv' => $cardData['cvv'],
                    'billing_address' => [
                        'street' => $cardData['street'] ?? '',
                        'number' => $cardData['number_address'] ?? '',
                        'zip_code' => $this->formatZipCode($cardData['zip_code'] ?? ''),
                        'city' => $cardData['city'] ?? '',
                        'state' => $cardData['state'] ?? '',
                        'country' => 'BR',
                    ],
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Pagar.me create card error', [
                'message' => $e->getMessage(),
                'response' => $e->getResponse()?->getBody(),
            ]);

            throw $e;
        }
    }

    /**
     * Criar assinatura
     *
     * @param  string  $customerId
     * @param  string  $cardId
     * @param  array  $planData
     * @return array
     */
    public function createSubscription(string $customerId, string $cardId, array $planData): array
    {
        try {
            $interval = $planData['interval'] ?? 'month';
            $intervalCount = $planData['interval_count'] ?? 1;
            $amount = (int)($planData['amount'] * 100); // Converter para centavos

            $response = $this->client->post('/subscriptions', [
                'json' => [
                    'customer_id' => $customerId,
                    'card_id' => $cardId,
                    'plan_id' => $planData['plan_id'] ?? null,
                    'code' => $planData['code'] ?? 'subscription_' . time(),
                    'description' => $planData['description'] ?? '',
                    'statement_descriptor' => 'EXCLUSIVA LAR',
                    'billing_type' => 'credit_card',
                    'interval' => $interval,
                    'interval_count' => $intervalCount,
                    'billing_day' => 1,
                    'currency' => 'BRL',
                    'items' => [
                        [
                            'description' => $planData['description'] ?? '',
                            'pricing_scheme' => [
                                'scheme_type' => 'fixed',
                                'price' => $amount,
                            ],
                            'quantity' => 1,
                        ],
                    ],
                    'metadata' => $planData['metadata'] ?? [],
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Pagar.me create subscription error', [
                'message' => $e->getMessage(),
                'response' => $e->getResponse()?->getBody(),
            ]);

            throw $e;
        }
    }

    /**
     * Obter assinatura
     *
     * @param  string  $subscriptionId
     * @return array
     */
    public function getSubscription(string $subscriptionId): array
    {
        try {
            $response = $this->client->get("/subscriptions/{$subscriptionId}");
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Pagar.me get subscription error', [
                'message' => $e->getMessage(),
                'subscription_id' => $subscriptionId,
            ]);

            throw $e;
        }
    }

    /**
     * Cancelar assinatura
     *
     * @param  string  $subscriptionId
     * @return array
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        try {
            $response = $this->client->delete("/subscriptions/{$subscriptionId}");
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Pagar.me cancel subscription error', [
                'message' => $e->getMessage(),
                'subscription_id' => $subscriptionId,
            ]);

            throw $e;
        }
    }

    /**
     * Atualizar assinatura
     *
     * @param  string  $subscriptionId
     * @param  array  $data
     * @return array
     */
    public function updateSubscription(string $subscriptionId, array $data): array
    {
        try {
            $payload = [];

            if (isset($data['card_id'])) {
                $payload['card_id'] = $data['card_id'];
            }

            if (isset($data['plan_id'])) {
                $payload['plan_id'] = $data['plan_id'];
            }

            if (isset($data['billing_day'])) {
                $payload['billing_day'] = $data['billing_day'];
            }

            $response = $this->client->patch("/subscriptions/{$subscriptionId}", [
                'json' => $payload,
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Pagar.me update subscription error', [
                'message' => $e->getMessage(),
                'subscription_id' => $subscriptionId,
            ]);

            throw $e;
        }
    }

    /**
     * Processar webhook de pagamento
     *
     * @param  array  $payload
     * @return void
     */
    public function handleWebhook(array $payload): void
    {
        $event = $payload['type'] ?? null;
        $data = $payload['data'] ?? [];

        Log::info('Pagar.me webhook received', [
            'event' => $event,
            'subscription_id' => $data['subscription_id'] ?? null,
        ]);

        switch ($event) {
            case 'subscription.created':
                $this->handleSubscriptionCreated($data);
                break;

            case 'subscription.updated':
                $this->handleSubscriptionUpdated($data);
                break;

            case 'subscription.deleted':
                $this->handleSubscriptionDeleted($data);
                break;

            case 'charge.succeeded':
                $this->handleChargeSucceeded($data);
                break;

            case 'charge.failed':
                $this->handleChargeFailed($data);
                break;

            case 'charge.refunded':
                $this->handleChargeRefunded($data);
                break;

            default:
                Log::warning('Unknown webhook event', ['event' => $event]);
        }
    }

    /**
     * Lidar com assinatura criada
     */
    private function handleSubscriptionCreated(array $data): void
    {
        $subscriptionId = $data['id'] ?? null;
        $customerId = $data['customer_id'] ?? null;
        $status = $data['status'] ?? null;

        if (!$subscriptionId) {
            Log::warning('Subscription created webhook missing subscription_id');
            return;
        }

        // Buscar tenant pelo customer_id do Pagar.me
        $tenant = Tenant::where('pagar_me_customer_id', $customerId)->first();

        if (!$tenant) {
            Log::warning('Tenant not found for customer', ['customer_id' => $customerId]);
            return;
        }

        // Atualizar tenant com IDs do Pagar.me
        $tenant->update([
            'pagar_me_subscription_id' => $subscriptionId,
            'subscription_status' => $this->mapPagarMeStatus($status),
        ]);

        Log::info('Subscription created', [
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscriptionId,
        ]);
    }

    /**
     * Lidar com assinatura atualizada
     */
    private function handleSubscriptionUpdated(array $data): void
    {
        $subscriptionId = $data['id'] ?? null;
        $status = $data['status'] ?? null;

        if (!$subscriptionId) {
            Log::warning('Subscription updated webhook missing subscription_id');
            return;
        }

        $subscription = Subscription::where('pagar_me_subscription_id', $subscriptionId)->first();

        if (!$subscription) {
            Log::warning('Subscription not found', ['subscription_id' => $subscriptionId]);
            return;
        }

        $subscription->update([
            'status' => $this->mapPagarMeStatus($status),
        ]);

        Log::info('Subscription updated', [
            'subscription_id' => $subscriptionId,
            'status' => $status,
        ]);
    }

    /**
     * Lidar com assinatura deletada
     */
    private function handleSubscriptionDeleted(array $data): void
    {
        $subscriptionId = $data['id'] ?? null;

        if (!$subscriptionId) {
            Log::warning('Subscription deleted webhook missing subscription_id');
            return;
        }

        $subscription = Subscription::where('pagar_me_subscription_id', $subscriptionId)->first();

        if (!$subscription) {
            Log::warning('Subscription not found', ['subscription_id' => $subscriptionId]);
            return;
        }

        $subscription->cancel('Cancelada pelo Pagar.me');

        Log::info('Subscription deleted', ['subscription_id' => $subscriptionId]);
    }

    /**
     * Lidar com cobrança bem-sucedida
     */
    private function handleChargeSucceeded(array $data): void
    {
        $subscriptionId = $data['subscription_id'] ?? null;
        $chargeId = $data['id'] ?? null;

        if (!$subscriptionId) {
            Log::warning('Charge succeeded webhook missing subscription_id');
            return;
        }

        $subscription = Subscription::where('pagar_me_subscription_id', $subscriptionId)->first();

        if (!$subscription) {
            Log::warning('Subscription not found', ['subscription_id' => $subscriptionId]);
            return;
        }

        $subscription->markAsActive();

        Log::info('Charge succeeded', [
            'subscription_id' => $subscriptionId,
            'charge_id' => $chargeId,
        ]);
    }

    /**
     * Lidar com cobrança falhada
     */
    private function handleChargeFailed(array $data): void
    {
        $subscriptionId = $data['subscription_id'] ?? null;
        $chargeId = $data['id'] ?? null;

        if (!$subscriptionId) {
            Log::warning('Charge failed webhook missing subscription_id');
            return;
        }

        $subscription = Subscription::where('pagar_me_subscription_id', $subscriptionId)->first();

        if (!$subscription) {
            Log::warning('Subscription not found', ['subscription_id' => $subscriptionId]);
            return;
        }

        $subscription->markAsPastDue('Cobrança falhada no Pagar.me');

        Log::info('Charge failed', [
            'subscription_id' => $subscriptionId,
            'charge_id' => $chargeId,
        ]);
    }

    /**
     * Lidar com cobrança reembolsada
     */
    private function handleChargeRefunded(array $data): void
    {
        $subscriptionId = $data['subscription_id'] ?? null;
        $chargeId = $data['id'] ?? null;

        Log::info('Charge refunded', [
            'subscription_id' => $subscriptionId,
            'charge_id' => $chargeId,
        ]);
    }

    /**
     * Mapear status do Pagar.me para status local
     */
    private function mapPagarMeStatus(string $pagarMeStatus): string
    {
        return match($pagarMeStatus) {
            'active' => 'active',
            'pending' => 'active',
            'canceled' => 'canceled',
            'paused' => 'paused',
            default => 'active',
        };
    }

    /**
     * Formatar telefone
     */
    private function formatPhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }

    /**
     * Formatar CEP
     */
    private function formatZipCode(string $zipCode): string
    {
        return preg_replace('/\D/', '', $zipCode);
    }

    /**
     * Verificar webhook signature
     *
     * @param  string  $payload
     * @param  string  $signature
     * @return bool
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $hash = hash_hmac('sha256', $payload, $this->apiKey);
        return hash_equals($hash, $signature);
    }
}
