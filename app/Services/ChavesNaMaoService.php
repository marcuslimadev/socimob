<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChavesNaMaoService
{
    private string $apiUrl = 'https://api.chavesnamao.com.br/leads'; // Endpoint da API
    private ?string $email;
    private ?string $token;

    public function __construct()
    {
        $this->email = env('EXCLUSIVA_MAIL_CHAVES_NA_MAO');
        $this->token = env('EXCLUSIVA_CHAVES_NA_MAO');

        if (!$this->email || !$this->token) {
            Log::error('❌ Credenciais Chaves na Mão não configuradas', [
                'email_exists' => !empty($this->email),
                'token_exists' => !empty($this->token)
            ]);
        }
    }

    /**
     * Gera o header de autenticação Basic Auth
     */
    private function getAuthHeader(): string
    {
        $credentials = base64_encode("{$this->email}:{$this->token}");
        return "Basic {$credentials}";
    }

    /**
     * Envia lead para API do Chaves na Mão
     */
    public function sendLead(Lead $lead): array
    {
        // Verificar credenciais
        if (!$this->email || !$this->token) {
            return [
                'success' => false,
                'error' => 'Credenciais não configuradas no .env',
                'requires_configuration' => true
            ];
        }

        // Verificar se já foi enviado
        if ($lead->chaves_na_mao_sent_at) {
            Log::info('⚠️ Lead já enviado ao Chaves na Mão', [
                'lead_id' => $lead->id,
                'sent_at' => $lead->chaves_na_mao_sent_at
            ]);
            return [
                'success' => false,
                'error' => 'Lead já foi enviado anteriormente',
                'already_sent' => true
            ];
        }

        // Validar dados mínimos
        if (!$this->validateLead($lead)) {
            Log::warning('⚠️ Lead inválido para envio', [
                'lead_id' => $lead->id,
                'nome' => $lead->nome,
                'email' => $lead->email,
                'telefone' => $lead->telefone
            ]);
            return [
                'success' => false,
                'error' => 'Dados insuficientes (necessário nome + email ou telefone)'
            ];
        }

        try {
            // Preparar payload
            $payload = $this->buildPayload($lead);

            Log::info('📤 Enviando lead para Chaves na Mão', [
                'lead_id' => $lead->id,
                'payload_keys' => array_keys($payload)
            ]);

            // Executar requisição
            $response = Http::withHeaders([
                'Authorization' => $this->getAuthHeader(),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->timeout(30)
            ->post($this->apiUrl, $payload);

            // Processar resposta
            $statusCode = $response->status();
            $responseBody = $response->json() ?? $response->body();

            Log::info('📥 Resposta da API Chaves na Mão', [
                'lead_id' => $lead->id,
                'status_code' => $statusCode,
                'response_preview' => is_array($responseBody) 
                    ? json_encode(array_slice($responseBody, 0, 5))
                    : substr($responseBody, 0, 200)
            ]);

            // Atualizar lead com informações de integração
            $this->updateLeadIntegrationStatus($lead, $statusCode, $responseBody);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status_code' => $statusCode,
                    'response' => $responseBody
                ];
            }

            // Tratamento de erros HTTP
            return $this->handleError($statusCode, $responseBody);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao enviar lead para Chaves na Mão', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $lead->update([
                'chaves_na_mao_status' => 'error',
                'chaves_na_mao_error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'exception' => true
            ];
        }
    }

    /**
     * Valida se o lead possui dados mínimos
     */
    private function validateLead(Lead $lead): bool
    {
        return !empty($lead->nome) && (!empty($lead->email) || !empty($lead->telefone));
    }

    /**
     * Constrói o payload para envio
     */
    private function buildPayload(Lead $lead): array
    {
        $payload = [
            'nome' => $lead->nome,
            'email' => $lead->email,
            'telefone' => $lead->telefone ?? $lead->whatsapp,
            'origem' => 'Exclusiva SaaS',
            'status' => $this->mapStatus($lead->status),
        ];

        // Campos opcionais
        if ($lead->observacoes) {
            $payload['observacoes'] = $lead->observacoes;
        }

        if ($lead->budget_min || $lead->budget_max) {
            $payload['orcamento'] = sprintf(
                'R$ %s - R$ %s',
                number_format($lead->budget_min ?? 0, 2, ',', '.'),
                number_format($lead->budget_max ?? 0, 2, ',', '.')
            );
        }

        if ($lead->localizacao) {
            $payload['localizacao'] = $lead->localizacao;
        }

        if ($lead->quartos) {
            $payload['quartos'] = $lead->quartos;
        }

        // Identificação interna
        $payload['referencia_externa'] = "EXCLUSIVA_LEAD_{$lead->id}";

        return $payload;
    }

    /**
     * Mapeia status interno para status da API
     */
    private function mapStatus(string $status): string
    {
        $map = [
            'novo' => 'novo',
            'em_atendimento' => 'em_andamento',
            'qualificado' => 'qualificado',
            'fechado' => 'convertido',
            'descartado' => 'perdido'
        ];

        return $map[$status] ?? 'novo';
    }

    /**
     * Atualiza status de integração no lead
     */
    private function updateLeadIntegrationStatus(Lead $lead, int $statusCode, $response): void
    {
        $updateData = [
            'chaves_na_mao_status' => $statusCode >= 200 && $statusCode < 300 ? 'sent' : 'error',
            'chaves_na_mao_response' => is_array($response) ? json_encode($response) : $response,
        ];

        if ($statusCode >= 200 && $statusCode < 300) {
            $updateData['chaves_na_mao_sent_at'] = now();
        } else {
            $updateData['chaves_na_mao_error'] = is_array($response) 
                ? ($response['message'] ?? 'Erro desconhecido')
                : substr($response, 0, 500);
        }

        $lead->update($updateData);
    }

    /**
     * Trata erros da API
     */
    private function handleError(int $statusCode, $response): array
    {
        $error = is_array($response) ? ($response['message'] ?? 'Erro desconhecido') : $response;

        // Classificação de erros
        if (in_array($statusCode, [401, 403])) {
            Log::critical('🔒 Erro de autenticação Chaves na Mão', [
                'status_code' => $statusCode,
                'email_configured' => !empty($this->email)
            ]);
            return [
                'success' => false,
                'error' => 'Erro de autenticação - verificar credenciais',
                'status_code' => $statusCode,
                'requires_attention' => true
            ];
        }

        if ($statusCode >= 400 && $statusCode < 500) {
            Log::warning('⚠️ Erro de payload Chaves na Mão', [
                'status_code' => $statusCode,
                'error' => $error
            ]);
            return [
                'success' => false,
                'error' => "Erro no payload: {$error}",
                'status_code' => $statusCode,
                'retry' => false
            ];
        }

        if ($statusCode >= 500) {
            Log::error('🔥 Erro no servidor Chaves na Mão', [
                'status_code' => $statusCode,
                'error' => $error
            ]);
            return [
                'success' => false,
                'error' => 'Erro temporário no servidor',
                'status_code' => $statusCode,
                'retry' => true
            ];
        }

        return [
            'success' => false,
            'error' => $error,
            'status_code' => $statusCode
        ];
    }

    /**
     * Retry de envio com backoff exponencial
     */
    public function retryFailedLeads(int $maxRetries = 3): array
    {
        $leads = Lead::where('chaves_na_mao_status', 'error')
            ->where(function ($query) {
                $query->whereNull('chaves_na_mao_retries')
                    ->orWhere('chaves_na_mao_retries', '<', 3);
            })
            ->limit(50)
            ->get();

        $results = [
            'total' => $leads->count(),
            'success' => 0,
            'failed' => 0
        ];

        foreach ($leads as $lead) {
            $retries = $lead->chaves_na_mao_retries ?? 0;
            
            // Backoff exponencial: 1min, 5min, 30min
            $backoffMinutes = [1, 5, 30][$retries] ?? 60;
            $nextRetryAt = $lead->updated_at->addMinutes($backoffMinutes);

            if (now()->lt($nextRetryAt)) {
                continue; // Ainda não é hora de tentar novamente
            }

            $result = $this->sendLead($lead);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $lead->update([
                    'chaves_na_mao_retries' => $retries + 1
                ]);
            }

            // Pequeno delay entre requisições
            usleep(500000); // 500ms
        }

        Log::info('🔄 Retry de leads Chaves na Mão concluído', $results);

        return $results;
    }
}
