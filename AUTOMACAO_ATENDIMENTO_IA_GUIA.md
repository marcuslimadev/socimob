# 🤖 Automação de Atendimento IA - Leads Chaves na Mão

## Visão Geral

Sistema de automação que inicia atendimento via WhatsApp automaticamente para todos os leads recebidos da integração **Chaves na Mão**. A IA gera mensagens personalizadas com base no contexto completo do lead e dá continuidade ao atendimento.

## 🎯 Funcionalidades

### 1. Automação Automática
✅ **Triggers automáticos**:
- Quando lead é criado via integração Chaves na Mão
- Sistema detecta automaticamente pela origem
- Valida número de WhatsApp
- Cria conversa se não existir
- Envia primeira mensagem personalizada via IA
- Registra tudo no banco de dados

### 2. Inicialização Manual
✅ **Botão em cada lead**:
- Ícone de robô em cada card de lead
- Permite iniciar atendimento manualmente
- Útil para leads que não foram automatizados
- Funciona para qualquer lead (não só Chaves na Mão)

### 3. Processamento em Lote
✅ **API para múltiplos leads**:
- Endpoint para processar vários leads de uma vez
- Retorna estatísticas (sucesso/falha)
- Logs detalhados de cada operação

## 🔄 Fluxo Completo

```
1. Lead criado via Chaves na Mão
   ↓
2. LeadObserver detecta (isFromChavesNaMao)
   ↓
3. Chama LeadAutomationService
   ↓
4. Valida WhatsApp (formato brasileiro)
   ↓
5. Cria/reutiliza Conversa
   ↓
6. OpenAI gera mensagem personalizada (contexto do lead)
   ↓
7. Envia via Twilio WhatsApp
   ↓
8. Registra mensagem no banco
   ↓
9. Atualiza status do lead
   ↓
10. IA continua atendimento normalmente
```

## 📋 Componentes Criados

### Backend

**LeadAutomationService** (`app/Services/LeadAutomationService.php`)
- `iniciarAtendimento(Lead $lead, bool $forceStart)` - Inicia para um lead
- `iniciarAtendimentoEmLote(array $leadIds)` - Processa múltiplos
- `validarWhatsApp($telefone)` - Valida formato
- `gerarMensagemInicial(Lead $lead)` - IA personalizada
- `montarContextoLead(Lead $lead)` - Extrai dados completos
- `enviarMensagemWhatsApp(...)` - Twilio
- `registrarMensagem(...)` - Salva no banco

**LeadsController** (`app/Http/Controllers/Admin/LeadsController.php`)
- `iniciarAtendimento(Request $request, $id)` - Manual
- `iniciarAtendimentoLote(Request $request)` - Lote

**LeadObserver** (`app/Observers/LeadObserver.php`)
- Modificado método `created()` - Hook automático
- Método `iniciarAtendimentoIA(Lead $lead)` - Chama serviço

### Frontend

**leads.html** (`public/app/leads.html`)
- Botão "Iniciar Atendimento IA" em cada card
- Função `iniciarAtendimentoIA(id)` - AJAX call
- Feedback visual (spinner + alerts)

### Rotas

```php
POST /api/admin/leads/{id}/iniciar-atendimento
POST /api/admin/leads/iniciar-atendimento-lote
```

## 💡 Como Funciona a Personalização

### Contexto Capturado do Lead

```php
- Nome
- Email
- Telefone
- Tipo de interesse (compra/aluguel/venda)
- Preferências (quartos, localização, valor)
- Observações da integração Chaves na Mão
- Origem
```

### Prompt OpenAI

```
"Você é um assistente imobiliário iniciando contato com um lead 
que demonstrou interesse.

CONTEXTO DO LEAD:
Nome: João Silva
Email: joao@email.com
Telefone: 11987654321
Interesse: Apartamento 2 quartos para compra
Preferências: Zona Sul, até R$ 400.000
Origem: chavesnamao

INSTRUÇÕES:
- Faça uma abordagem amigável e personalizada
- Mencione o interesse específico do lead
- Seja direto mas cordial
- Pergunte quando seria um bom momento para conversar
- Máximo 3 linhas

Gere a mensagem de primeiro contato:"
```

### Mensagem Gerada (exemplo)

```
Bom dia, João! Meu nome é Alex, assistente virtual da Exclusiva Lar Imóveis.

Vi que você está interessado em apartamento de 2 quartos na Zona Sul 
até R$ 400.000. Temos algumas opções incríveis que combinam com seu perfil!

Quando seria um bom momento para conversarmos e eu te mostrar os imóveis?
```

### Fallback (se OpenAI falhar)

```php
private function mensagemInicialPadrao(Lead $lead)
{
    "{Saudação}! Meu nome é Alex, assistente virtual da Exclusiva Lar Imóveis.

    Vi que você demonstrou interesse em nossos imóveis.
    Gostaria de te ajudar a encontrar o imóvel ideal!

    Quando seria um bom momento para conversarmos?"
}
```

## 🚀 Como Usar

### Automação Automática (Chaves na Mão)

