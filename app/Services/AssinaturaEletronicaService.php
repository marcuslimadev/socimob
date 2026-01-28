<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de integração com provedor de assinatura eletrônica
 * Suporta: Clicksign, Docusign
 */
class AssinaturaEletronicaService
{
    private $provider;
    private $apiKey;
    private $baseUrl;

    public function __construct()
    {
        $this->provider = env('ASSINATURA_PROVIDER', 'clicksign'); // clicksign ou docusign
        $this->apiKey = env('ASSINATURA_API_KEY', '');

        if ($this->provider === 'clicksign') {
            $this->baseUrl = env('CLICKSIGN_API_URL', 'https://api.clicksign.com/v1');
        } elseif ($this->provider === 'docusign') {
            $this->baseUrl = env('DOCUSIGN_API_URL', 'https://demo.docusign.net/restapi/v2.1');
        }
    }

    /**
     * Enviar documento para assinatura
     */
    public function enviarDocumento($documento, $arquivo = null)
    {
        if (!$this->isConfigured()) {
            Log::warning('Provedor de assinatura não configurado');
            return [
                'success' => false,
                'error' => 'Provedor de assinatura não configurado'
            ];
        }

        try {
            if ($this->provider === 'clicksign') {
                return $this->enviarDocumentoClicksign($documento, $arquivo);
            } elseif ($this->provider === 'docusign') {
                return $this->enviarDocumentoDocusign($documento, $arquivo);
            }
        } catch (\Exception $e) {
            Log::error('Erro ao enviar documento para assinatura', [
                'error' => $e->getMessage(),
                'provider' => $this->provider,
                'documento_id' => $documento->id
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }

        return [
            'success' => false,
            'error' => 'Provedor não suportado'
        ];
    }

    /**
     * Clicksign - Enviar documento
     */
    private function enviarDocumentoClicksign($documento, $arquivo)
    {
        // Upload do documento
        $response = Http::withHeaders([
            'Authorization' => $this->apiKey,
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . '/documents', [
            'document' => [
                'path' => "/documents/{$documento->id}.pdf",
                'template' => [
                    'data' => base64_encode($arquivo ?? 'PDF_CONTENT_HERE')
                ]
            ]
        ]);

        if (!$response->successful()) {
            throw new \Exception('Falha ao fazer upload do documento: ' . $response->body());
        }

        $documentData = $response->json();
        $documentKey = $documentData['document']['key'] ?? null;

        if (!$documentKey) {
            throw new \Exception('Chave do documento não retornada pela API');
        }

        // Adicionar assinantes
        $assinantes = $documento->assinantes ?? [];
        foreach ($assinantes as $assinante) {
            Http::withHeaders([
                'Authorization' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/list', [
                'list' => [
                    'document_key' => $documentKey,
                    'signer' => [
                        'email' => $assinante['email'],
                        'name' => $assinante['nome'],
                        'documentation' => $assinante['cpf'] ?? ''
                    ],
                    'sign_as' => 'sign'
                ]
            ]);
        }

        return [
            'success' => true,
            'document_key' => $documentKey,
            'provider' => 'clicksign'
        ];
    }

    /**
     * Docusign - Enviar documento
     */
    private function enviarDocumentoDocusign($documento, $arquivo)
    {
        $envelopeDefinition = [
            'emailSubject' => $documento->titulo,
            'documents' => [
                [
                    'documentBase64' => base64_encode($arquivo ?? 'PDF_CONTENT_HERE'),
                    'name' => $documento->titulo . '.pdf',
                    'fileExtension' => 'pdf',
                    'documentId' => '1'
                ]
            ],
            'recipients' => [
                'signers' => []
            ],
            'status' => 'sent'
        ];

        // Adicionar assinantes
        $assinantes = $documento->assinantes ?? [];
        foreach ($assinantes as $index => $assinante) {
            $envelopeDefinition['recipients']['signers'][] = [
                'email' => $assinante['email'],
                'name' => $assinante['nome'],
                'recipientId' => (string)($index + 1),
                'routingOrder' => (string)($index + 1)
            ];
        }

        $accountId = env('DOCUSIGN_ACCOUNT_ID', '');
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . "/accounts/{$accountId}/envelopes", $envelopeDefinition);

        if (!$response->successful()) {
            throw new \Exception('Falha ao criar envelope: ' . $response->body());
        }

        $envelopeData = $response->json();
        $envelopeId = $envelopeData['envelopeId'] ?? null;

        if (!$envelopeId) {
            throw new \Exception('ID do envelope não retornado pela API');
        }

        return [
            'success' => true,
            'envelope_id' => $envelopeId,
            'provider' => 'docusign'
        ];
    }

    /**
     * Consultar status do documento
     */
    public function consultarStatus($documentoIdExterno)
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Não configurado'];
        }

        try {
            if ($this->provider === 'clicksign') {
                $response = Http::withHeaders([
                    'Authorization' => $this->apiKey
                ])->get($this->baseUrl . "/documents/{$documentoIdExterno}");

                if ($response->successful()) {
                    $data = $response->json();
                    return [
                        'success' => true,
                        'status' => $data['document']['status'] ?? 'unknown',
                        'data' => $data
                    ];
                }
            } elseif ($this->provider === 'docusign') {
                $accountId = env('DOCUSIGN_ACCOUNT_ID', '');
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey
                ])->get($this->baseUrl . "/accounts/{$accountId}/envelopes/{$documentoIdExterno}");

                if ($response->successful()) {
                    $data = $response->json();
                    return [
                        'success' => true,
                        'status' => $data['status'] ?? 'unknown',
                        'data' => $data
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('Erro ao consultar status de assinatura', [
                'error' => $e->getMessage(),
                'documento_id_externo' => $documentoIdExterno
            ]);
        }

        return ['success' => false, 'error' => 'Falha ao consultar status'];
    }

    /**
     * Processar webhook de atualização de status
     */
    public function processarWebhook($payload)
    {
        try {
            if ($this->provider === 'clicksign') {
                return $this->processarWebhookClicksign($payload);
            } elseif ($this->provider === 'docusign') {
                return $this->processarWebhookDocusign($payload);
            }
        } catch (\Exception $e) {
            Log::error('Erro ao processar webhook', [
                'error' => $e->getMessage(),
                'provider' => $this->provider
            ]);
        }

        return null;
    }

    /**
     * Processar webhook Clicksign
     */
    private function processarWebhookClicksign($payload)
    {
        $event = $payload['event'] ?? null;
        $documentKey = $payload['document']['key'] ?? null;

        if (!$documentKey) {
            return null;
        }

        $status = 'pendente';
        if ($event === 'sign') {
            $status = 'em_assinatura';
        } elseif ($event === 'close') {
            $status = 'assinado';
        } elseif ($event === 'cancel') {
            $status = 'cancelado';
        }

        return [
            'documento_id_externo' => $documentKey,
            'status' => $status,
            'event' => $event
        ];
    }

    /**
     * Processar webhook Docusign
     */
    private function processarWebhookDocusign($payload)
    {
        $envelopeId = $payload['envelopeId'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$envelopeId) {
            return null;
        }

        $statusMap = [
            'sent' => 'pendente',
            'delivered' => 'pendente',
            'signed' => 'em_assinatura',
            'completed' => 'assinado',
            'declined' => 'cancelado',
            'voided' => 'cancelado'
        ];

        return [
            'documento_id_externo' => $envelopeId,
            'status' => $statusMap[$status] ?? 'pendente',
            'event' => $status
        ];
    }

    /**
     * Verificar se o provedor está configurado
     */
    private function isConfigured()
    {
        return !empty($this->apiKey) && !empty($this->baseUrl);
    }
}
