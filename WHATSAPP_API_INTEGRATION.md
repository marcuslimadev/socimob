# Integração WhatsApp Business API - Alternativa ao Twilio

**Objetivo:** Implementar integração direta com WhatsApp Business API como alternativa ao Twilio para reduzir custos e ter mais controle sobre mensagens.

---

## 📊 Comparação: Twilio vs WhatsApp API

| Critério | Twilio | WhatsApp API (Meta) |
|----------|--------|---------------------|
| **Custo** | ~R$ 0,25/msg | ~R$ 0,08-0,15/msg |
| **Setup** | Simples (5 min) | Médio (1-2 dias) |
| **Templates** | Limitado | Completo com variáveis |
| **Mídia** | Sim | Sim (imagens, PDFs, vídeos) |
| **Botões** | Não | Sim (call-to-action, quick reply) |
| **Webhook** | Sim | Sim |
| **Limite** | Alto | Médio (tier-based) |

**Recomendação:** Usar WhatsApp API para reduzir custos em ~60-70%.

---

## 🏗️ Arquitetura Proposta

### Opção 1: Meta Cloud API (Recomendado)
```
Frontend → Backend Lumen → Meta Cloud API → WhatsApp
                ↑
            Webhooks
```

**Vantagens:**
- Sem servidor próprio
- Escalável automaticamente
- Mais barato (sem custo de infraestrutura)
- Setup rápido (1-2 dias)

### Opção 2: On-Premises API
```
Frontend → Backend Lumen → WhatsApp Business API Container → WhatsApp
                ↑
            Webhooks
```

**Vantagens:**
- Controle total
- Sem limite de mensagens
- Dados 100% privados

**Desvantagens:**
- Requer servidor dedicado
- Mais complexo
- Custo de infra

---

## 🔧 Implementação - Meta Cloud API

### 1. Pré-requisitos