**Já está ativa!** Não precisa fazer nada.

Quando um lead chega via integração Chaves na Mão:
1. Sistema detecta automaticamente
2. Envia mensagem via WhatsApp
3. Lead fica em "em_atendimento"
4. IA continua conversa normalmente

### Iniciar Manualmente (Interface)

1. Acesse: `http://127.0.0.1:8000/app/leads.html`
2. Localize o lead desejado
3. Clique no botão com **ícone de robô** (🤖)
4. Confirme a ação
5. Aguarde mensagem de sucesso

### API - Iniciar para Um Lead

```bash
POST /api/admin/leads/123/iniciar-atendimento
Authorization: Bearer {token}
```

**Resposta:**
```json
{
  "success": true,
  "message": "Atendimento IA iniciado com sucesso",
  "data": {
    "lead_id": 123,
    "conversa_id": 456,
    "mensagem": "Bom dia! Meu nome é Alex..."
  }
}
```

### API - Iniciar em Lote

```bash
POST /api/admin/leads/iniciar-atendimento-lote
Authorization: Bearer {token}
Content-Type: application/json

{
  "lead_ids": [101, 102, 103, 104]
}
```

**Resposta:**
```json
{
  "success": true,
  "message": "Processados 4 leads",
  "data": {
    "total": 4,
    "sucesso": 3,
    "falha": 1,
    "detalhes": [
      {
        "lead_id": 101,
        "success": true,
        "conversa_id": 201
      },
      {
        "lead_id": 102,
        "success": false,
        "error": "Número de WhatsApp inválido"
      },
      ...
    ]
  }
}
```

## 📊 Validação de WhatsApp

### Regras Implementadas

```php
✅ Formato brasileiro: (XX) 9XXXX-XXXX
✅ Com ou sem código país: 55
✅ Mínimo 10 dígitos (DDD + número)
✅ Máximo 13 dígitos (55 + DDD + 9 + 8 dígitos)
✅ DDD válido (11-99)
✅ Celular (9 no início)

❌ Telefone fixo
❌ Formato internacional não-brasileiro
❌ Números incompletos
```

### Exemplos Válidos

```
(11) 98765-4321  ✅
11987654321      ✅
5511987654321    ✅
+55 11 98765-4321 ✅
```

### Exemplos Inválidos

```
(11) 3333-4444   ❌ (fixo)
98765-4321       ❌ (sem DDD)
123              ❌ (muito curto)
```

## 🔍 Detecção de Origem Chaves na Mão

### Métodos de Detecção

```php
private function isFromChavesNaMao(Lead $lead): bool
{
    // 1. Verifica campo chaves_na_mao_id
    if (!empty($lead->chaves_na_mao_id)) {
        return true;
    }

    // 2. Verifica campo origem
    if ($lead->origem === 'chavesnamao') {
        return true;
    }

    // 3. Verifica observações
    if (stripos($lead->observacoes, 'Chaves na') !== false) {
        return true;
    }

    return false;
}
```

## 🛡️ Prevenção de Duplicatas

### Estratégia Implementada

1. **Verificação de conversa existente**:
   ```php
   $conversaExistente = Conversa::where('lead_id', $lead->id)
       ->where('tenant_id', $lead->tenant_id)
       ->first();
   ```

2. **Opção de forçar reinício**:
   ```php
   iniciarAtendimento($lead, $forceStart = false);
   // Se forceStart=true, inicia mesmo com conversa existente
   ```

3. **Logs detalhados**:
   - Lead já possui atendimento ativo
   - Conversa reutilizada ou criada
   - Mensagem enviada ou erro

## 📝 Logs e Monitoramento

### Logs Gerados

```bash
# Atendimento iniciado automaticamente
[LeadObserver] Iniciando atendimento IA automático
[LeadAutomation] Iniciando atendimento para lead
[LeadAutomation] Conversa criada
[LeadAutomation] Atendimento iniciado com sucesso

# Erro - telefone inválido
[LeadAutomation] Telefone inválido ou não é WhatsApp

# Erro - já tem conversa
[LeadAutomation] Lead já possui conversa
```

### Verificar Logs

```bash
# Logs em tempo real
tail -f backend/storage/logs/lumen-$(date +%Y-%m-%d).log | grep LeadAutomation

# Filtrar por lead específico
grep "lead_id.*123" backend/storage/logs/lumen-*.log
```

## 🔧 Configuração

### Variáveis de Ambiente (.env)

```env
# Twilio WhatsApp (obrigatório)
TWILIO_ACCOUNT_SID=ACxxx
TWILIO_AUTH_TOKEN=xxx
TWILIO_WHATSAPP_FROM=whatsapp:+14155238886

# OpenAI (para mensagens personalizadas)
EXCLUSIVA_OPENAI_API_KEY=sk-xxx
EXCLUSIVA_OPENAI_MODEL=gpt-4o-mini
```

### Teste de Configuração

```bash
# Verificar se Twilio está configurado
php artisan tinker
>>> app(App\Services\TwilioService::class);

# Verificar se OpenAI está configurado
>>> app(App\Services\OpenAIService::class);
```

