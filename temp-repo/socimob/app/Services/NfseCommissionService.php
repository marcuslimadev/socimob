<?php

namespace App\Services;

use App\Models\CommissionInvoice;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class NfseCommissionService
{
    public function emitir(CommissionInvoice $invoice, array $tomador, array $financeiro = []): array
    {
        $tenant = $invoice->tenant ?? Tenant::find($invoice->tenant_id);
        $apiUrl = $tenant?->getIntegrationValue('nfse_api_url', env('NFSE_API_URL'));
        $apiToken = $tenant?->getIntegrationValue('nfse_api_token', env('NFSE_API_TOKEN'));

        if (!$apiUrl || !$apiToken) {
            throw new RuntimeException('Credenciais da API de NFSe não configuradas');
        }

        $payload = $this->montarPayload($invoice, $tomador, $financeiro, $tenant);
        $endpoint = rtrim($apiUrl, '/') . '/nfse/comissoes';

        Log::info('Enviando solicitação de NFSe de comissão', [
            'invoice_id' => $invoice->id,
            'endpoint' => $endpoint,
            'tenant_id' => $invoice->tenant_id,
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiToken,
            'Accept' => 'application/json',
        ])->timeout(30)
            ->post($endpoint, $payload);

        $body = $response->json() ?? [];

        if ($response->failed()) {
            Log::error('Falha ao emitir NFSe de comissão', [
                'invoice_id' => $invoice->id,
                'status' => $response->status(),
                'body' => $body ?: $response->body(),
            ]);

            $message = $body['message'] ?? 'Erro ao emitir NFSe de comissão';
            throw new RuntimeException($message);
        }

        return [
            'nfse_numero' => data_get($body, 'nfse.numero'),
            'codigo_verificacao' => data_get($body, 'nfse.codigo_verificacao'),
            'nfse_rps' => data_get($body, 'nfse.rps'),
            'pdf_url' => data_get($body, 'links.pdf'),
            'xml_url' => data_get($body, 'links.xml'),
            'integracao_id' => data_get($body, 'id') ?? data_get($body, 'nfse.id') ?? Str::uuid()->toString(),
            'raw_response' => $body,
            'payload' => $payload,
            'financeiro_status' => data_get($body, 'financeiro.status'),
        ];
    }

    private function montarPayload(CommissionInvoice $invoice, array $tomador, array $financeiro, ?Tenant $tenant): array
    {
        $payload = [
            'referencia' => 'commission_invoice_' . $invoice->id,
            'competencia' => optional($invoice->competencia)->toDateString() ?? now()->toDateString(),
            'descricao' => $invoice->descricao_servico,
            'valor' => $invoice->valor_total,
            'aliquota' => $invoice->aliquota_iss,
            'valor_iss' => $invoice->valor_iss,
            'servico' => [
                'codigo' => $tenant?->getIntegrationValue('nfse_service_code', env('NFSE_SERVICE_CODE', '105')), 
                'discriminacao' => $invoice->descricao_servico,
                'municipio' => $tenant?->getIntegrationValue('nfse_municipio', env('NFSE_MUNICIPIO')),
            ],
            'prestador' => [
                'cnpj' => $tenant?->getIntegrationValue('nfse_emitter_document', env('NFSE_EMITTER_DOCUMENT')),
                'inscricao_municipal' => $tenant?->getIntegrationValue(
                    'nfse_emitter_inscricao_municipal',
                    env('NFSE_EMITTER_INSCRICAO_MUNICIPAL')
                ),
                'razao_social' => $tenant?->name,
            ],
            'tomador' => [
                'nome' => $tomador['nome'] ?? null,
                'documento' => $tomador['documento'] ?? null,
                'email' => $tomador['email'] ?? null,
                'telefone' => $tomador['telefone'] ?? null,
                'endereco' => $tomador['endereco'] ?? null,
            ],
            'financeiro' => [
                'vencimento' => $financeiro['vencimento'] ?? optional($invoice->financeiro_vencimento)->toDateString(),
                'forma_pagamento' => $financeiro['forma_pagamento'] ?? null,
                'status' => $financeiro['status'] ?? $invoice->financeiro_status,
            ],
            'contexto' => [
                'lead_id' => $invoice->lead_id,
                'property_id' => $invoice->property_id,
                'corretor_id' => $invoice->corretor_id,
            ],
        ];

        return $this->limparPayload($payload);
    }

    private function limparPayload(array $payload): array
    {
        return collect($payload)
            ->map(function ($value) {
                if (is_array($value)) {
                    $filtered = $this->limparPayload($value);
                    return empty($filtered) ? null : $filtered;
                }

                return $value;
            })
            ->filter(function ($value) {
                if (is_array($value)) {
                    return count($value) > 0;
                }

                return !is_null($value);
            })
            ->all();
    }
}