1. **Conta Meta Business** (gratuita)
2. **Número de telefone** dedicado (WhatsApp Business)
3. **Webhook** público (já temos: https://lojadaesquina.store/webhook/whatsapp)

### 2. Estrutura de Arquivos

```
app/
├── Services/
│   ├── WhatsAppService.php (novo - Cloud API)
│   └── TwilioService.php (manter como fallback)
├── Http/Controllers/
│   └── WebhookController.php (atualizar)
└── config/
    └── whatsapp.php (novo)
```

### 3. Código - WhatsAppService.php

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private $apiUrl;
    private $accessToken;
    private $phoneNumberId;
    private $businessAccountId;

    public function __construct()
    {
        $this->apiUrl = 'https://graph.facebook.com/v18.0';
        $this->accessToken = env('WHATSAPP_ACCESS_TOKEN');
        $this->phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');
        $this->businessAccountId = env('WHATSAPP_BUSINESS_ACCOUNT_ID');
    }

    /**
     * Enviar mensagem de template (ex: boas-vindas, notificação)
     */
    public function enviarTemplate($para, $templateName, $variables = [])
    {
        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $this->formatPhone($para),
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => 'pt_BR'],
                ]
            ];

            // Adicionar variáveis se houver
            if (!empty($variables)) {
                $payload['template']['components'] = [
                    [
                        'type' => 'body',
                        'parameters' => array_map(function($var) {
                            return ['type' => 'text', 'text' => $var];
                        }, $variables)
                    ]
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", $payload);

            if ($response->successful()) {
                Log::info('Template WhatsApp enviado', [
                    'para' => $para,
                    'template' => $templateName,
                    'message_id' => $response->json()['messages'][0]['id']
                ]);

                return [
                    'success' => true,
                    'message_id' => $response->json()['messages'][0]['id']
                ];
            }

            throw new \Exception($response->body());

        } catch (\Exception $e) {
            Log::error('Erro ao enviar template WhatsApp', [
                'erro' => $e->getMessage(),
                'para' => $para
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Enviar mensagem de texto simples (apenas em sessão ativa 24h)
     */
    public function enviarTexto($para, $mensagem)
    {
        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $this->formatPhone($para),
                'type' => 'text',
                'text' => [
                    'body' => $mensagem
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message_id' => $response->json()['messages'][0]['id']
                ];
            }

            throw new \Exception($response->body());

        } catch (\Exception $e) {
            Log::error('Erro ao enviar texto WhatsApp', ['erro' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Enviar imagem
     */
    public function enviarImagem($para, $imageUrl, $caption = '')
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->formatPhone($para),
            'type' => 'image',
            'image' => [
                'link' => $imageUrl,
                'caption' => $caption
            ]
        ];

        return $this->enviarMidia($payload);
    }

    /**
     * Enviar documento (PDF, DOCX, etc)
     */
    public function enviarDocumento($para, $documentUrl, $filename, $caption = '')
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->formatPhone($para),
            'type' => 'document',
            'document' => [
                'link' => $documentUrl,
                'filename' => $filename,
                'caption' => $caption
            ]
        ];

        return $this->enviarMidia($payload);
    }

    /**
     * Enviar mensagem com botões interativos
     */
    public function enviarBotoes($para, $mensagem, $botoes)
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->formatPhone($para),
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $mensagem],
                'action' => [
                    'buttons' => array_map(function($btn, $index) {
                        return [
                            'type' => 'reply',
                            'reply' => [
                                'id' => $btn['id'] ?? 'btn_' . $index,
                                'title' => substr($btn['text'], 0, 20) // Max 20 chars
                            ]
                        ];
                    }, $botoes, array_keys($botoes))
                ]
            ]
        ];

        return $this->enviarMidia($payload);
    }

    /**
     * Processar webhook de mensagem recebida
     */
    public function processarWebhook($payload)
    {
        try {
            $entry = $payload['entry'][0] ?? null;
            if (!$entry) return null;

            $changes = $entry['changes'][0] ?? null;
            if (!$changes) return null;

            $value = $changes['value'] ?? null;
            if (!$value) return null;

            // Mensagem recebida
            $messages = $value['messages'] ?? [];
            if (empty($messages)) return null;

            $message = $messages[0];

            return [
                'from' => $message['from'],
                'message_id' => $message['id'],
                'timestamp' => $message['timestamp'],
                'type' => $message['type'],
                'text' => $message['text']['body'] ?? null,
                'image' => $message['image'] ?? null,
                'document' => $message['document'] ?? null,
                'name' => $value['contacts'][0]['profile']['name'] ?? 'Desconhecido'
            ];

        } catch (\Exception $e) {
            Log::error('Erro ao processar webhook WhatsApp', ['erro' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Marcar mensagem como lida
     */
    public function marcarComoLida($messageId)
    {
        try {
            Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
            ])->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $messageId
            ]);
        } catch (\Exception $e) {
            Log::warning('Falha ao marcar mensagem como lida', ['erro' => $e->getMessage()]);
        }
    }

    // Métodos auxiliares

    private function enviarMidia($payload)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message_id' => $response->json()['messages'][0]['id']
                ];
            }

            throw new \Exception($response->body());

        } catch (\Exception $e) {
            Log::error('Erro ao enviar mídia WhatsApp', ['erro' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function formatPhone($phone)
    {
        // Remover caracteres não numéricos
        $cleaned = preg_replace('/\D/', '', $phone);

        // Adicionar código do país se não tiver
        if (strlen($cleaned) === 11 && substr($cleaned, 0, 2) !== '55') {
            $cleaned = '55' . $cleaned;
        }

        return $cleaned;
    }
}
```

### 4. Configuração - .env

```env
# WhatsApp Business API (Meta Cloud)
WHATSAPP_PROVIDER=meta  # ou 'twilio' para fallback
WHATSAPP_ACCESS_TOKEN=EAAxxxxxxxxxxxx
WHATSAPP_PHONE_NUMBER_ID=1234567890
WHATSAPP_BUSINESS_ACCOUNT_ID=9876543210
WHATSAPP_VERIFY_TOKEN=seu_token_aleatorio_seguro_aqui

# Twilio (Fallback)
TWILIO_ACCOUNT_SID=ACxxxx
TWILIO_AUTH_TOKEN=xxxx
TWILIO_WHATSAPP_NUMBER=+14155238886
```

### 5. Controller - Atualizar WebhookController

```php
public function whatsapp(Request $request)
{
    // Verificação de webhook (GET - Meta)
    if ($request->isMethod('get')) {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === env('WHATSAPP_VERIFY_TOKEN')) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    // Processar mensagem recebida (POST)
    $whatsappService = new WhatsAppService();
    $mensagem = $whatsappService->processarWebhook($request->all());

    if (!$mensagem) {
        return response()->json(['status' => 'ignored'], 200);
    }

    // Marcar como lida
    $whatsappService->marcarComoLida($mensagem['message_id']);

    // Processar mensagem do lead
    // ... (lógica existente)

    return response()->json(['status' => 'ok'], 200);
}
```

---

## 📝 Templates Aprovados (Exemplos)

### 1. Boas-vindas ao Lead
```
Nome: welcome_lead
Conteúdo:
Olá {{1}}! 👋

Obrigado por entrar em contato com a *{{2}}*!

Vi que você tem interesse em imóveis. Estou aqui para ajudar!

Em que posso te auxiliar hoje?
```

### 2. Notificação de Vistoria Agendada
```
Nome: vistoria_agendada
Conteúdo:
Olá {{1}}! 🏠

Sua vistoria foi agendada com sucesso!

📅 Data: {{2}}
🕒 Horário: {{3}}
📍 Local: {{4}}

Aguardamos você!
```

---

## 🚀 Plano de Implementação

### Fase 1: Setup (1 dia)
1. Criar conta Meta Business
2. Adicionar número WhatsApp
3. Configurar webhook
4. Criar templates iniciais

### Fase 2: Desenvolvimento (2 dias)
1. Criar `WhatsAppService.php`
2. Atualizar `WebhookController.php`
3. Criar toggle no `.env` (Twilio vs Meta)
4. Testes unitários

### Fase 3: Migração (1 dia)
1. Aprovar templates no Meta
2. Migrar leads ativos
3. Monitorar por 24h
4. Desativar Twilio

---

## 💰 Economia Estimada

**Cenário:** 10.000 mensagens/mês

| Provedor | Custo/msg | Total/mês |
|----------|-----------|-----------|
| Twilio | R$ 0,25 | R$ 2.500 |
| Meta API | R$ 0,10 | R$ 1.000 |
| **Economia** | | **R$ 1.500/mês** |

**ROI:** 60% de redução de custos

---

## ⚠️ Limitações WhatsApp API

1. **Templates obrigatórios** para primeira mensagem (após 24h)
2. **Aprovação Meta** demora 1-2 dias
3. **Limite de mensagens** baseado em tier (cresce com uso)
4. **Sessão de 24h** - após isso, só templates

---

## 🔐 Segurança

- ✅ Validação de webhook token
- ✅ HTTPS obrigatório
- ✅ Rate limiting
- ✅ Logs de auditoria
- ✅ Criptografia de access tokens

---

## 📊 Monitoramento

Adicionar métricas no Dashboard:
- Mensagens enviadas (Meta vs Twilio)
- Taxa de entrega
- Custo por canal
- Templates mais usados

---

## ✅ Próximos Passos

1. Aprovar integração Meta
2. Criar conta Meta Business
3. Implementar `WhatsAppService.php`
4. Criar templates no Meta Business Manager
5. Testar em sandbox
6. Migrar para produção

---

**Recomendação Final:** Implementar Meta Cloud API em paralelo com Twilio, fazendo migração gradual. Isso garante fallback e permite comparação de custos/performance.
