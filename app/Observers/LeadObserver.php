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
        if ($this->isFromChavesNaMao($lead)) {
            Log::info('Lead recebido do Chaves na Mao, ignorando envio de retorno', [
                'lead_id' => $lead->id,
                'nome' => $lead->nome
            ]);
            return;
        }

        Log::info('ÐYÅ Novo lead criado, enviando para Chaves na MÇœo', [
            'lead_id' => $lead->id,
            'nome' => $lead->nome
        ]);

        // Enviar para Chaves na MÇœo de forma assÇðncrona (se possÇðvel) ou sÇðncrona
        $this->sendToChavesNaMao($lead);
    }

    /**
     * Handle the Lead "updated" event.
     */
    public function updated(Lead $lead): void
    {
        // Verificar se jÇ­ foi enviado antes
        if ($lead->chaves_na_mao_sent_at) {
            Log::debug('ƒ"û‹÷? Lead jÇ­ sincronizado com Chaves na MÇœo', [
                'lead_id' => $lead->id,
                'sent_at' => $lead->chaves_na_mao_sent_at
            ]);
            return;
        }

        // Se nÇœo foi enviado ainda, enviar agora
        if ($this->isReadyToSend($lead)) {
            Log::info('ÐY"? Lead atualizado e pronto para envio', [
                'lead_id' => $lead->id,
                'nome' => $lead->nome
            ]);
            $this->sendToChavesNaMao($lead);
        }
    }

    /**
     * Verifica se o lead estÇ­ pronto para ser enviado
     */
    private function isReadyToSend(Lead $lead): bool
    {
        // Deve ter nome e pelo menos email ou telefone
        return !empty($lead->nome) && (!empty($lead->email) || !empty($lead->telefone));
    }

    /**
     * Envia lead para Chaves na MÇœo
     */
    private function sendToChavesNaMao(Lead $lead): void
    {
        try {
            // Marcar como pending antes de enviar
            $lead->update(['chaves_na_mao_status' => 'pending']);

            // Enviar
            $result = $this->chavesNaMaoService->sendLead($lead);

            if ($result['success']) {
                Log::info('ƒo. Lead enviado com sucesso para Chaves na MÇœo', [
                    'lead_id' => $lead->id,
                    'status_code' => $result['status_code'] ?? null
                ]);
            } else {
                Log::warning('ƒsÿ‹÷? Falha ao enviar lead para Chaves na MÇœo', [
                    'lead_id' => $lead->id,
                    'error' => $result['error'] ?? 'Erro desconhecido',
                    'retry' => $result['retry'] ?? false
                ]);
            }
        } catch (\Exception $e) {
            Log::error('ƒ?O ExceÇõÇœo ao enviar lead para Chaves na MÇœo', [
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

    private function isFromChavesNaMao(Lead $lead): bool
    {
        $observacoes = $lead->observacoes ?? '';
        return stripos($observacoes, 'Chaves na') !== false;
    }
}
