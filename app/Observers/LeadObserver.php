<?php

namespace App\Observers;

use App\Models\Lead;
use App\Services\ChavesNaMaoService;
use Illuminate\Support\Facades\Log;

class LeadObserver
{
    private ChavesNaMaoService $chavesNaMaoService;

    public function __construct(ChavesNaMaoService $chavesNaMaoService)
    {
        $this->chavesNaMaoService = $chavesNaMaoService;
    }

    /**
     * Handle the Lead "created" event.
     */
    public function created(Lead $lead): void
    {
        Log::info('🆕 Novo lead criado, enviando para Chaves na Mão', [
            'lead_id' => $lead->id,
            'nome' => $lead->nome
        ]);

        // Enviar para Chaves na Mão de forma assíncrona (se possível) ou síncrona
        $this->sendToChavesNaMao($lead);
    }

    /**
     * Handle the Lead "updated" event.
     */
    public function updated(Lead $lead): void
    {
        // Verificar se já foi enviado antes
        if ($lead->chaves_na_mao_sent_at) {
            Log::debug('ℹ️ Lead já sincronizado com Chaves na Mão', [
                'lead_id' => $lead->id,
                'sent_at' => $lead->chaves_na_mao_sent_at
            ]);
            return;
        }

        // Se não foi enviado ainda, enviar agora
        if ($this->isReadyToSend($lead)) {
            Log::info('📝 Lead atualizado e pronto para envio', [
                'lead_id' => $lead->id,
                'nome' => $lead->nome
            ]);
            $this->sendToChavesNaMao($lead);
        }
    }

    /**
     * Verifica se o lead está pronto para ser enviado
     */
    private function isReadyToSend(Lead $lead): bool
    {
        // Deve ter nome e pelo menos email ou telefone
        return !empty($lead->nome) && (!empty($lead->email) || !empty($lead->telefone));
    }

    /**
     * Envia lead para Chaves na Mão
     */
    private function sendToChavesNaMao(Lead $lead): void
    {
        try {
            // Marcar como pending antes de enviar
            $lead->update(['chaves_na_mao_status' => 'pending']);

            // Enviar
            $result = $this->chavesNaMaoService->sendLead($lead);

            if ($result['success']) {
                Log::info('✅ Lead enviado com sucesso para Chaves na Mão', [
                    'lead_id' => $lead->id,
                    'status_code' => $result['status_code'] ?? null
                ]);
            } else {
                Log::warning('⚠️ Falha ao enviar lead para Chaves na Mão', [
                    'lead_id' => $lead->id,
                    'error' => $result['error'] ?? 'Erro desconhecido',
                    'retry' => $result['retry'] ?? false
                ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Exceção ao enviar lead para Chaves na Mão', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $lead->update([
                'chaves_na_mao_status' => 'error',
                'chaves_na_mao_error' => $e->getMessage()
            ]);
        }
    }
}
