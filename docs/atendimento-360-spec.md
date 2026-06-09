# PROMPT PROFISSIONAL — Desenvolvimento do Módulo Socimob Atendimento 360 + Extensão Chrome

Você é um arquiteto sênior e desenvolvedor full stack especializado em Laravel 11, React 19, Vite, Tailwind, APIs REST, extensões Chrome Manifest V3, segurança multi-tenant e sistemas CRM.

Preciso que você desenvolva uma solução integrada ao projeto Socimob, um sistema imobiliário em Laravel 11 + React/Vite, com arquitetura multi-tenant, porém com implantação isolada por cliente em hospedagens separadas.

## Objetivo do projeto

Criar o módulo Socimob Atendimento 360, responsável por centralizar, registrar e organizar atendimentos comerciais feitos por corretores, especialmente atendimentos iniciados ou continuados pelo WhatsApp.

A solução deve permitir que a imobiliária não perca histórico comercial, negociação, propostas, visitas, interesses e próximos passos, mesmo quando o corretor realiza parte do atendimento pelo WhatsApp Web.

A solução deve ter duas camadas:

1.  **Módulo interno no Socimob**
    *   Central de atendimentos.
    *   Histórico por lead, cliente, imóvel e corretor.
    *   Registro de mensagens, eventos, resumos, tarefas, visitas e propostas.
    *   Integração futura com WhatsApp Business Platform/API oficial.
    *   APIs para comunicação com extensão Chrome.

2.  **Extensão Google Chrome Manifest V3**
    *   Assistente lateral do Socimob dentro do WhatsApp Web.
    *   Login seguro no Socimob.
    *   Vinculação manual de conversas do WhatsApp a leads/imóveis.
    *   Registro de resumo comercial.
    *   Criação de tarefas.
    *   Registro de proposta, visita, interesse e status da negociação.
    *   Geração/cópia de mensagens prontas.
    *   Envio de informações para o Socimob via API.

A extensão não deve ser criada como ferramenta de espionagem, captura silenciosa ou automação abusiva. Ela deve funcionar como assistente de produtividade e registro comercial, com ação explícita do corretor.

## Contexto técnico do projeto

O Socimob usa:

*   **Backend**: Laravel 11.
*   **PHP**: 8.2+ / 8.3.
*   **Frontend**: React 19.
*   **Bundler**: Vite.
*   **UI**: Tailwind 4, Radix UI, componentes React.
*   **Autenticação atual**: token simples/Bearer token.
*   **Multi-tenancy**: resolução por tenant/domínio.
*   **Banco**: relacional, usando migrations Laravel.
*   **Arquitetura atual**: rotas em `routes/web.php`, controllers em `app/Http/Controllers`, middlewares de tenant e autenticação já existentes.

A implementação deve respeitar o tenant atual da requisição e nunca permitir vazamento de dados entre imobiliárias.

## Regras de negócio

### Conceitos principais

Criar as seguintes entidades de domínio:

*   **Lead**
    *   Pessoa interessada em imóvel, atendimento, avaliação, compra, venda ou locação.
*   **Conversation**
    *   Representa uma conversa comercial vinculada a um lead, cliente, imóvel e corretor.
*   **Conversation Message**
    *   Mensagem registrada no histórico comercial. Pode ser manual, importada futuramente por API oficial ou registrada pela extensão mediante ação do usuário.
*   **Conversation Event**
    *   Evento de negociação, como:
        *   lead criado;
        *   conversa vinculada;
        *   resumo salvo;
        *   visita agendada;
        *   proposta registrada;
        *   status alterado;
        *   tarefa criada;
        *   atendimento transferido;
        *   conversa encerrada.
*   **Conversation Assignment**
    *   Define o corretor responsável pelo atendimento.
*   **Consent Log**
    *   Registro de consentimento do corretor para uso da extensão e registro de dados comerciais no Socimob.
*   **Chrome Extension Session**
    *   Sessão autorizada da extensão Chrome conectada ao usuário/corretor.

### Escopo funcional do backend

Criar APIs REST protegidas por autenticação e tenant.

#### Endpoints sugeridos

**Sessão da extensão**

*   `POST /api/extension/auth/check`
    *   Valida token do usuário autenticado.
    *   Retorna:
        *   usuário;
        *   tenant;
        *   permissões;
        *   configurações do módulo.

*   `POST /api/extension/consent`
    *   Registra consentimento do corretor para uso da extensão.
    *   Campos:
        *   `user_id`;
        *   `tenant_id`;
        *   `accepted_at`;
        *   `version`;
        *   `ip`;
        *   `user_agent`;
        *   `text_hash`.