## 🧪 Testes

### Teste Manual - Interface

1. **Criar lead de teste**:
   ```sql
   INSERT INTO leads (tenant_id, nome, telefone, email, origem, created_at, updated_at)
   VALUES (1, 'João Teste', '11987654321', 'joao@teste.com', 'chavesnamao', NOW(), NOW());
   ```

2. **Verificar na interface**:
   - Acesse `leads.html`
   - Veja o lead criado
   - Clique no botão de robô
   - Confirme e aguarde

3. **Verificar WhatsApp**:
   - Abra WhatsApp do número 11987654321
   - Deve receber mensagem da IA

### Teste Automático - Observer

```php
// No tinker ou script de teste
$lead = new Lead([
    'tenant_id' => 1,
    'nome' => 'Maria Teste',
    'telefone' => '11987654321',
    'email' => 'maria@teste.com',
    'origem' => 'chavesnamao',
    'observacoes' => 'Lead de teste'
]);
$lead->save();

// Observer dispara automaticamente!
// Verificar logs:
tail -f storage/logs/lumen-*.log
```

### Teste API - cURL

```bash
# Teste com um lead
curl -X POST http://127.0.0.1:8000/api/admin/leads/123/iniciar-atendimento \
  -H "Authorization: Bearer {seu-token}" \
  -H "Content-Type: application/json"

# Teste em lote
curl -X POST http://127.0.0.1:8000/api/admin/leads/iniciar-atendimento-lote \
  -H "Authorization: Bearer {seu-token}" \
  -H "Content-Type: application/json" \
  -d '{"lead_ids": [101, 102, 103]}'
```

## ⚠️ Troubleshooting

### Mensagem não enviada

**Verificar**:
1. Número é WhatsApp válido?
2. Twilio configurado corretamente?
3. Créditos Twilio disponíveis?
4. Logs mostram erro?

**Solução**:
```bash
# Logs detalhados
grep "LeadAutomation" storage/logs/lumen-*.log

# Testar Twilio diretamente
php test_twilio_send.php
```

### IA não gera mensagem personalizada

**Verificar**:
1. OpenAI API Key configurada?
2. Créditos OpenAI disponíveis?
3. Modelo correto (gpt-4o-mini)?

**Solução**:
- Sistema usa **fallback automático**
- Mensagem padrão é enviada
- Logs mostram: "Erro ao gerar mensagem IA"

### Lead já tem conversa (não inicia)

**Comportamento esperado**: Previne duplicação

**Forçar reinício**:
```bash
POST /api/admin/leads/123/iniciar-atendimento
{
  "force": true
}
```

### Observer não dispara

**Verificar**:
1. Observer registrado em `bootstrap/app.php`?
   ```php
   Lead::observe(LeadObserver::class);
   ```

2. Lead tem origem correta?
   ```sql
   SELECT id, nome, origem, observacoes 
   FROM leads 
   WHERE id = 123;
   ```

## 📊 Relatórios

### Leads com Atendimento IA Iniciado

```sql
SELECT 
    l.id,
    l.nome,
    l.telefone,
    l.status,
    c.id as conversa_id,
    c.created_at as atendimento_iniciado
FROM leads l
JOIN conversas c ON c.lead_id = l.id
WHERE c.origem = 'automacao_chaves_na_mao'
ORDER BY c.created_at DESC;
```

### Taxa de Sucesso da Automação

```sql
SELECT 
    COUNT(*) as total_leads,
    SUM(CASE WHEN c.id IS NOT NULL THEN 1 ELSE 0 END) as com_atendimento,
    ROUND(
        SUM(CASE WHEN c.id IS NOT NULL THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 
        2
    ) as taxa_sucesso_percent
FROM leads l
LEFT JOIN conversas c ON c.lead_id = l.id AND c.origem = 'automacao_chaves_na_mao'
WHERE l.origem = 'chavesnamao'
    AND l.created_at >= CURDATE() - INTERVAL 7 DAY;
```

### Leads Sem Atendimento (para processar manualmente)

```sql
SELECT 
    l.id,
    l.nome,
    l.telefone,
    l.created_at
FROM leads l
LEFT JOIN conversas c ON c.lead_id = l.id
WHERE l.origem = 'chavesnamao'
    AND c.id IS NULL
    AND l.telefone IS NOT NULL
ORDER BY l.created_at DESC;
```

## 🎯 Benefícios

✅ **Velocidade**: Atendimento instantâneo (segundos após lead chegar)
✅ **Personalização**: Mensagem única para cada lead com contexto completo
✅ **Escalabilidade**: Processa centenas de leads simultaneamente
✅ **Rastreabilidade**: Logs completos de cada operação
✅ **Flexibilidade**: Automático + manual quando necessário
✅ **Qualidade**: IA OpenAI gera mensagens naturais e profissionais

---

**Status**: ✅ Funcional e testado  
**Criado em**: 29/12/2024  
**Stack**: Lumen 10 + OpenAI + Twilio WhatsApp
