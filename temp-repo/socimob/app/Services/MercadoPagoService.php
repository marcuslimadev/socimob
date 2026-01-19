<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Serviço de integração com Mercado Pago
 * Documentação: https://www.mercadopago.com.br/developers/pt/docs/checkout-api/integration-configuration/integrate-with-pix
 */
class MercadoPagoService
{
    private $accessToken;
    private $baseUrl;

    public function __construct()
    {
        $this->accessToken = env('MERCADOPAGO_ACCESS_TOKEN');
        $this->baseUrl = env('MERCADOPAGO_BASE_URL', 'https://api.mercadopago.com');
        
        if (empty($this->accessToken)) {
            Log::warning('[MercadoPago] Access token não configurado em .env');
        }
    }

    /**
     * Criar pagamento PIX
     * 
     * @param array $data Dados do pagamento
     * @return array Dados do pagamento criado (id, qrcode, qrcode_base64)
     */
    public function criarPagamentoPix(array $data)
    {
        try {
            Log::info('[MercadoPago] Criando pagamento PIX', [
                'valor' => $data['transaction_amount'],
                'descricao' => $data['description']
            ]);

            if (empty($this->accessToken)) {
                throw new \Exception('Mercado Pago não configurado - configure MERCADOPAGO_ACCESS_TOKEN no .env');
            }

            $payload = [
                'transaction_amount' => $data['transaction_amount'],
                'description' => $data['description'],
                'payment_method_id' => 'pix',
                'payer' => [
                    'email' => $data['payer']['email'] ?? 'cliente@exclusiva.com',
                    'first_name' => $data['payer']['first_name'] ?? 'Cliente',
                    'identification' => [
                        'type' => 'CPF',
                        'number' => $data['payer']['cpf'] ?? '00000000000'
                    ]
                ],
                'external_reference' => $data['external_reference'] ?? null,
                'notification_url' => env('APP_URL') . '/api/webhooks/mercadopago'
            ];

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->accessToken}",
                'Content-Type' => 'application/json',
                'X-Idempotency-Key' => uniqid('mp_', true)
            ])->post("{$this->baseUrl}/v1/payments", $payload);

            if (!$response->successful()) {
                Log::error('[MercadoPago] Erro ao criar pagamento', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                throw new \Exception('Erro ao criar pagamento: ' . $response->body());
            }

            $result = $response->json();

            // Extrair dados do PIX
            $pixData = [
                'id' => $result['id'],
                'status' => $result['status'],
                'status_detail' => $result['status_detail'] ?? null,
                'qrcode' => $result['point_of_interaction']['transaction_data']['qr_code'] ?? null,
                'qrcode_base64' => $result['point_of_interaction']['transaction_data']['qr_code_base64'] ?? null,
                'ticket_url' => $result['point_of_interaction']['transaction_data']['ticket_url'] ?? null
            ];

            Log::info('[MercadoPago] Pagamento PIX criado', [
                'payment_id' => $pixData['id'],
                'status' => $pixData['status']
            ]);

            return $pixData;

        } catch (\Exception $e) {
            Log::error('[MercadoPago] Exceção ao criar pagamento PIX', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Consultar status de um pagamento
     * 
     * @param string $paymentId ID do pagamento no Mercado Pago
     * @return array Dados do pagamento
     */
    public function consultarPagamento($paymentId)
    {
        try {
            Log::info('[MercadoPago] Consultando pagamento', ['payment_id' => $paymentId]);

            if (empty($this->accessToken)) {
                throw new \Exception('Mercado Pago não configurado');
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->accessToken}",
            ])->get("{$this->baseUrl}/v1/payments/{$paymentId}");

            if (!$response->successful()) {
                Log::error('[MercadoPago] Erro ao consultar pagamento', [
                    'payment_id' => $paymentId,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                throw new \Exception('Erro ao consultar pagamento');
            }

            $result = $response->json();

            return [
                'id' => $result['id'],
                'status' => $result['status'],
                'status_detail' => $result['status_detail'] ?? null,
                'transaction_amount' => $result['transaction_amount'],
                'date_created' => $result['date_created'],
                'date_approved' => $result['date_approved'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error('[MercadoPago] Exceção ao consultar pagamento', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Processar webhook do Mercado Pago
     * 
     * @param array $data Dados do webhook
     * @return array Dados processados
     */
    public function processarWebhook(array $data)
    {
        try {
            Log::info('[MercadoPago] Webhook recebido', ['data' => $data]);

            $type = $data['type'] ?? null;
            $action = $data['action'] ?? null;

            // Verificar se é notificação de pagamento
            if ($type === 'payment') {
                $paymentId = $data['data']['id'] ?? null;
                
                if ($paymentId) {
                    // Consultar dados atualizados do pagamento
                    return $this->consultarPagamento($paymentId);
                }
            }

            return [
                'type' => $type,
                'action' => $action,
                'processed' => false
            ];

        } catch (\Exception $e) {
            Log::error('[MercadoPago] Erro ao processar webhook', [
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Cancelar pagamento
     * 
     * @param string $paymentId ID do pagamento
     * @return bool Sucesso
     */
    public function cancelarPagamento($paymentId)
    {
        try {
            Log::info('[MercadoPago] Cancelando pagamento', ['payment_id' => $paymentId]);

            if (empty($this->accessToken)) {
                throw new \Exception('Mercado Pago não configurado');
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->accessToken}",
                'Content-Type' => 'application/json'
            ])->put("{$this->baseUrl}/v1/payments/{$paymentId}", [
                'status' => 'cancelled'
            ]);

            if (!$response->successful()) {
                Log::error('[MercadoPago] Erro ao cancelar pagamento', [
                    'payment_id' => $paymentId,
                    'status' => $response->status()
                ]);
                
                return false;
            }

            Log::info('[MercadoPago] Pagamento cancelado', ['payment_id' => $paymentId]);
            return true;

        } catch (\Exception $e) {
            Log::error('[MercadoPago] Exceção ao cancelar pagamento', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }
}
