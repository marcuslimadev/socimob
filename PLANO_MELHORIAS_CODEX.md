# 🎯 PLANO DE MELHORIAS - SOCIMOB (Para Execução pelo Codex)

**Data:** 19/01/2026  
**Status:** Ambiente configurado ✅ | Melhorias pendentes ⚙️

---

## ✅ JÁ CONCLUÍDO

### 1. Ambiente de Desenvolvimento
- ✅ XAMPP/PHP 8.2.12 configurado
- ✅ MySQL/MariaDB 10.4.32 funcionando
- ✅ Banco `exclusiva` criado com 25 tabelas
- ✅ Arquivo `.env` criado
- ✅ Servidor PHP rodando em http://127.0.0.1:8000
- ✅ Script `server-manager.ps1` criado

### 2. Validações Iniciadas
- ✅ `AuthController` - validação em `login()` e `googleLogin()`
- 🔄 Restante dos controllers pendente

---

## 🔴 MELHORIAS CRÍTICAS (Executar Primeiro)

### 1. **Adicionar Validações em Todos os Controllers**

#### Arquivos para modificar:

**a) app/Http/Controllers/Admin/LeadsController.php**
```php
// Método store() - criar lead
$validator = Validator::make($request->all(), [
    'nome' => 'required|string|max:255',
    'telefone' => 'nullable|string|max:20',
    'whatsapp' => 'nullable|string|max:20',
    'email' => 'nullable|email|max:255',
    'origem' => 'nullable|string|max:100',
    'status' => 'nullable|in:novo,contato,visita,proposta,fechado,perdido',
    'observacoes' => 'nullable|string|max:2000',
]);

// Método update() - atualizar lead
$validator = Validator::make($request->all(), [
    'nome' => 'sometimes|string|max:255',
    'telefone' => 'sometimes|string|max:20',
    'whatsapp' => 'sometimes|string|max:20',
    'email' => 'sometimes|email|max:255',
    'status' => 'sometimes|in:novo,contato,visita,proposta,fechado,perdido',
]);
```

**b) app/Http/Controllers/PropertyController.php**
```php
// Método store() - criar imóvel
$validator = Validator::make($request->all(), [
    'titulo' => 'required|string|max:255',
    'tipo' => 'required|in:casa,apartamento,terreno,comercial,rural',
    'finalidade' => 'required|in:venda,aluguel,ambos',
    'status' => 'required|in:disponivel,vendido,alugado,inativo',
    'preco_venda' => 'nullable|numeric|min:0',
    'preco_aluguel' => 'nullable|numeric|min:0',
    'area_total' => 'nullable|numeric|min:0',
    'area_construida' => 'nullable|numeric|min:0',
    'quartos' => 'nullable|integer|min:0|max:20',
    'suites' => 'nullable|integer|min:0|max:20',
    'banheiros' => 'nullable|integer|min:0|max:20',
    'vagas_garagem' => 'nullable|integer|min:0|max:20',
    'cep' => 'nullable|string|max:10',
    'cidade' => 'nullable|string|max:100',
    'bairro' => 'nullable|string|max:100',
    'endereco' => 'nullable|string|max:255',
]);
```

**c) app/Http/Controllers/Portal/ClientAuthController.php**
```php
// Método register() - registro de cliente
$validator = Validator::make($request->all(), [
    'name' => 'required|string|max:255',
    'email' => 'required|email|max:255|unique:users,email',
    'password' => 'required|string|min:6|max:255|confirmed',
    'telefone' => 'nullable|string|max:20',
]);
```

**d) app/Http/Controllers/ConversasController.php**
```php
// Método sendMessage() - enviar mensagem
$validator = Validator::make($request->all(), [
    'mensagem' => 'required|string|max:5000',
    'type' => 'sometimes|in:text,audio,image,file',
]);
```

### 2. **Corrigir Queries N+1 (Eager Loading)**

#### Arquivos para modificar:

**a) app/Http/Controllers/Admin/LeadsController.php**
```php
// Método index() - listar leads
public function index(Request $request)
{
    $tenantId = $request->get('tenant_id');
    
    $leads = Lead::where('tenant_id', $tenantId)
        ->with(['conversa', 'user']) // ⚡ Eager loading
        ->orderBy('created_at', 'desc')
        ->paginate(50);
    
    return response()->json($leads);
}
```

**b) app/Http/Controllers/ConversasController.php**
```php
// Método index() - listar conversas
public function index(Request $request)
{
    $tenantId = $request->get('tenant_id');
    
    $conversas = Conversa::where('tenant_id', $tenantId)
        ->with(['lead', 'corretor', 'mensagens' => function($query) {
            $query->orderBy('created_at', 'desc')->limit(1);
        }]) // ⚡ Eager loading
        ->orderBy('ultima_atividade', 'desc')
        ->get();
    
    return response()->json($conversas);
}

// Método mensagens() - listar mensagens de uma conversa
public function mensagens(Request $request, $id)
{
    $tenantId = $request->get('tenant_id');
    
    $mensagens = Mensagem::whereHas('conversa', function($query) use ($tenantId) {
            $query->where('tenant_id', $tenantId);
        })
        ->where('conversa_id', $id)
        ->with('user') // ⚡ Eager loading
        ->orderBy('created_at', 'asc')
        ->get();
    
    return response()->json($mensagens);
}
```