**Leads**

*   `GET /api/extension/leads/search?q=`
    *   Busca leads por:
        *   nome;
        *   telefone;
        *   e-mail;
        *   código do imóvel;
        *   documento, se existir.
    *   Deve respeitar tenant.

*   `POST /api/extension/leads`
    *   Cria lead rapidamente a partir da extensão.
    *   Campos:
        *   `nome`;
        *   `telefone`;
        *   `email`;
        *   `origem`;
        *   `observações`;
        *   `property_id` opcional.

**Conversas**

*   `POST /api/extension/conversations/link`
    *   Vincula uma conversa do WhatsApp Web a um lead.
    *   Campos:
        *   `lead_id`;
        *   `property_id` opcional;
        *   `contact_name`;
        *   `contact_phone`;
        *   `whatsapp_chat_identifier`;
        *   `source`;
        *   `assigned_user_id`.
    *   Observação: `whatsapp_chat_identifier` não deve conter dados sensíveis desnecessários. Usar hash quando apropriado.

*   `GET /api/conversations`
    *   Lista conversas do tenant.
    *   Filtros:
        *   corretor;
        *   lead;
        *   imóvel;
        *   status;
        *   origem;
        *   data;
        *   etapa.

*   `GET /api/conversations/{id}`
    *   Detalha conversa.
    *   Inclui:
        *   lead;
        *   imóvel;
        *   corretor responsável;
        *   mensagens;
        *   eventos;
        *   tarefas;
        *   propostas;
        *   visitas.

*   `POST /api/conversations/{id}/summary`
    *   Salva resumo comercial da conversa.
    *   Campos:
        *   `summary`;
        *   `next_action`;
        *   `interest_level`;
        *   `status`;
        *   `event_source`: web, chrome_extension, api.

*   `POST /api/conversations/{id}/messages`
    *   Registra mensagem comercial.
    *   Campos:
        *   `direction`: inbound ou outbound;
        *   `message_type`: text, image, file, audio, system;
        *   `body`;
        *   `external_message_id` opcional;
        *   `sent_at`;
        *   `metadata` opcional.
    *   A mensagem só deve ser salva se houver ação explícita do corretor ou origem autorizada.

*   `POST /api/conversations/{id}/events`
    *   Cria evento de timeline.
    *   Tipos:
        *   note;
        *   status_changed;
        *   visit_scheduled;
        *   proposal_created;
        *   task_created;
        *   conversation_linked;
        *   conversation_closed.

**Tarefas**

*   `POST /api/conversations/{id}/tasks`
    *   Cria tarefa vinculada à conversa.
    *   Campos:
        *   `title`;
        *   `description`;
        *   `due_at`;
        *   `assigned_user_id`;
        *   `priority`.

**Visitas**

*   `POST /api/conversations/{id}/visits`
    *   Registra visita.
    *   Campos:
        *   `property_id`;
        *   `scheduled_at`;
        *   `participants`;
        *   `notes`;
        *   `status`.

**Propostas**

*   `POST /api/conversations/{id}/proposals`
    *   Registra proposta.
    *   Campos:
        *   `property_id`;
        *   `amount`;
        *   `proposal_type`: purchase, rent, other;
        *   `notes`;
        *   `status`.

**Templates de mensagens**

*   `GET /api/extension/message-templates`
    *   Retorna mensagens prontas por tenant.
    *   Exemplos:
        *   primeiro contato;
        *   envio de imóvel;
        *   agendamento de visita;
        *   cobrança de retorno;
        *   proposta recebida;
        *   documentação necessária.

### Modelagem de banco sugerida

Criar migrations para:

*   **communication_channels**
    *   Campos:
        *   `id`;
        *   `tenant_id`;
        *   `name`;
        *   `type`: whatsapp_official, whatsapp_web_assistant, email, chat, manual;
        *   `provider`;
        *   `status`;
        *   `settings_json`;
        *   `created_at`;
        *   `updated_at`.

*   **crm_conversations**
    *   Campos:
        *   `id`;
        *   `tenant_id`;
        *   `lead_id` nullable;
        *   `property_id` nullable;
        *   `assigned_user_id` nullable;
        *   `channel_id` nullable;
        *   `source`;
        *   `external_identifier_hash` nullable;
        *   `contact_name`;
        *   `contact_phone`;
        *   `status`;
        *   `stage`;
        *   `interest_level`;
        *   `last_message_at`;
        *   `last_summary_at`;
        *   `created_by`;
        *   `created_at`;
        *   `updated_at`;
        *   `deleted_at`.
    *   Índices:
        *   `tenant_id`;
        *   `lead_id`;
        *   `property_id`;
        *   `assigned_user_id`;
        *   `status`;
        *   `external_identifier_hash`.

