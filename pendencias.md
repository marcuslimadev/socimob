# Pendências do Projeto SociMob

> Documento gerado em: 02/02/2026
> Atualizado em: 03/02/2026 (Limpeza para produção)

---

## Status Geral

### Implementado Nesta Sessão

| Item | Status | Arquivos Modificados |
|------|--------|---------------------|
| Envio de Email (IntentionService) | Implementado | `IntentionService.php`, `PropertyMatchMail.php` |
| Envio de WhatsApp (IntentionService) | Implementado | `IntentionService.php` |
| Envio de SMS (IntentionService) | Implementado | `IntentionService.php`, `TwilioService.php` |
| Verificação de Webhook | Habilitado | `SubscriptionController.php` |
| Verificação SSL | Habilitado (env-based) | `ImportacaoController.php` |
| APP_DEBUG | Desabilitado | `.env` |
| Geração de Thumbnails | Implementado | `PerformanceOptimizationService.php` |
| Sistema de Filas WhatsApp | Implementado | `WhatsAppService.php`, `SendWhatsAppMessageJob.php` |
| Notificações ao Corretor | Implementado | `PortalController.php`, `NewClientInterestMail.php` |
| confirm() → AlertDialog | Implementado | `Leads.tsx`, `Vistorias.tsx`, `Pessoas.tsx` |
| Badges Dinâmicos | Implementado | `Sidebar.tsx` |
| Rotas de Notificações | Adicionadas | `routes/web.php` |
| .gitignore atualizado | Concluído | `.gitignore` |

---

## 1. Integrações Implementadas

### 1.1 Sistema de Notificações

| Funcionalidade | Status | Observações |
|----------------|--------|-------------|
| Envio de Email | **Implementado** | Usa `PropertyMatchMail` com template HTML |
| Envio de WhatsApp | **Implementado** | Usa `TwilioService::sendMessage()` |
| Envio de SMS | **Implementado** | Novo método `TwilioService::sendSMS()` |
| Push Notifications | Pendente | Requer integração com FCM |

### 1.2 Sistema de Filas (Queue)

**Status:** Implementado

- Job criado: `app/Jobs/SendWhatsAppMessageJob.php`
- Método `queueMessage()` adicionado ao `WhatsAppService`
- Configuração: Mudar `QUEUE_CONNECTION=database` no `.env` para habilitar

### 1.3 Notificações ao Corretor

**Status:** Implementado

- Email enviado automaticamente quando cliente demonstra interesse
- WhatsApp opcional (habilitar via `notify_broker_whatsapp` nas settings do tenant)

---

## 2. Segurança Corrigida

### 2.1 Verificação de Assinatura de Webhook

**Status:** Habilitado

A verificação de assinatura foi habilitada no `SubscriptionController.php`. Webhooks com assinatura inválida retornam 401.

### 2.2 Verificação SSL

**Status:** Configurável via ambiente

Agora usa `env('VERIFY_SSL_CERTIFICATES', true)` - habilitado por padrão, pode ser desabilitado em desenvolvimento.

### 2.3 Debug Mode

**Status:** Desabilitado em produção

`APP_DEBUG=false` no `.env`

---

## 3. Frontend Atualizado

### 3.1 Dialogs de Confirmação

**Status:** Implementado

Todos os `confirm()` nativos foram substituídos por `AlertDialog` do shadcn/ui:
- `Leads.tsx`
- `Vistorias.tsx`
- `Pessoas.tsx`

### 3.2 Badges Dinâmicos

**Status:** Implementado

O `Sidebar.tsx` agora busca contadores via API:
- Notificações: `/notifications/unread-count`
- Leads novos: `/leads/stats`
- Mensagens: `/admin/conversas/fila/estatisticas`

Atualização automática a cada 30 segundos.

---

## 4. Pendências Restantes

### Alta Prioridade
- [ ] Implementar push notifications (FCM)
- [ ] Integração bidirecional WhatsApp (sync com conversas)

### Média Prioridade
- [ ] Implementar suporte a vídeos no portal
- [ ] Remover console.error do código de produção
- [ ] Substituir dados de teste em seeders

### Baixa Prioridade
- [ ] Remover arquivos de teste/debug da raiz (já ignorados no .gitignore)

---

## 5. Arquivos Criados

```
app/Mail/PropertyMatchMail.php
app/Mail/NewClientInterestMail.php
app/Jobs/SendWhatsAppMessageJob.php
resources/views/emails/property_match.blade.php
resources/views/emails/notification.blade.php
resources/views/emails/new_client_interest.blade.php
```

---

## 6. Configurações Recomendadas para Produção

```env
# .env
APP_DEBUG=false
QUEUE_CONNECTION=database
VERIFY_SSL_CERTIFICATES=true
```

Para habilitar notificações WhatsApp ao corretor, adicionar no settings do tenant:
```json
{
  "notify_broker_whatsapp": true
}
```

---

## 7. Checklist de Produção

- [x] Desabilitar APP_DEBUG
- [x] Habilitar verificação SSL
- [x] Habilitar verificação de assinatura de webhooks
- [x] Implementar notificações por email
- [x] Implementar notificações por WhatsApp
- [x] Implementar sistema de filas
- [x] Substituir confirm() por AlertDialog
- [x] Implementar badges dinâmicos
- [x] Adicionar arquivos de debug ao .gitignore
- [ ] Configurar QUEUE_CONNECTION=database
- [ ] Executar queue worker em produção
- [ ] Remover console.error (opcional)

---

*Documento atualizado após implementação das pendências.*