**c) app/Http/Controllers/PropertyController.php**
```php
// Método index() - listar imóveis
public function index(Request $request)
{
    $tenantId = $request->get('tenant_id');
    
    $properties = Property::where('tenant_id', $tenantId)
        ->with('images') // ⚡ Eager loading
        ->orderBy('created_at', 'desc')
        ->paginate(20);
    
    return response()->json($properties);
}
```

### 3. **Adicionar tenant_id em Todos os Logs**

#### Arquivos para modificar:

**a) app/Services/LeadAutomationService.php**
```php
// Em TODOS os Log::info(), Log::warning(), Log::error():
Log::info('[LeadAutomation] Mensagem', [
    'tenant_id' => $lead->tenant_id,  // ✅ Adicionar
    'lead_id' => $lead->id,
    // ... outros campos
]);
```

**b) app/Services/WhatsAppService.php**
```php
// Em TODOS os logs:
Log::info('[WhatsApp] Mensagem', [
    'tenant_id' => $tenant->id ?? null,  // ✅ Adicionar
    'conversa_id' => $conversa->id,
    // ... outros campos
]);
```

**c) app/Services/TwilioService.php**
```php
// Em TODOS os logs:
Log::info('[Twilio] Mensagem', [
    'tenant_id' => $this->getTenantId(),  // ✅ Adicionar método helper
    'to' => $to,
    // ... outros campos
]);

// Adicionar método helper:
private function getTenantId() {
    return app()->bound('tenant') ? app('tenant')->id : null;
}
```

### 4. **Adicionar Timeout em OpenAI**

#### Arquivo: app/Services/OpenAIService.php

```php
use Illuminate\Support\Facades\Http;

public function chatCompletion($system, $user, $model = null)
{
    $model = $model ?: $this->model;
    
    try {
        $response = Http::timeout(10) // ⚡ Timeout de 10 segundos
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'temperature' => 0.7,
            ]);
        
        // ... resto do código
    } catch (\Illuminate\Http\Client\ConnectionException $e) {
        Log::error('[OpenAI] Timeout na requisição', [
            'error' => $e->getMessage(),
            'timeout' => 10
        ]);
        return null; // Fallback
    }
}

// Mesmo para transcribeAudio():
public function transcribeAudio($audioPath)
{
    try {
        $response = Http::timeout(30) // ⚡ Timeout maior para transcrição
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])
            ->attach('file', fopen($audioPath, 'r'), basename($audioPath))
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
            ]);
        
        // ... resto do código
    } catch (\Illuminate\Http\Client\ConnectionException $e) {
        Log::error('[OpenAI] Timeout na transcrição', [
            'error' => $e->getMessage(),
            'timeout' => 30
        ]);
        return null;
    }
}
```

---

## 🟡 MELHORIAS IMPORTANTES

### 5. **Implementar Rate Limiting**

#### Arquivo: bootstrap/app.php

```php
// Adicionar throttle middleware
$app->routeMiddleware([
    'auth' => App\Http\Middleware\Authenticate::class,
    'simple-auth' => App\Http\Middleware\SimpleTokenAuth::class,
    'throttle' => Illuminate\Routing\Middleware\ThrottleRequests::class, // ⚡ Novo
]);
```

#### Arquivo: routes/web.php

```php
// Aplicar rate limit em rotas de login
$router->post('/api/auth/login', [
    'middleware' => 'throttle:5,1', // 5 tentativas por minuto
    'uses' => 'AuthController@login'
]);
```

### 6. **Adicionar Loading States no Frontend**

#### Arquivo: public/app/js/loading.js (CRIAR NOVO)

```javascript
// Loading utilities
const Loading = {
    show: function(elementId = 'app') {
        const spinner = `
            <div class="loading-overlay" id="loading-${elementId}">
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p class="loading-text">Carregando...</p>
                </div>
            </div>
        `;
        document.getElementById(elementId).insertAdjacentHTML('beforeend', spinner);
    },
    
    hide: function(elementId = 'app') {
        const overlay = document.getElementById(`loading-${elementId}`);
        if (overlay) overlay.remove();
    },
    
    button: function(buttonElement, loading = true) {
        if (loading) {
            buttonElement.disabled = true;
            buttonElement.dataset.originalText = buttonElement.textContent;
            buttonElement.innerHTML = '<span class="spinner-small"></span> Processando...';
        } else {
            buttonElement.disabled = false;
            buttonElement.textContent = buttonElement.dataset.originalText || 'Enviar';
        }
    }
};
```