*   **crm_messages**
    *   Campos:
        *   `id`;
        *   `tenant_id`;
        *   `conversation_id`;
        *   `user_id` nullable;
        *   `direction`;
        *   `message_type`;
        *   `body`;
        *   `external_message_id` nullable;
        *   `external_sent_at` nullable;
        *   `metadata_json` nullable;
        *   `created_at`;
        *   `updated_at`.
    *   Índices:
        *   `tenant_id`;
        *   `conversation_id`;
        *   `external_message_id`.

*   **crm_conversation_events**
    *   Campos:
        *   `id`;
        *   `tenant_id`;
        *   `conversation_id`;
        *   `user_id` nullable;
        *   `event_type`;
        *   `title`;
        *   `description` nullable;
        *   `payload_json` nullable;
        *   `source`;
        *   `created_at`.
    *   Índices:
        *   `tenant_id`;
        *   `conversation_id`;
        *   `event_type`.

*   **crm_conversation_tasks**
    *   Campos:
        *   `id`;
        *   `tenant_id`;
        *   `conversation_id`;
        *   `assigned_user_id`;
        *   `created_by`;
        *   `title`;
        *   `description` nullable;
        *   `due_at` nullable;
        *   `priority`;
        *   `status`;
        *   `completed_at` nullable;
        *   `created_at`;
        *   `updated_at`.

*   **crm_conversation_visits**
    *   Campos:
        *   `id`;
        *   `tenant_id`;
        *   `conversation_id`;
        *   `property_id`;
        *   `scheduled_at`;
        *   `status`;
        *   `notes` nullable;
        *   `participants_json` nullable;
        *   `created_by`;
        *   `created_at`;
        *   `updated_at`.

*   **crm_conversation_proposals**
    *   Campos:
        *   `id`;
        *   `tenant_id`;
        *   `conversation_id`;
        *   `property_id`;
        *   `amount` decimal;
        *   `proposal_type`;
        *   `status`;
        *   `notes` nullable;
        *   `created_by`;
        *   `created_at`;
        *   `updated_at`.

*   **extension_consent_logs**
    *   Campos:
        *   `id`;
        *   `tenant_id`;
        *   `user_id`;
        *   `consent_version`;
        *   `consent_text_hash`;
        *   `accepted_at`;
        *   `ip_address`;
        *   `user_agent`;
        *   `created_at`.

*   **extension_sessions**
    *   Campos:
        *   `id`;
        *   `tenant_id`;
        *   `user_id`;
        *   `extension_id` nullable;
        *   `browser`;
        *   `last_seen_at`;
        *   `status`;
        *   `created_at`;
        *   `updated_at`.

### Regras de segurança

Implementar com rigor:

1.  Toda query deve filtrar por `tenant_id`.
2.  Usuário comum só pode ver conversas do seu tenant.
3.  Corretor só pode ver suas conversas, salvo perfil de gestor/admin.
4.  Gestor pode ver conversas da equipe.
5.  Superadmin só acessa mediante contexto explícito de tenant.
6.  Nunca aceitar `tenant_id` vindo livremente do frontend.
7.  Usar tenant resolvido pelo middleware.
8.  Registrar logs de auditoria para ações sensíveis.
9.  Extensão deve usar token Bearer já emitido pelo Socimob ou fluxo próprio seguro.
10. Não salvar conversas pessoais automaticamente.
11. Não capturar todo WhatsApp Web silenciosamente.
12. Não automatizar disparos em massa.
13. Não expor tokens em `localStorage` da extensão sem cuidado.
14. Preferir `chrome.storage.local` com expiração e logout.
15. Sanitizar qualquer HTML/texto vindo do WhatsApp Web.

### Escopo funcional do frontend Socimob

Criar área no sistema chamada:

**Atendimento 360**

#### Telas:

1.  **Caixa de entrada**
    *   Lista conversas com:
        *   cliente;
        *   telefone;
        *   imóvel;
        *   corretor;
        *   status;
        *   última interação;
        *   origem;
        *   prioridade.
    *   Filtros:
        *   corretor;
        *   status;
        *   etapa;
        *   origem;
        *   data;
        *   imóvel.

2.  **Detalhe da conversa**
    *   Exibir:
        *   dados do lead;
        *   dados do imóvel;
        *   corretor responsável;
        *   timeline;
        *   mensagens registradas;
        *   notas;
        *   tarefas;
        *   visitas;
        *   propostas.
    *   Ações:
        *   alterar status;
        *   criar tarefa;
        *   agendar visita;
        *   registrar proposta;
        *   salvar resumo;
        *   transferir atendimento;
        *   encerrar conversa.

