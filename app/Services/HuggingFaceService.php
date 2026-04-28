<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HuggingFaceService
{
    private const DEFAULT_CHAT_MODEL = 'meta-llama/Llama-3.1-8B-Instruct';

    public function isConfigured(): bool
    {
        return $this->resolveApiKey() !== '';
    }

    public function getModel(): string
    {
        return $this->resolveModel();
    }

    public function generateSimpleMessage(string $systemPrompt, string $userPrompt): string
    {
        $result = $this->chatCompletion($systemPrompt, $userPrompt, 35, 120);

        return ($result['success'] ?? false) ? (string) $result['content'] : '';
    }

    public function processMessage($message, $context = '', $isFromAudio = false, $availableProperties = [], $leadData = null): array
    {
        $assistantName = $this->resolveAssistantName();
        $companyName = $this->resolveCompanyName();
        $audioInstruction = $isFromAudio
            ? "\n- A mensagem veio de um audio transcrito. Responda de forma natural, demonstrando que entendeu."
            : '';

        $propertiesContext = $this->buildPropertiesContext($availableProperties);
        $dataCollectionContext = $this->buildDataCollectionContext($leadData);

        $customPrompt = AppSetting::getValue('ai_prompt_custom', null);

        if (!empty($customPrompt)) {
            $systemPrompt = str_replace(
                ['{$assistantName}', '{$companyName}', '{$audioInstruction}', '{$propertiesContext}'],
                [$assistantName, $companyName, $audioInstruction, $propertiesContext],
                (string) $customPrompt
            );
            $systemPrompt .= "\n\nREGRA FIXA: responda sempre em portugues do Brasil, com no maximo 30 palavras, uma pergunta por vez.";
        } else {
            $systemPrompt = "Voce e {$assistantName}, assistente imobiliario virtual da {$companyName}.

REGRAS:
- Responda sempre em portugues do Brasil.
- Use no maximo 30 palavras.
- Faca uma pergunta por vez.
- Nunca invente dados de imoveis; use apenas os imoveis reais fornecidos.
- Colete bairro/regiao, orcamento, quartos e prazo de compra.
- Quando ja houver criterios suficientes, confirme que vai buscar opcoes compativeis.
- Nao diga que e IA ou robo.{$audioInstruction}

{$propertiesContext}

{$dataCollectionContext}";
        }

        $userPrompt = ($context ? "Historico da conversa:\n{$context}\n\n" : '') .
            "Mensagem do cliente: {$message}\n\nResposta curta:";

        $result = $this->chatCompletion($systemPrompt, $userPrompt, 30, 90);

        if (!($result['success'] ?? false)) {
            Log::warning('[HuggingFace] Falha ao processar mensagem', [
                'model' => $this->resolveModelForRequest(),
                'error' => $result['error'] ?? 'unknown',
                'status' => $result['status'] ?? null,
            ]);
        }

        return $result;
    }

    private function chatCompletion(string $systemPrompt, string $userPrompt, ?int $maxWords = 30, int $maxTokens = 90): array
    {
        $apiKey = $this->resolveApiKey();
        if ($apiKey === '') {
            return [
                'success' => false,
                'error' => 'Hugging Face API key not configured',
            ];
        }

        $payload = [
            'model' => $this->resolveModelForRequest(),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => (float) env('HUGGINGFACE_CHAT_TEMPERATURE', 0.4),
            'max_tokens' => $maxTokens,
            'stream' => false,
        ];

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout((int) env('HUGGINGFACE_CHAT_TIMEOUT', 60))
                ->post($this->resolveChatEndpoint(), $payload);
        } catch (\Throwable $exception) {
            Log::error('[HuggingFace] Erro de conexao', [
                'endpoint' => $this->resolveChatEndpoint(),
                'model' => $payload['model'],
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Unable to contact Hugging Face chat endpoint',
                'exception' => $exception->getMessage(),
            ];
        }

        if (!$response->successful()) {
            Log::error('[HuggingFace] Chat completion falhou', [
                'endpoint' => $this->resolveChatEndpoint(),
                'model' => $payload['model'],
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 800),
            ]);

            return [
                'success' => false,
                'error' => 'Hugging Face chat request failed',
                'status' => $response->status(),
                'body' => $response->body(),
            ];
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content']
            ?? $data['choices'][0]['text']
            ?? null;

        if (!is_string($content) || trim($content) === '') {
            Log::error('[HuggingFace] Resposta em formato inesperado', [
                'model' => $payload['model'],
                'payload' => $data,
            ]);

            return [
                'success' => false,
                'error' => 'Unexpected Hugging Face chat response format',
                'payload' => $data,
            ];
        }

        return [
            'success' => true,
            'content' => $this->limitResponseWords($content, $maxWords),
            'model' => $payload['model'],
        ];
    }

    private function buildPropertiesContext(array $availableProperties): string
    {
        if (empty($availableProperties)) {
            return 'IMOVEIS DISPONIVEIS: nenhum imovel foi enviado no contexto desta chamada.';
        }

        $lines = ['IMOVEIS DISPONIVEIS NO BANCO DE DADOS:'];
        foreach (array_slice($availableProperties, 0, 12) as $property) {
            $dormitorios = (int) ($property['dormitorios'] ?? 0);
            $suites = (int) ($property['suites'] ?? 0);
            $totalQuartos = $dormitorios + $suites;
            $valor = isset($property['valor_venda'])
                ? 'R$ ' . number_format((float) $property['valor_venda'], 0, ',', '.')
                : 'sob consulta';

            $lines[] = sprintf(
                '- Codigo %s | %s | %s, %s | %dq | %s',
                $property['codigo_imovel'] ?? 'N/A',
                $property['tipo_imovel'] ?? 'Imovel',
                $property['bairro'] ?? 'Bairro nao informado',
                $property['cidade'] ?? 'Cidade nao informada',
                $totalQuartos,
                $valor
            );
        }

        return implode("\n", $lines);
    }

    private function buildDataCollectionContext($leadData): string
    {
        $missing = [];

        if (is_array($leadData)) {
            if (empty($leadData['localizacao']) && empty($leadData['preferencia_bairro'])) {
                $missing[] = 'bairro ou regiao';
            }
            if (empty($leadData['budget_min']) && empty($leadData['budget_max'])) {
                $missing[] = 'faixa de valor';
            }
            if (empty($leadData['quartos'])) {
                $missing[] = 'quantidade de quartos';
            }
            if (empty($leadData['prazo_compra'])) {
                $missing[] = 'prazo de compra';
            }
        } else {
            $missing = ['bairro ou regiao', 'faixa de valor', 'quantidade de quartos', 'prazo de compra'];
        }

        if (empty($missing)) {
            return 'DADOS DO LEAD: criterios principais coletados. Confirme e encaminhe para busca de opcoes.';
        }

        return 'DADOS FALTANTES DO LEAD: ' . implode(', ', $missing) .
            ". Priorize o primeiro item faltante e sugira um exemplo de resposta.";
    }

    private function limitResponseWords(string $content, ?int $maxWords): string
    {
        $content = trim(preg_replace('/\s+/', ' ', $content) ?? $content);
        if ($maxWords === null || $maxWords <= 0) {
            return $content;
        }

        $words = preg_split('/\s+/', $content) ?: [];
        if (count($words) <= $maxWords) {
            return $content;
        }

        return implode(' ', array_slice($words, 0, $maxWords));
    }

    private function resolveChatEndpoint(): string
    {
        $explicit = trim((string) env('HUGGINGFACE_CHAT_COMPLETIONS_URL', ''));
        if ($explicit !== '') {
            return $explicit;
        }

        return rtrim((string) env('HUGGINGFACE_CHAT_BASE_URL', 'https://router.huggingface.co/v1'), '/') . '/chat/completions';
    }

    private function resolveModelForRequest(): string
    {
        $model = $this->resolveModel();
        $policy = trim((string) env('HUGGINGFACE_PROVIDER_POLICY', ''));

        if ($policy !== '' && !str_contains($model, ':')) {
            return $model . ':' . $policy;
        }

        return $model;
    }

    private function resolveModel(): string
    {
        $chatModel = trim((string) env('HUGGINGFACE_CHAT_MODEL', ''));
        if ($chatModel !== '') {
            return $chatModel;
        }

        $legacyModel = trim((string) env('HUGGINGFACE_MODEL', ''));
        if ($legacyModel !== '' && !str_contains(strtolower($legacyModel), 'embed') && !str_contains(strtolower($legacyModel), 'nomic')) {
            return $legacyModel;
        }

        return self::DEFAULT_CHAT_MODEL;
    }

    private function resolveApiKey(): string
    {
        foreach (['HUGGINGFACE_API_KEY', 'HUGGINGFACE_TOKEN', 'HF_TOKEN'] as $key) {
            $value = trim((string) env($key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function resolveAssistantName(): string
    {
        $tenant = $this->currentTenant();
        if ($tenant && method_exists($tenant, 'getAiAssistantName')) {
            $name = trim((string) $tenant->getAiAssistantName());
            if ($name !== '') {
                return $name;
            }
        }

        return trim((string) AppSetting::getValue('ai_name', env('AI_ASSISTANT_NAME', 'Teresa'))) ?: 'Teresa';
    }

    private function resolveCompanyName(): string
    {
        $tenant = $this->currentTenant();
        if ($tenant && method_exists($tenant, 'getCompanyName')) {
            return $tenant->getCompanyName();
        }

        return env('COMPANY_NAME', 'Imobiliaria');
    }

    private function currentTenant()
    {
        if (!app()->bound('tenant')) {
            return null;
        }

        try {
            return app()->make('tenant');
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
