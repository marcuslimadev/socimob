<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocimobAiService
{
    public function isConfigured(?int $tenantId = null): bool
    {
        return $this->baseUrl($tenantId) !== '';
    }

    public function getModel(?int $tenantId = null): ?string
    {
        return $this->setting('socimob_ai_model', $tenantId, env('SOCIMOB_AI_MODEL', null));
    }

    public function health(?int $tenantId = null): array
    {
        $baseUrl = $this->baseUrl($tenantId);
        if ($baseUrl === '') {
            return ['success' => false, 'error' => 'SOCIMOB_AI_BASE_URL not configured'];
        }

        try {
            $response = Http::withHeaders($this->headers($tenantId))
                ->timeout(8)
                ->acceptJson()
                ->get($baseUrl . '/health');
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'error' => 'Unable to contact Socimob AI',
                'exception' => $exception->getMessage(),
            ];
        }

        return $response->successful()
            ? ($response->json() ?: ['success' => true])
            : [
                'success' => false,
                'error' => 'Socimob AI health check failed',
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 800),
            ];
    }

    public function generateSimpleMessage(string $systemPrompt, string $userPrompt, ?int $tenantId = null): string
    {
        $result = $this->chat($tenantId, [
            'system_prompt' => $systemPrompt,
            'message' => $userPrompt,
            'max_words' => 90,
        ]);

        return ($result['success'] ?? false) ? (string) $result['content'] : '';
    }

    public function processMessage($message, $context = '', $isFromAudio = false, $availableProperties = [], $leadData = null, ?int $tenantId = null): array
    {
        $assistantName = $this->assistantName();
        $companyName = env('COMPANY_NAME', 'Imobiliaria');

        $systemPrompt = "Voce e {$assistantName}, assistente imobiliaria da {$companyName}.

REGRAS FIXAS:
- Responda em portugues do Brasil.
- Seja cordial, educada, natural e acolhedora.
- Nao seja seca; reconheca o que o cliente informou antes de pedir outro dado.
- Faca no maximo uma pergunta por vez.
- Nao repita pergunta ja respondida.
- Nunca invente imoveis, valores, documentos, visitas ou aprovacoes.
- Use apenas os imoveis enviados no contexto.
- Nao diga que e IA ou robo.
- Se a mensagem veio de audio, responda naturalmente.
- Se faltar dado essencial, peca apenas o proximo dado mais importante.";

        $result = $this->chat($tenantId, [
            'system_prompt' => $systemPrompt,
            'message' => (string) $message,
            'history' => (string) $context,
            'is_from_audio' => (bool) $isFromAudio,
            'properties' => array_slice($availableProperties, 0, 12),
            'lead' => $leadData,
            'assistant_name' => $assistantName,
            'company_name' => $companyName,
            'max_words' => 120,
        ]);

        if (!($result['success'] ?? false)) {
            Log::warning('[SocimobAI] Falha ao processar mensagem', [
                'tenant_id' => $tenantId,
                'error' => $result['error'] ?? 'unknown',
                'status' => $result['status'] ?? null,
            ]);
        }

        return $result;
    }

    private function chat(?int $tenantId, array $payload): array
    {
        $baseUrl = $this->baseUrl($tenantId);
        if ($baseUrl === '') {
            return ['success' => false, 'error' => 'SOCIMOB_AI_BASE_URL not configured'];
        }

        if ($model = $this->getModel($tenantId)) {
            $payload['model'] = $model;
        }

        try {
            $response = Http::withHeaders($this->headers($tenantId))
                ->timeout((int) env('SOCIMOB_AI_TIMEOUT', 60))
                ->acceptJson()
                ->post($baseUrl . '/chat', $payload);
        } catch (\Throwable $exception) {
            Log::error('[SocimobAI] Erro de conexao', [
                'base_url' => $baseUrl,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Unable to contact Socimob AI',
                'exception' => $exception->getMessage(),
            ];
        }

        if (!$response->successful()) {
            return [
                'success' => false,
                'error' => 'Socimob AI request failed',
                'status' => $response->status(),
                'body' => $response->body(),
            ];
        }

        $data = $response->json();
        $content = $data['content'] ?? $data['response'] ?? $data['message'] ?? null;

        if (!is_string($content) || trim($content) === '') {
            return [
                'success' => false,
                'error' => 'Unexpected Socimob AI response format',
                'payload' => $data,
            ];
        }

        return [
            'success' => true,
            'content' => trim($content),
            'model' => $data['model'] ?? ($payload['model'] ?? null),
            'provider' => 'socimob_ai',
        ];
    }

    private function headers(?int $tenantId = null): array
    {
        $headers = ['Content-Type' => 'application/json'];
        $apiKey = $this->setting('socimob_ai_api_key', $tenantId, env('SOCIMOB_AI_API_KEY', ''));

        if ($apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        return $headers;
    }

    private function baseUrl(?int $tenantId = null): string
    {
        return rtrim($this->setting('socimob_ai_base_url', $tenantId, env('SOCIMOB_AI_BASE_URL', '')), '/');
    }

    private function setting(string $key, ?int $tenantId, mixed $default = ''): string
    {
        return trim((string) AppSetting::getValue($key, $default, $tenantId));
    }

    private function assistantName(): string
    {
        return trim((string) AppSetting::getValue('ai_name', env('AI_ASSISTANT_NAME', 'Teresa'))) ?: 'Teresa';
    }
}