#### Arquivo: public/css/loading.css (CRIAR NOVO)

```css
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.loading-spinner {
    text-align: center;
}

.spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3b82f6;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.spinner-small {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid #fff;
    border-top: 2px solid transparent;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

.loading-text {
    color: white;
    font-size: 18px;
}
```

#### Usar em leads.html, imoveis.html, etc:

```javascript
// Exemplo em leads.html
async function salvarLead() {
    const btn = document.getElementById('btn-salvar');
    Loading.button(btn, true); // Ativar loading
    
    try {
        const response = await fetch('/api/admin/leads', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('token')}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(leadData)
        });
        
        // ... processar resposta
    } catch (error) {
        console.error(error);
    } finally {
        Loading.button(btn, false); // Desativar loading
    }
}
```

---

## 🟢 MELHORIAS RECOMENDADAS

### 7. **Criar Sistema de Logs Estruturados**

#### Arquivo: app/Services/LogService.php (CRIAR NOVO)

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class LogService
{
    public static function info($context, $message, $data = [])
    {
        self::log('info', $context, $message, $data);
    }
    
    public static function error($context, $message, $data = [])
    {
        self::log('error', $context, $message, $data);
    }
    
    public static function warning($context, $message, $data = [])
    {
        self::log('warning', $context, $message, $data);
    }
    
    private static function log($level, $context, $message, $data)
    {
        $logData = array_merge([
            'tenant_id' => app()->bound('tenant') ? app('tenant')->id : null,
            'user_id' => auth()->check() ? auth()->id() : null,
            'timestamp' => now()->toIso8601String(),
        ], $data);
        
        Log::$level("[{$context}] {$message}", $logData);
    }
}
```

**Uso:**
```php
// Substituir Log::info() por:
LogService::info('LeadAutomation', 'Atendimento iniciado', [
    'lead_id' => $lead->id,
    'conversa_id' => $conversa->id,
]);
```

### 8. **Criar Postman Collection**

#### Arquivo: SOCIMOB_API.postman_collection.json (CRIAR)

```json
{
  "info": {
    "name": "SOCIMOB API",
    "description": "Collection completa da API SOCIMOB",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "auth": {
    "type": "bearer",
    "bearer": [
      {
        "key": "token",
        "value": "{{auth_token}}",
        "type": "string"
      }
    ]
  },
  "item": [
    {
      "name": "Auth",
      "item": [
        {
          "name": "Login",
          "request": {
            "method": "POST",
            "header": [],
            "body": {
              "mode": "raw",
              "raw": "{\"email\":\"admin@exclusiva.com\",\"password\":\"password\"}",
              "options": {
                "raw": {
                  "language": "json"
                }
              }
            },
            "url": {
              "raw": "{{base_url}}/api/auth/login",
              "host": ["{{base_url}}"],
              "path": ["api", "auth", "login"]
            }
          }
        }
      ]
    }
  ]
}
```

---

## 📋 CHECKLIST DE EXECUÇÃO

### Codex - Execute nesta ordem:

- [ ] **1. Validações em Controllers**
  - [ ] LeadsController (store, update)
  - [ ] PropertyController (store, update)
  - [ ] ClientAuthController (register)
  - [ ] ConversasController (sendMessage)

- [ ] **2. Eager Loading**
  - [ ] LeadsController::index()
  - [ ] ConversasController::index()
  - [ ] ConversasController::mensagens()
  - [ ] PropertyController::index()

- [ ] **3. Logs Estruturados**
  - [ ] LeadAutomationService (todos os logs)
  - [ ] WhatsAppService (todos os logs)
  - [ ] TwilioService (todos os logs + método helper)

- [ ] **4. Timeout OpenAI**
  - [ ] OpenAIService::chatCompletion() (10s)
  - [ ] OpenAIService::transcribeAudio() (30s)

- [ ] **5. Rate Limiting**
  - [ ] Adicionar middleware throttle
  - [ ] Aplicar em /api/auth/login

- [ ] **6. Loading States Frontend**
  - [ ] Criar loading.js
  - [ ] Criar loading.css
  - [ ] Adicionar em login.html
  - [ ] Adicionar em leads.html
  - [ ] Adicionar em imoveis.html

- [ ] **7. LogService (opcional)**
  - [ ] Criar LogService.php
  - [ ] Refatorar logs existentes

- [ ] **8. Documentação**
  - [ ] Criar Postman collection
  - [ ] Atualizar README.md

---

## 🎯 RESULTADO ESPERADO

Após executar todas as melhorias:

- ✅ **Segurança:** 95% (validações + rate limiting)
- ✅ **Performance:** 85% (eager loading + timeout)
- ✅ **UX:** 90% (loading states)
- ✅ **Manutenibilidade:** 95% (logs estruturados)
- ✅ **Documentação:** 85% (Postman collection)

---

**Data de criação:** 19/01/2026  
**Status:** Pronto para execução pelo Codex
