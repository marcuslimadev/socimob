# Documentação Técnica - Módulo Socimob Atendimento 360

## Visão Geral

O módulo **Socimob Atendimento 360** é uma solução integrada que centraliza, registra e organiza atendimentos comerciais feitos por corretores imobiliários, especialmente aqueles iniciados ou continuados pelo WhatsApp Web. A solução compreende um backend robusto em Laravel 11, um frontend em React 19 e uma extensão Chrome Manifest V3.

## Arquitetura Geral

### Componentes Principais

1. **Backend (Laravel 11)**
   - Migrations para criação de tabelas
   - Models para representação de dados
   - Controllers para APIs REST
   - Services para lógica de negócio
   - Policies para autorização

2. **Frontend (React 19)**
   - Componentes para Caixa de Entrada
   - Telas de Detalhe de Conversa
   - Dashboard de Atendimento
   - Página de Configurações

3. **Extensão Chrome (Manifest V3)**
   - Background Service Worker
   - Content Script para detecção de conversas
   - Popup para login
   - Side Panel para funcionalidades principais

## Banco de Dados

### Tabelas Criadas

#### communication_channels
Armazena canais de comunicação disponíveis (WhatsApp Official, WhatsApp Web Assistant, Email, Chat, Manual).

```sql
CREATE TABLE communication_channels (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    name VARCHAR(255),
    type ENUM('whatsapp_official', 'whatsapp_web_assistant', 'email', 'chat', 'manual'),
    provider VARCHAR(255),
    status VARCHAR(255),
    settings_json JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### crm_conversations
Representa conversas comerciais vinculadas a leads, imóveis e corretores.

```sql
CREATE TABLE crm_conversations (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    lead_id BIGINT,
    property_id BIGINT,
    assigned_user_id BIGINT,
    channel_id BIGINT,
    source VARCHAR(255),
    external_identifier_hash VARCHAR(255) UNIQUE,
    contact_name VARCHAR(255),
    contact_phone VARCHAR(255),
    status VARCHAR(255),
    stage VARCHAR(255),
    interest_level INT,
    last_message_at TIMESTAMP,
    last_summary_at TIMESTAMP,
    created_by BIGINT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);
