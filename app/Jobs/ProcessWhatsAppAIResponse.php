<?php

namespace App\Jobs;

use App\Models\Conversa;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppAIResponse implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 120;

    protected $conversaId;
    protected $mensagem;
    protected $fromAudio;

    public function __construct($conversaId, $mensagem, $fromAudio = false)
    {
        $this->conversaId = $conversaId;
        $this->mensagem = $mensagem;
        $this->fromAudio = $fromAudio;
    }

    public function handle()
    {
        $startTime = microtime(true);
        
        Log::info('⏱️ [JOB] Iniciando processamento assíncrono de IA', [
            'conversa_id' => $this->conversaId,
            'mensagem_preview' => substr($this->mensagem, 0, 50)
        ]);

        try {
            $conversa = Conversa::with(['lead', 'mensagens'])->find($this->conversaId);
            
            if (!$conversa) {
                Log::error('[JOB] Conversa não encontrada', ['conversa_id' => $this->conversaId]);
                return;
            }

            $whatsappService = app(WhatsAppService::class);
            $result = $whatsappService->handleRegularMessage($conversa, $this->mensagem, $this->fromAudio);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('✅ [JOB] Processamento concluído', [
                'conversa_id' => $this->conversaId,
                'tempo_ms' => $duration,
                'sucesso' => $result['success'] ?? false
            ]);

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::error('❌ [JOB] Erro no processamento', [
                'conversa_id' => $this->conversaId,
                'erro' => $e->getMessage(),
                'tempo_ms' => $duration
            ]);
        }
    }
}
