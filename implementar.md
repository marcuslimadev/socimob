# 📋 Documento de Funcionalidades Pendentes e Mocadas - SOCIMOB

## 🎯 Status Geral
- **Último atualizado**: 26 de Janeiro de 2026
- **Versão**: 2.0 (React 19 + Lumen 10)
- **Ambiente**: Desenvolvimento Local + Produção (lojadaesquina.store)

---

## 🔴 PRIORIDADE ALTA - Funcionalidades Críticas Pendentes

### 1. Portal do Cliente - Configuração Dinâmica
**Arquivo**: `app/Http/Controllers/PortalController.php:19`
**Status**: ❌ MOCADA
**Descrição**: Endpoint `/api/portal/config` retorna hardcoded. Deveria buscar configurações do tenant do banco.
**Impacto**: Logo, cores, contatos não refletem os dados do tenant real
**Ação**: 
- [ ] Implementar busca de `Tenant` pelo domínio da request
- [ ] Retornar cores, logo, contato do tenant real
- [ ] Validar disponibilidade de assets

```php
// ANTES (mocado)
$tenant = [
    'name' => 'Exclusiva Lar Imóveis',  // hardcoded
    ...
];

// DEPOIS (real)
$tenant = app('tenant');
return $tenant->getPublicConfig();
```

### 2. Lead Service - Busca e Normalização Completa
**Arquivo**: `app/Services/LeadService.php:1-140`
**Status**: ⚠️ PARCIALMENTE IMPLEMENTADO
**Descrição**: Service tem funções de normalização mas faltam:
- [ ] Busca avançada com múltiplos critérios
- [ ] Deduplicação inteligente
- [ ] Validação de dados completa
- [ ] Scoring de lead (qualificação automática)

### 3. Lead Conversation Service - Fluxo Incompleto
**Arquivo**: `app/Services/LeadConversationService.php:1-162`
**Status**: ⚠️ PARCIALMENTE IMPLEMENTADO
**Funções Pendentes**:
- [ ] `ensureConversaForLead()` - Retorna `null` em casos de erro (deveria log/retry)
- [ ] `extractMensagemFromObservacoes()` - Extrair mensagem de observações do lead
- [ ] Histórico de mensagens completo
- [ ] Sincronização com WhatsApp real

### 4. Portal - Funcionalidade de Interesse do Cliente
**Arquivo**: `app/Http/Controllers/PortalController.php:135-270`
**Status**: ❌ ESTRUTURA BÁSICA SÓ
**Descrição**: Registrar interesse em imóvel não está completo
**Ações Pendentes**:
- [ ] Validação robusta de dados
- [ ] Notificação ao corretor (email/WhatsApp)
- [ ] Rastreamento de histórico de interesses
- [ ] Score de probabilidade de conversão
- [ ] Enviar sugestões baseadas em interesse

---

## 🟡 PRIORIDADE MÉDIA - Funcionalidades Mocadas/Incompletas

### 5. Portal - Obtenção de Imóvel Específico
**Arquivo**: `app/Http/Controllers/PortalController.php:91+`
**Status**: ❌ MOCADA
```php
public function imovel(Request $request, $id) {
    // Retorna dados de exemplo, não busca do banco real
    return response()->json([
        'success' => true,
        'data' => $mockData  // ← Dados fake
    ]);
}
```
**Ação**: Implementar busca real do banco com imagens, descrição, vídeos

### 6. WhatsApp Service - Processamento de Mensagens
**Arquivo**: `app/Services/WhatsAppService.php`
**Status**: ⚠️ MÚLTIPLOS RETORNOS NULL
**Linhas**: 449, 454, e outros
**Descrição**: Muitos caminhos retornam `null` em vez de tratar erros
**Ação**:
- [ ] Adicionar logging estruturado
- [ ] Implementar retry automático
- [ ] Notificar admin de falhas
- [ ] Queue para processamento assíncrono

