<?php

namespace App\Services;

use App\Models\CommissionInvoice;
use App\Models\FinancialTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FinancialIntegrationService
{
    public function registrarRecebimentoComissao(CommissionInvoice $invoice, array $financeiro): ?FinancialTransaction
    {
        $transaction = FinancialTransaction::create([
            'tenant_id' => $invoice->tenant_id,
            'commission_invoice_id' => $invoice->id,
            'user_id' => $invoice->corretor_id,
            'tipo' => 'receber',
            'status' => 'pendente',
            'vencimento' => $financeiro['vencimento'] ?? optional($invoice->financeiro_vencimento)->toDateString(),
            'valor' => $invoice->valor_total,
            'forma_pagamento' => $financeiro['forma_pagamento'] ?? null,
            'descricao' => $financeiro['descricao'] ?? 'Recebimento de comissão',
            'metadata' => [
                'lead_id' => $invoice->lead_id,
                'property_id' => $invoice->property_id,
            ],
        ]);

        $gatewayResponse = $this->enviarParaGatewayFinanceiro($transaction, $invoice, $financeiro);

        if ($gatewayResponse) {
            $transaction->update([
                'referencia_externa' => $gatewayResponse['referencia'] ?? $transaction->referencia_externa,
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'gateway_response' => $gatewayResponse,
                ]),
            ]);
        }

        return $transaction;
    }

    private function enviarParaGatewayFinanceiro(FinancialTransaction $transaction, CommissionInvoice $invoice, array $financeiro): ?array
    {
        $tenant = $invoice->tenant ?? $transaction->tenant ?? null;
        $apiUrl = $tenant?->getIntegrationValue('finance_api_url', env('FINANCE_API_URL'));
        $apiToken = $tenant?->getIntegrationValue('finance_api_token', env('FINANCE_API_TOKEN'));

        if (!$apiUrl || !$apiToken) {
            return null;
        }

        $endpoint = rtrim($apiUrl, '/') . '/financeiro/lancamentos';
        $payload = [
            'id_integracao' => $transaction->id,
            'tipo' => $transaction->tipo,
            'valor' => $transaction->valor,
            'vencimento' => optional($transaction->vencimento)->toDateString(),
            'descricao' => $transaction->descricao,
            'forma_pagamento' => $transaction->forma_pagamento,
            'status' => $transaction->status,
            'nfse' => [
                'numero' => $invoice->nfse_numero,
                'codigo_verificacao' => $invoice->nfse_codigo_verificacao,
                'pdf_url' => $invoice->nfse_pdf_url,
                'xml_url' => $invoice->nfse_xml_url,
            ],
            'contexto' => [
                'tenant_id' => $invoice->tenant_id,
                'lead_id' => $invoice->lead_id,
                'property_id' => $invoice->property_id,
                'corretor_id' => $invoice->corretor_id,
            ],
        ];

        Log::info('Sincronizando financeiro da comissão', [
            'transaction_id' => $transaction->id,
            'endpoint' => $endpoint,
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiToken,
            'Accept' => 'application/json',
        ])->timeout(20)
            ->post($endpoint, $payload);

        $body = $response->json() ?? [];

        if ($response->failed()) {
            Log::warning('Falha ao enviar lançamento financeiro', [
                'transaction_id' => $transaction->id,
                'status' => $response->status(),
                'body' => $body ?: $response->body(),
            ]);
            return null;
        }

        return [
            'referencia' => $body['referencia'] ?? Str::uuid()->toString(),
            'payload' => $payload,
            'resposta' => $body,
        ];
    }
}