3.  **Dashboard de atendimento**
    *   Indicadores:
        *   leads em atendimento;
        *   tempo médio sem resposta;
        *   visitas agendadas;
        *   propostas abertas;
        *   conversas sem próximo passo;
        *   atendimentos por corretor;
        *   conversões por origem.

4.  **Configurações**
    *   Configurar:
        *   templates de mensagem;
        *   etapas do funil;
        *   permissões por perfil;
        *   texto de consentimento da extensão;
        *   canais de atendimento.

### Escopo funcional da extensão Chrome

Criar extensão Manifest V3 com:

#### Arquivos principais

*   `manifest.json`
*   `background/service-worker`
*   `content-script`
*   `side-panel` ou painel injetado
*   `popup`
*   `options`
*   cliente de API

#### Funcionalidades

**Login**

*   Permitir que o corretor conecte a extensão ao Socimob.
*   Fluxo preferencial:
    *   usuário informa URL da instalação Socimob;
    *   autentica com token ou login;
    *   extensão valida sessão via `/api/extension/auth/check`;
    *   armazena sessão localmente com expiração.

**Painel lateral no WhatsApp Web**

*   Quando o usuário abrir `https://web.whatsapp.com`, a extensão deve exibir um painel Socimob discreto.
*   O painel deve mostrar:
    *   status da conexão com Socimob;
    *   conversa atual detectada;
    *   busca de lead;
    *   botão “Vincular a lead”;
    *   botão “Criar novo lead”;
    *   campo “Resumo da conversa”;
    *   campo “Próxima ação”;
    *   botão “Criar tarefa”;
    *   botão “Agendar visita”;
    *   botão “Registrar proposta”;
    *   templates de mensagem com botão “Copiar”.

**Detecção de conversa**

*   A extensão pode tentar identificar de forma limitada:
    *   nome exibido do contato;
    *   telefone se visível;
    *   texto do cabeçalho da conversa.
*   Se não conseguir identificar telefone, permitir preenchimento manual.
*   Não depender de seletores frágeis sem camada de fallback.

**Vinculação manual**

*   A extensão deve exigir ação do corretor:
    *   buscar lead no Socimob;
    *   selecionar lead;
    *   confirmar vínculo;
    *   opcionalmente selecionar imóvel.

**Registro de resumo**

*   O corretor escreve ou revisa resumo.
*   A extensão envia para:
    *   `POST /api/conversations/{id}/summary`

**Templates**

*   A extensão busca templates em:
    *   `GET /api/extension/message-templates`
*   Ao clicar em um template:
    *   copiar texto para área de transferência;
    *   opcionalmente preencher campo de mensagem do WhatsApp Web apenas se isso for seguro e estável;
    *   não enviar automaticamente.

### Restrições importantes da extensão

Não implementar:

*   captura invisível de todas as conversas;
*   leitura automática em massa do histórico;
*   envio automático sem ação do usuário;
*   disparo em lote;
*   scraping agressivo;
*   bypass de limitações do WhatsApp;
*   interceptação de conversas pessoais sem vínculo comercial;
*   coleta de contatos não relacionados a leads.

A extensão deve ser apresentada como assistente de produtividade e registro, não como robô ou monitoramento oculto.

### Integração futura com WhatsApp Business API

Preparar abstração para canais oficiais.

Criar camada de serviço:

*   `CommunicationChannelService`
*   `ConversationService`
*   `MessageTemplateService`
*   `WhatsAppOfficialProviderInterface`
*   `ManualWhatsAppAssistantProvider`

A ideia é permitir futuramente:

*   Twilio;
*   Meta Cloud API;
*   Z-API ou outro provedor;
*   canal manual/extensão.

### Arquitetura esperada no Laravel

Criar:

**Models**

*   `CommunicationChannel`
*   `CrmConversation`
*   `CrmMessage`
*   `CrmConversationEvent`
*   `CrmConversationTask`
*   `CrmConversationVisit`
*   `CrmConversationProposal`
*   `ExtensionConsentLog`
*   `ExtensionSession`

**Controllers**