### 7. Financial Integration - Pagar.me / Stripe
**Arquivo**: `app/Services/MercadoPagoService.php`, `app/Services/FinancialIntegrationService.php`
**Status**: ⚠️ STUB IMPLEMENTATION
**Problema**: Funções retornam `false` em vários cenários
**Pendências**:
- [ ] Fluxo de pagamento completo
- [ ] Webhook de confirmação
- [ ] Retry automático
- [ ] Relatório de transações

### 8. NFeIO Service - Emissão de Notas Fiscais
**Arquivo**: `app/Services/NFeIOService.php:195-207`
**Status**: ❌ NÃO IMPLEMENTADA
**Descrição**: Retorna `false` sempre
**Ação**: 
- [ ] Integração com API NFeIO
- [ ] Formatação correta de dados fiscais
- [ ] Armazenamento de comprovante
- [ ] Webhook de processamento

### 9. PropertySync Service - Sincronização de Imóveis
**Arquivo**: `app/Services/PropertySyncService.php`
**Status**: ⚠️ PARCIALMENTE IMPLEMENTADO
**Problemas**:
- [ ] Método `sync()` pode retornar `null` (linha 279, 286, 360)
- [ ] Processamento de imagens incompleto
- [ ] Validação de dados mínimos
- [ ] Deduplica ção de imóveis (linha 523 retorna `false`)

### 10. Stage Detection - Qualificação de Lead
**Arquivo**: `app/Services/StageDetectionService.php:95-105`
**Status**: ❌ RETORNA FALSE SEMPRE
**Descrição**: Deveria detectar estágio do lead (novo, qualificado, pronto)
**Ação**:
- [ ] Implementar lógica de análise de comportamento
- [ ] Scoring automático
- [ ] Integração com histórico de conversas
- [ ] Regras customizáveis por tenant

---

## 🟠 PRIORIDADE MÉDIA-BAIXA - UI/Frontend Pendente

### 11. React Frontend - Componentes Principais
**Localização**: `client/src/`
**Status**: ⚠️ EM DESENVOLVIMENTO
**Componentes Faltando**:
- [ ] Dashboard completo com gráficos (usando Recharts/Victory)
- [ ] Tabela de Leads com filtros avançados
- [ ] Gerenciador de Propriedades (CRUD)
- [ ] Chat em tempo real (WebSocket)
- [ ] Agendamento de visitas
- [ ] Relatórios e exportação (PDF/Excel)
- [ ] Notificações em tempo real (toast/bell icon)

### 12. Portal Cliente React - Catálogo
**Status**: ❌ NÃO INICIADO
**Descrição**: Frontend cliente não tem UI para:
- [ ] Listagem de imóveis com filtros
- [ ] Detalhes e galeria de fotos
- [ ] Agendamento de visita
- [ ] Chat com corretor
- [ ] Favoritos/Wishlist
- [ ] Histórico de buscas

### 13. Admin - Configurações Avançadas
**Arquivo**: `public/app/configuracoes.html`
**Status**: ⚠️ PARCIALMENTE IMPLEMENTADO
**Faltam**:
- [ ] Sincronização de conversas Chaves na Mão (linha 233)
- [ ] Editor visual de prompts de IA
- [ ] Configuração de webhooks customizados
- [ ] Integração com múltiplos canais (WhatsApp Business, Telegram, etc)
- [ ] Backup automático
- [ ] Auditoria de acesso

---

## 🔵 PRIORIDADE BAIXA - Melhorias e Otimizações

### 14. Importação de Imóveis - Múltiplas Fontes
**Arquivo**: `app/Http/Controllers/ImportacaoImoveisController.php`
**Status**: ⚠️ APENAS CHAVES NA MÃO
**Ação**: 
- [ ] Suporte a Vivareal
- [ ] Suporte a Imobiliário.com
- [ ] Suporte a Imobiliária própria (SFTP/API)
- [ ] Importação agendada automática

### 15. Performance - Cache e Otimizações
**Status**: ❌ NÃO IMPLEMENTADO
**Ações**:
- [ ] Cache de imóveis por tenant
- [ ] Índices no banco (tenant_id, status)
- [ ] Paginação otimizada
- [ ] Lazy loading de imagens
- [ ] Compressão de respostas JSON

