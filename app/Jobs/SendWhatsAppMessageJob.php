<?php

namespace App\Jobs;

use App\Services\EvolutionApiService;
use App\Services\TwilioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de tentativas máximas
     */
    public $tries = 3;

    /**
     * Tempo máximo de execução em segundos
     */
    public $timeout = 60;

    /**
     * Número do destinatário
     */
    protected string $to;

    /**
     * Mensagem a ser enviada
     */
    protected string $message;

    /**
     * Tipo de mensagem (text, template, media)
     */
    protected string $type;

    /**
     * Dados extras (template_sid, media_url, etc.)
     */
    protected array $extra;

    /**
     * Create a new job instance.
     */
    public function __construct(string $to, string $message, string $type = 'text', array $extra = [])
    {
        $this->to = $to;
        $this->message = $message;
        $this->type = $type;
        $this->extra = $extra;
    }

    private function resolveWhatsAppGateway(TwilioService $twilioService, EvolutionApiService $evolutionApiService): TwilioService|EvolutionApiService
    {
        $driver = strtolower((string) config('whatsapp.driver', 'evolution'));
        return $driver === 'evolution' ? $evolutionApiService : $twilioService;
    }

    /**
     * Execute the job.
     */
    public function handle(TwilioService $twilioService, EvolutionApiService $evolutionApiService): void
    {
        Log::info('Processing WhatsApp message job', [
            'to' => $this->to,
            'type' => $this->type,
            'attempt' => $this->attempts(),
        ]);

        try {
            $gateway = $this->resolveWhatsAppGateway($twilioService, $evolutionApiService);

            $result = match ($this->type) {
                'template' => $gateway->sendTemplate(
                    $this->to,
                    $this->extra['template_sid'] ?? '',
                    $this->extra['variables'] ?? []
                ),
                'media' => $gateway->sendMedia(
                    $this->to,
                    $this->message,
                    $this->extra['media_url'] ?? ''
                ),
                default => $gateway->sendMessage($this->to, $this->message),
            };

            if (!$result['success']) {
                Log::warning('WhatsApp message job failed', [
                    'to' => $this->to,
                    'error' => $result['error'] ?? 'Unknown error',
                    'attempt' => $this->attempts(),
                ]);

                // Se ainda temos tentativas, relançar exceção para retry
                if ($this->attempts() < $this->tries) {
                    throw new \Exception($result['error'] ?? 'Failed to send WhatsApp message');
                }
            } else {
                Log::info('WhatsApp message job completed', [
                    'to' => $this->to,
                    'message_sid' => $result['message_sid'] ?? null,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp message job exception', [
                'to' => $this->to,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('WhatsApp message job permanently failed', [
            'to' => $this->to,
            'message' => $this->message,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // 30s, 1min, 2min
    }
}