*   `App\Http\Controllers\Api\Atendimento\ConversationController`
*   `App\Http\Controllers\Api\Atendimento\ConversationMessageController`
*   `App\Http\Controllers\Api\Atendimento\ConversationEventController`
*   `App\Http\Controllers\Api\Atendimento\ConversationTaskController`
*   `App\Http\Controllers\Api\Atendimento\ConversationVisitController`
*   `App\Http\Controllers\Api\Atendimento\ConversationProposalController`
*   `App\Http\Controllers\Api\Extension\ExtensionAuthController`
*   `App\Http\Controllers\Api\Extension\ExtensionLeadController`
*   `App\Http\Controllers\Api\Extension\ExtensionConversationController`
*   `App\Http\Controllers\Api\Extension\ExtensionTemplateController`
*   `App\Http\Controllers\Api\Extension\ExtensionConsentController`

**Services**

*   `ConversationService`
*   `ConversationTimelineService`
*   `LeadMatchingService`
*   `MessageTemplateService`
*   `ExtensionSessionService`
*   `ConsentService`

**Requests**

Criar Form Requests para validação:

*   `StoreConversationRequest`
*   `StoreConversationSummaryRequest`
*   `StoreConversationMessageRequest`
*   `StoreConversationTaskRequest`
*   `StoreConversationVisitRequest`
*   `StoreConversationProposalRequest`
*   `ExtensionLinkConversationRequest`
*   `ExtensionCreateLeadRequest`

**Policies**

Criar policies:

*   `CrmConversationPolicy`
*   `CrmMessagePolicy`
*   `CrmTaskPolicy`

### Padrão de resposta das APIs

Usar JSON consistente:

```json
{
  "success": true,
  "data": {},
  "message": "Operação realizada com sucesso."
}
```

Em erro:

```json
{
  "success": false,
  "message": "Mensagem clara para o usuário.",
  "errors": {}
}
```

### Testes obrigatórios

Criar testes automatizados para:

1.  Corretor não acessa conversa de outro tenant.
2.  Corretor não acessa conversa de outro corretor se não for gestor.
3.  Gestor acessa conversas da equipe.
4.  Extensão só cria vínculo com lead do mesmo tenant.
5.  Resumo cria evento na timeline.
6.  Tarefa criada aparece na conversa.
7.  Proposta criada respeita imóvel do tenant.
8.  Consentimento é registrado.
9.  Token inválido da extensão retorna 401.
10. Tentativa de tenant spoofing falha.

### UX esperada

A experiência deve ser simples.

**No Socimob**

O gestor deve conseguir responder rapidamente:

*   Quais leads estão sem retorno?
*   Qual corretor está atendendo cada cliente?
*   Qual foi o último resumo da negociação?
*   Quais visitas estão marcadas?
*   Quais propostas estão em aberto?
*   Quais conversas não têm próximo passo?

**Na extensão**

O corretor deve conseguir em poucos cliques:

1.  Vincular conversa a um lead.
2.  Salvar resumo.
3.  Criar próxima ação.

Não transformar a extensão em um sistema pesado.

### Entregáveis

Entregar:

1.  Migrations.
2.  Models.
3.  Controllers.
4.  Services.
5.  Requests.
6.  Policies.
7.  Rotas API.
8.  Telas React do Atendimento 360.
9.  Extensão Chrome Manifest V3.
10. Testes backend.
11. Documentação técnica.
12. Checklist de instalação da extensão.
13. Guia de uso para corretores.
14. Guia de configuração para administradores.

### Critérios de aceite

A entrega será considerada concluída quando:

1.  Um corretor conseguir abrir o WhatsApp Web, usar a extensão, buscar um lead no Socimob e vincular a conversa.
2.  O corretor conseguir salvar um resumo comercial da conversa.
3.  O resumo aparecer na timeline do lead/conversa dentro do Socimob.
4.  O corretor conseguir criar uma tarefa de próximo contato.
5.  O gestor conseguir ver a conversa no painel Atendimento 360.
6.  Nenhum dado de outro tenant ficar acessível.
7.  A extensão não enviar mensagens automaticamente.
8.  A extensão não capturar histórico completo sem ação do usuário.
9.  As APIs estiverem protegidas por autenticação e tenant.
10. Os testes principais de isolamento e permissão estiverem passando.

### Orientação de implementação

Comece pelo backend e pela modelagem.

Ordem recomendada:

1.  Migrations, models e policies.
2.  APIs de conversations, summary, tasks e leads.
3.  Tela Atendimento 360 no React.
4.  Extensão Chrome com login e painel lateral.
5.  Vinculação de conversa e registro de resumo.
6.  Templates de mensagem.
7.  Testes e documentação.

Priorizar um MVP funcional antes de tentar automações avançadas.

### Observação estratégica

O objetivo não é substituir o WhatsApp nem criar um robô. O objetivo é impedir que a imobiliária perca o histórico comercial.

A frase que deve guiar o produto é:

> O WhatsApp continua com o corretor. A inteligência comercial fica com a imobiliária.
