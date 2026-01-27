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

    /**
     * Enviar para gateway com retry automático
     *
     * @param FinancialTransaction $transaction
     * @param CommissionInvoice $invoice
     * @param array $financeiro
     * @param int $maxRetries
     * @return array|null
     */
    public function enviarComRetry(FinancialTransaction $transaction, CommissionInvoice $invoice, array $financeiro, int $maxRetries = 3): ?array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                $attempt++;

                Log::info('Tentando enviar transação ao gateway', [
                    'transaction_id' => $transaction->id,
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries
                ]);

                $result = $this->enviarParaGatewayFinanceiro($transaction, $invoice, $financeiro);

                if ($result) {
                    Log::info('Transação enviada com sucesso', [
                        'transaction_id' => $transaction->id,
                        'attempt' => $attempt
                    ]);

                    return $result;
                }

                if ($attempt >= $maxRetries) {
                    Log::warning('Falha após todas as tentativas', [
                        'transaction_id' => $transaction->id,
                        'attempts' => $attempt
                    ]);

                    return null;
                }

            } catch (\Exception $e) {
                $lastException = $e;

                Log::warning('Tentativa falhou', [
                    'transaction_id' => $transaction->id,
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);

                if ($attempt >= $maxRetries) {
                    Log::error('Erro definitivo ao enviar transação', [
                        'transaction_id' => $transaction->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    throw $e;
                }

                // Aguardar antes de retry (backoff exponencial)
                sleep(pow(2, $attempt));
            }

            $attempt++;
        }

        if ($lastException) {
            throw $lastException;
        }

        return null;
    }

    /**
     * Gerar relatório de transações
     *
     * @param int|null $tenantId
     * @param array $filters
     * @return array
     */
    public function gerarRelatorio(?int $tenantId = null, array $filters = []): array
    {
        Log::info('Gerando relatório financeiro', [
            'tenant_id' => $tenantId,
            'filters' => array_keys($filters)
        ]);

        $query = FinancialTransaction::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        // Aplicar filtros
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['tipo'])) {
            $query->where('tipo', $filters['tipo']);
        }

        if (!empty($filters['data_inicio'])) {
            $query->where('created_at', '>=', $filters['data_inicio']);
        }

        if (!empty($filters['data_fim'])) {
            $query->where('created_at', '<=', $filters['data_fim']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['forma_pagamento'])) {
            $query->where('forma_pagamento', $filters['forma_pagamento']);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        // Calcular estatísticas
        $stats = [
            'total_transactions' => $transactions->count(),
            'total_valor' => $transactions->sum('valor'),
            'pendente_valor' => $transactions->where('status', 'pendente')->sum('valor'),
            'pago_valor' => $transactions->where('status', 'pago')->sum('valor'),
            'cancelado_valor' => $transactions->where('status', 'cancelado')->sum('valor'),
            'receber_count' => $transactions->where('tipo', 'receber')->count(),
            'pagar_count' => $transactions->where('tipo', 'pagar')->count(),
        ];

        // Agrupar por status
        $byStatus = $transactions->groupBy('status')->map(function($group) {
            return [
                'count' => $group->count(),
                'total' => $group->sum('valor')
            ];
        })->toArray();

        // Agrupar por forma de pagamento
        $byPaymentMethod = $transactions->whereNotNull('forma_pagamento')
            ->groupBy('forma_pagamento')
            ->map(function($group) {
                return [
                    'count' => $group->count(),
                    'total' => $group->sum('valor')
                ];
            })->toArray();

        // Transações recentes
        $recent = $transactions->take(10)->map(function($t) {
            return [
                'id' => $t->id,
                'tipo' => $t->tipo,
                'status' => $t->status,
                'valor' => $t->valor,
                'forma_pagamento' => $t->forma_pagamento,
                'descricao' => $t->descricao,
                'created_at' => $t->created_at->toIso8601String(),
            ];
        })->toArray();

        return [
            'success' => true,
            'stats' => $stats,
            'by_status' => $byStatus,
            'by_payment_method' => $byPaymentMethod,
            'recent_transactions' => $recent
        ];
    }

    /**
     * Processar webhook de confirmação de pagamento
     *
     * @param array $webhookData
     * @return array
     */
    public function processarWebhookConfirmacao(array $webhookData): array
    {
        try {
            Log::info('Processando webhook de confirmação', [
                'data_keys' => array_keys($webhookData)
            ]);

            $referenciaExterna = $webhookData['referencia_externa'] ?? $webhookData['external_reference'] ?? null;
            $status = $webhookData['status'] ?? null;
            $paymentId = $webhookData['payment_id'] ?? $webhookData['id'] ?? null;

            if (!$referenciaExterna && !$paymentId) {
                throw new \Exception('Webhook sem referência externa ou payment_id');
            }

            // Buscar transação
            $query = FinancialTransaction::query();

            if ($referenciaExterna) {
                $query->where('referencia_externa', $referenciaExterna);
            } elseif ($paymentId) {
                $query->where('referencia_externa', 'LIKE', "%{$paymentId}%");
            }

            $transaction = $query->first();

            if (!$transaction) {
                Log::warning('Transação não encontrada para webhook', [
                    'referencia_externa' => $referenciaExterna,
                    'payment_id' => $paymentId
                ]);

                return [
                    'success' => false,
                    'message' => 'Transação não encontrada'
                ];
            }

            // Mapear status do gateway para status interno
            $statusMap = [
                'approved' => 'pago',
                'paid' => 'pago',
                'pending' => 'pendente',
                'in_process' => 'processando',
                'cancelled' => 'cancelado',
                'refunded' => 'estornado',
                'rejected' => 'rejeitado',
            ];

            $newStatus = $statusMap[$status] ?? $transaction->status;

            // Atualizar transação
            $transaction->update([
                'status' => $newStatus,
                'data_pagamento' => ($newStatus === 'pago') ? now() : $transaction->data_pagamento,
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'webhook_received_at' => now()->toIso8601String(),
                    'webhook_data' => $webhookData,
                ])
            ]);

            Log::info('Transação atualizada via webhook', [
                'transaction_id' => $transaction->id,
                'old_status' => $transaction->getOriginal('status'),
                'new_status' => $newStatus
            ]);

            // Se foi pago, atualizar invoice relacionada
            if ($newStatus === 'pago' && $transaction->commission_invoice_id) {
                $invoice = \App\Models\CommissionInvoice::find($transaction->commission_invoice_id);
                if ($invoice && $invoice->status !== 'paid') {
                    $invoice->update([
                        'status' => 'paid',
                        'paid_at' => now()
                    ]);

                    Log::info('Invoice atualizada para paga', [
                        'invoice_id' => $invoice->id
                    ]);
                }
            }

            return [
                'success' => true,
                'transaction_id' => $transaction->id,
                'old_status' => $transaction->getOriginal('status'),
                'new_status' => $newStatus
            ];

        } catch (\Exception $e) {
            Log::error('Erro ao processar webhook de confirmação', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao processar webhook',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obter transações pendentes há mais de X dias
     *
     * @param int|null $tenantId
     * @param int $days
     * @return \Illuminate\Support\Collection
     */
    public function getTransacoesPendentes(?int $tenantId = null, int $days = 7)
    {
        $query = FinancialTransaction::where('status', 'pendente')
            ->where('created_at', '<=', now()->subDays($days));

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->orderBy('vencimento', 'asc')->get();
    }

    /**
     * Obter transações vencidas
     *
     * @param int|null $tenantId
     * @return \Illuminate\Support\Collection
     */
    public function getTransacoesVencidas(?int $tenantId = null)
    {
        $query = FinancialTransaction::where('status', 'pendente')
            ->where('vencimento', '<', now()->toDateString());

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->orderBy('vencimento', 'asc')->get();
    }

    /**
     * Calcular comissões do período
     *
     * @param int|null $tenantId
     * @param string $dataInicio
     * @param string $dataFim
     * @return array
     */
    public function calcularComissoesPeriodo(?int $tenantId, string $dataInicio, string $dataFim): array
    {
        $query = FinancialTransaction::where('tipo', 'receber')
            ->whereBetween('created_at', [$dataInicio, $dataFim]);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $transactions = $query->get();

        $total = $transactions->sum('valor');
        $pago = $transactions->where('status', 'pago')->sum('valor');
        $pendente = $transactions->where('status', 'pendente')->sum('valor');

        // Agrupar por corretor
        $porCorretor = $transactions->groupBy('user_id')->map(function($group) {
            $userId = $group->first()->user_id;
            $user = \App\Models\User::find($userId);

            return [
                'user_id' => $userId,
                'user_name' => $user?->name ?? 'Desconhecido',
                'total' => $group->sum('valor'),
                'pago' => $group->where('status', 'pago')->sum('valor'),
                'pendente' => $group->where('status', 'pendente')->sum('valor'),
                'count' => $group->count(),
            ];
        })->values()->toArray();

        return [
            'periodo' => [
                'inicio' => $dataInicio,
                'fim' => $dataFim
            ],
            'total' => $total,
            'pago' => $pago,
            'pendente' => $pendente,
            'por_corretor' => $porCorretor,
            'total_transacoes' => $transactions->count()
        ];
    }
}