### 16. Analytics e Relatórios
**Status**: ❌ SOMENTE MOCK
**Funcionalidades**:
- [ ] Dashboard com KPIs (leads, conversão, receita)
- [ ] Relatório de performance dos corretores
- [ ] Análise de comportamento de clientes
- [ ] Previsão de vendas (ML/IA)
- [ ] Exportação de relatórios (PDF, Excel)

### 17. Sistema de Notificações
**Status**: ⚠️ BÁSICO
**Melhorias**:
- [ ] Notificações em tempo real via WebSocket
- [ ] Email customizado por tenant
- [ ] SMS via Twilio
- [ ] Push notification
- [ ] Notificação no app (bell icon)

### 18. Rate Limiting e Segurança
**Status**: ⚠️ PARCIAL
**Ações**:
- [ ] Rate limiting por IP
- [ ] Rate limiting por user
- [ ] Verificação de CSRF
- [ ] Validação de entrada robusta
- [ ] SQL Injection prevention (audit)

### 19. Testes Automatizados
**Status**: ⚠️ APENAS ALGUNS
**Ações**:
- [ ] Testes unitários dos Services
- [ ] Testes de integração dos Controllers
- [ ] Testes de API (Postman/Newman)
- [ ] Testes e2e do Frontend (Cypress/Playwright)
- [ ] Cobertura mínima 80%

### 20. Documentação da API
**Status**: ⚠️ INCOMPLETA
**Ações**:
- [ ] Swagger/OpenAPI completo
- [ ] Exemplos de request/response
- [ ] Guia de autenticação
- [ ] Códigos de erro documentados
- [ ] Webhooks documentados

---

## 📊 Sumário de Status

| Categoria | Total | ✅ Completo | ⚠️ Parcial | ❌ Pendente |
|-----------|-------|------------|-----------|-----------|
| Backend Services | 10 | 3 | 4 | 3 |
| Controllers | 8 | 2 | 3 | 3 |
| Frontend React | 12 | 0 | 2 | 10 |
| Integrações Externas | 5 | 2 | 1 | 2 |
| DevOps/Testing | 5 | 1 | 2 | 2 |
| **TOTAL** | **40** | **8** | **12** | **20** |

**Conclusão**: ~20% completo, 30% em desenvolvimento, 50% pendente

---

## 🚀 Sugestão de Ordem de Implementação

### Sprint 1 (Semana 1-2)
1. ✅ Portal - Config dinâmica (1 - CRÍTICA)
2. ✅ Lead Service - Funcionalidades completas (2)
3. ✅ WhatsApp - Melhor tratamento de erros (6)

### Sprint 2 (Semana 3-4)
4. ✅ React Dashboard principal (11)
5. ✅ Tabela de Leads com filtros (11)
6. ✅ Property CRUD (11)

### Sprint 3 (Semana 5-6)
7. ✅ Chat real-time (11)
8. ✅ Portal Cliente - Catálogo (12)
9. ✅ Analytics básico (16)

### Sprint 4+ (Futuro)
- Remaining improvements em priority order

---

## 📝 Notas Importantes

1. **Testes**: Execute `php check_db.php` e `php test_atendimento_ia.php` antes de implementar
2. **Git**: Sempre criar branch feature antes de começar: `git checkout -b feature/nome`
3. **Commit**: Use padrão: `feat: descrição`, `fix: descrição`, `docs: descrição`
4. **Deploy**: Após testes, fazer push e aguardar CI/CD (`.github/workflows/`)
5. **Ambiente**: Variáveis em `.env`, nunca hardcode secrets

---

## 🔗 Referências Úteis

- **Documentação de Arquitetura**: `.github/copilot-instructions.md`
- **Testes Rápidos**: Root directory (`check_*.php`, `test_*.php`, `create_*.php`)
- **Logs**: `storage/logs/lumen-{date}.log`
- **Postman Collection**: `SOCIMOB_API.postman_collection.json`

---

**Última atualização**: 26/01/2026 por Sistema de Análise
