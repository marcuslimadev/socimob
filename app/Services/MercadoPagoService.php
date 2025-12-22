<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class MercadoPagoService
{
    private Client $client;

    public function __construct()
    {
        $accessToken = env('MERCADO_PAGO_ACCESS_TOKEN');

        $this->client = new Client([
            'base_uri' => 'https://api.mercadopago.com',
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function createOrUpdateCustomer(array $payload): array
    {
        try {
            $response = $this->client->post('/v1/customers', [
                'json' => [
                    'email' => $payload['email'],
                    'first_name' => $payload['first_name'] ?? null,
                    'last_name' => $payload['last_name'] ?? null,
                    'phone' => $this->formatPhonePayload($payload['phone'] ?? null),
                    'identification' => $payload['identification'] ?? null,
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Mercado Pago customer error', [
                'message' => $e->getMessage(),
                'response' => $e->getResponse()?->getBody(),
            ]);

            throw $e;
        }
    }

    public function createCardToken(array $payload): array
    {
        try {
            $response = $this->client->post('/v1/card_tokens', [
                'json' => [
                    'card_number' => $payload['card_number'],
                    'security_code' => $payload['security_code'],
                    'expiration_month' => (int) $payload['expiration_month'],
                    'expiration_year' => (int) $payload['expiration_year'],
                    'cardholder' => [
                        'name' => $payload['holder_name'],
                        'identification' => $payload['identification'] ?? [
                            'type' => 'CPF',
                            'number' => $payload['document'] ?? '00000000000',
                        ],
                    ],
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Mercado Pago card token error', [
                'message' => $e->getMessage(),
                'response' => $e->getResponse()?->getBody(),
            ]);

            throw $e;
        }
    }

    public function createPreapproval(array $payload): array
    {
        try {
            $response = $this->client->post('/preapproval', [
                'json' => [
                    'reason' => $payload['reason'],
                    'payer_email' => $payload['payer_email'],
                    'card_token_id' => $payload['card_token_id'],
                    'back_url' => $payload['back_url'],
                    'auto_recurring' => [
                        'frequency' => 1,
                        'frequency_type' => 'months',
                        'transaction_amount' => (float) $payload['amount'],
                        'currency_id' => 'BRL',
                    ],
                    'status' => 'authorized',
                    'external_reference' => $payload['external_reference'] ?? null,
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Mercado Pago preapproval error', [
                'message' => $e->getMessage(),
                'response' => $e->getResponse()?->getBody(),
            ]);

            throw $e;
        }
    }

    public function cancelPreapproval(string $preapprovalId): array
    {
        try {
            $response = $this->client->put("/preapproval/{$preapprovalId}", [
                'json' => ['status' => 'cancelled'],
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Mercado Pago cancel error', [
                'message' => $e->getMessage(),
                'response' => $e->getResponse()?->getBody(),
            ]);

            throw $e;
        }
    }

    public function updatePreapprovalCard(string $preapprovalId, string $cardTokenId): array
    {
        try {
            $response = $this->client->put("/preapproval/{$preapprovalId}", [
                'json' => [
                    'card_token_id' => $cardTokenId,
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Mercado Pago update card error', [
                'message' => $e->getMessage(),
                'response' => $e->getResponse()?->getBody(),
            ]);

            throw $e;
        }
    }

    private function formatPhonePayload(?string $phone): ?array
    {
        if (!$phone) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);

        return [
            'area_code' => substr($digits, 0, 2) ?: null,
            'number' => substr($digits, 2) ?: null,
        ];
    }
}