```

#### crm_messages
Armazena mensagens registradas no histórico comercial.

```sql
CREATE TABLE crm_messages (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    conversation_id BIGINT NOT NULL,
    user_id BIGINT,
    direction ENUM('inbound', 'outbound'),
    message_type ENUM('text', 'image', 'file', 'audio', 'system'),
    body TEXT,
    external_message_id VARCHAR(255),
    external_sent_at TIMESTAMP,
    metadata_json JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### crm_conversation_events
Timeline de eventos relacionados à conversa.

```sql
CREATE TABLE crm_conversation_events (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    conversation_id BIGINT NOT NULL,
    user_id BIGINT,
    event_type VARCHAR(255),
    title VARCHAR(255),
    description TEXT,
    payload_json JSON,
    source VARCHAR(255),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### crm_conversation_tasks
Tarefas vinculadas a conversas.

```sql
CREATE TABLE crm_conversation_tasks (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    conversation_id BIGINT NOT NULL,
    assigned_user_id BIGINT NOT NULL,
    created_by BIGINT NOT NULL,
    title VARCHAR(255),
    description TEXT,
    due_at TIMESTAMP,
    priority VARCHAR(255),
    status VARCHAR(255),
    completed_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### crm_conversation_visits
Visitas agendadas relacionadas a conversas.

```sql
CREATE TABLE crm_conversation_visits (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    conversation_id BIGINT NOT NULL,
    property_id BIGINT NOT NULL,
    scheduled_at TIMESTAMP,
    status VARCHAR(255),
    notes TEXT,
    participants_json JSON,
    created_by BIGINT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### crm_conversation_proposals
Propostas registradas relacionadas a conversas.

```sql
CREATE TABLE crm_conversation_proposals (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    conversation_id BIGINT NOT NULL,
    property_id BIGINT NOT NULL,
    amount DECIMAL(10, 2),
    proposal_type ENUM('purchase', 'rent', 'other'),
    status VARCHAR(255),
    notes TEXT,
    created_by BIGINT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### extension_consent_logs
Registro de consentimento do usuário para uso da extensão.

```sql
CREATE TABLE extension_consent_logs (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    consent_version VARCHAR(255),
    consent_text_hash VARCHAR(255),
    accepted_at TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP
);
```

#### extension_sessions
Sessões autorizadas da extensão Chrome.

```sql
CREATE TABLE extension_sessions (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    extension_id VARCHAR(255),
    browser VARCHAR(255),
    last_seen_at TIMESTAMP,
    status VARCHAR(255),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

## APIs REST

### Autenticação

Todas as APIs requerem autenticação via Bearer Token e validação de tenant.

```
Authorization: Bearer {token}
```

### Endpoints de Atendimento

#### GET /api/atendimento/conversations
Lista conversas do tenant com filtros opcionais.

**Parâmetros:**
- `corretor` (opcional): ID do usuário
- `lead` (opcional): ID do lead
- `imovel` (opcional): ID do imóvel
- `status` (opcional): Status da conversa
- `origem` (opcional): Origem da conversa
- `data` (opcional): Data da conversa
- `etapa` (opcional): Etapa do funil

**Resposta:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "lead_id": 10,
      "contact_name": "João Silva",
      "contact_phone": "11999999999",
      "status": "open",
      "stage": "initial_contact",
      "last_message_at": "2026-06-09T10:30:00Z",
      "assigned_user": { "id": 5, "name": "Maria" }
    }
  ],
  "message": "Conversas listadas com sucesso."
}
```

#### GET /api/atendimento/conversations/{id}
Obtém detalhes completos de uma conversa.

**Resposta:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "lead": { "id": 10, "name": "João Silva" },
    "property": { "id": 20, "address": "Rua A, 100" },
    "assigned_user": { "id": 5, "name": "Maria" },
    "messages": [],
    "events": [],
    "tasks": [],
    "visits": [],
    "proposals": []
  },
  "message": "Detalhes da conversa."
}
```

#### POST /api/atendimento/conversations/{id}/summary
Salva resumo comercial da conversa.

**Body:**
```json
{
  "summary": "Cliente interessado em imóvel de 3 quartos",
  "next_action": "Agendar visita para segunda-feira",
  "interest_level": 4,
  "status": "in_progress",
  "event_source": "chrome_extension"
}
```

#### POST /api/atendimento/conversations/{id}/messages
Registra mensagem comercial.

**Body:**
```json
{
  "direction": "inbound",
  "message_type": "text",
  "body": "Olá, tudo bem?",
  "sent_at": "2026-06-09T10:30:00Z",
  "metadata": {}
}
```

#### POST /api/atendimento/conversations/{id}/events
Cria evento na timeline.

**Body:**
```json
{
  "event_type": "status_changed",
  "title": "Status alterado para em progresso",
  "description": "Cliente confirmou interesse",
  "payload": {},
  "source": "web"
}
```

#### POST /api/atendimento/conversations/{id}/tasks
Cria tarefa vinculada à conversa.

**Body:**
```json
{
  "title": "Agendar visita",
  "description": "Agendar visita do imóvel",
  "due_at": "2026-06-12T14:00:00Z",
  "assigned_user_id": 5,
  "priority": "high"
}
```

#### POST /api/atendimento/conversations/{id}/visits
Registra visita.

**Body:**
```json
{
  "property_id": 20,
  "scheduled_at": "2026-06-12T14:00:00Z",
  "participants": ["João Silva", "Maria"],
  "notes": "Cliente muito interessado",
  "status": "scheduled"
}
```

#### POST /api/atendimento/conversations/{id}/proposals
Registra proposta.

**Body:**
```json
{
  "property_id": 20,
  "amount": 450000.00,
  "proposal_type": "purchase",
  "notes": "Proposta inicial",
  "status": "pending"
}
```

### Endpoints da Extensão

#### POST /api/extension/auth/check
Valida token do usuário autenticado.

**Resposta:**
```json
{
  "success": true,
  "data": {
    "user": { "id": 5, "name": "Maria", "email": "maria@example.com" },
    "tenant": { "id": 1, "name": "Imobiliária ABC" },
    "permissions": ["view_conversations", "create_tasks"],
    "module_config": {}
  },
  "message": "Token validado com sucesso."
}
```

#### POST /api/extension/consent
Registra consentimento do corretor.

**Body:**
```json
{
  "consent_version": "1.0",
  "consent_text_hash": "abc123def456"
}
```

#### GET /api/extension/leads/search?q=
Busca leads por nome, telefone, email ou código de imóvel.

**Resposta:**
```json
{
  "success": true,
  "data": [
    { "id": 10, "name": "João Silva", "phone": "11999999999" }
  ],
  "message": "Leads encontrados com sucesso."
}
```

#### POST /api/extension/leads
Cria novo lead rapidamente.

**Body:**
```json
{
  "name": "João Silva",
  "phone": "11999999999",
  "email": "joao@example.com",
  "origin": "whatsapp",
  "observations": "Cliente referenciado",
  "property_id": null
}
```

#### POST /api/extension/conversations/link
Vincula conversa do WhatsApp a um lead.

**Body:**
```json
{
  "lead_id": 10,
  "property_id": 20,
  "contact_name": "João Silva",
  "contact_phone": "11999999999",
  "whatsapp_chat_identifier": "55119999999@c.us",
  "source": "whatsapp_web",
  "assigned_user_id": 5
}
```

#### GET /api/extension/message-templates
Retorna templates de mensagem do tenant.

**Resposta:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Primeiro Contato",
      "content": "Olá! Tudo bem? Sou corretor imobiliário..."
    }
  ],
  "message": "Templates de mensagem listados com sucesso."
}
```

## Models Laravel

### CrmConversation
Representa uma conversa comercial.

```php
class CrmConversation extends Model {
    public function lead() { return $this->belongsTo(Lead::class); }
    public function property() { return $this->belongsTo(Property::class); }
    public function assignedUser() { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function messages() { return $this->hasMany(CrmMessage::class, 'conversation_id'); }
    public function events() { return $this->hasMany(CrmConversationEvent::class, 'conversation_id'); }
    public function tasks() { return $this->hasMany(CrmConversationTask::class, 'conversation_id'); }
    public function visits() { return $this->hasMany(CrmConversationVisit::class, 'conversation_id'); }
    public function proposals() { return $this->hasMany(CrmConversationProposal::class, 'conversation_id'); }
}
```

## Segurança

### Regras de Tenant

1. Toda query filtra automaticamente por `tenant_id`
2. Usuários comuns só veem conversas do seu tenant
3. Corretores só veem suas conversas (exceto gestores)
4. Gestores veem conversas da equipe
5. Superadmin acessa apenas com contexto explícito de tenant

### Policies de Autorização

- **CrmConversationPolicy**: Controla acesso a conversas
- **CrmMessagePolicy**: Controla acesso a mensagens
- **CrmTaskPolicy**: Controla acesso a tarefas

### Extensão Chrome

- Tokens armazenados em `chrome.storage.local` (não em localStorage)
- Expiração automática após 24h
- Validação de tenant em todas as requisições
- Sem captura automática de conversas
- Requer ação explícita do usuário

## Padrão de Resposta

Todas as APIs retornam JSON com estrutura consistente:

**Sucesso:**
```json
{
  "success": true,
  "data": {},
  "message": "Operação realizada com sucesso."
}
```

**Erro:**
```json
{
  "success": false,
  "message": "Mensagem clara para o usuário.",
  "errors": {}
}
```

## Fluxos de Uso

### Fluxo 1: Vincular Conversa do WhatsApp

1. Corretor abre WhatsApp Web
2. Extensão detecta conversa atual
3. Corretor busca lead no painel lateral
4. Seleciona lead e clica "Vincular"
5. Extensão envia POST em `/api/extension/conversations/link`
6. Conversa é criada e vinculada no Socimob

### Fluxo 2: Registrar Resumo Comercial

1. Corretor escreve resumo no painel lateral
2. Define próxima ação e nível de interesse
3. Clica "Salvar Resumo"
4. Extensão envia POST em `/api/conversations/{id}/summary`
5. Evento é criado na timeline
6. Gestor vê resumo atualizado no Socimob

### Fluxo 3: Criar Tarefa de Acompanhamento

1. Corretor clica em "Criar Tarefa"
2. Preenche título, descrição e data
3. Clica "Salvar"
4. Extensão envia POST em `/api/conversations/{id}/tasks`
5. Tarefa aparece na conversa e no Socimob

## Testes

### Testes Obrigatórios

1. **Isolamento de Tenant**: Usuário não acessa dados de outro tenant
2. **Autorização**: Corretor não acessa conversa de outro corretor
3. **Timeline**: Resumo cria evento na timeline
4. **Tarefas**: Tarefa criada aparece na conversa
5. **Propostas**: Proposta respeita imóvel do tenant
6. **Consentimento**: Consentimento é registrado
7. **Token Inválido**: Retorna 401
8. **Tenant Spoofing**: Tentativa falha

## Próximos Passos

1. Implementar testes automatizados (Feature Tests)
2. Integração com WhatsApp Business API
3. Notificações em tempo real via WebSocket
4. Relatórios e analytics
5. Integração com sistemas de CRM externos
