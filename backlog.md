# Backlog de Aprimoramentos e Desenvolvimento

**Fonte:** `novos.md` (Documento de Desenvolvimento - Sistema de Gestão Imobiliária)

Este backlog consolida tudo que precisa ser **aprimorado ou desenvolvido** no sistema atual, comparando os requisitos do `novos.md` com o que já existe no repositório.

---

## 1. Visão Geral do Gap

- O sistema atual cobre bem **leads, conversas, portal de imóveis e sincronização de imóveis**.
- Faltam os módulos **Vistoria** e **Assinatura Eletrônica** completos.
- O módulo **Pessoas** existe apenas indiretamente via leads/clientes (users), e precisa ser formalizado.
- O módulo **Imóveis** existe, mas falta **cadastro completo/edição/exportação** e ajustes na UI.
- Requisitos não funcionais precisam ser endereçados junto com os novos módulos.

---

## 2. Backlog por Módulo

### 2.1. Acesso e Autenticação

**Status:** Parcial (funciona, precisa refinamento de UX e integração com novos módulos)

- **RF-001 Login** — OK
- **RF-002 Recuperação de Senha** — OK
- **RF-003 Dashboard/Acessos Rápidos** — OK
  - Dashboard atualizado com cards/atalhos dos novos módulos (Vistoria, Assinatura, Pessoas, Imóveis)
- **RF-004 Notificações** — OK
  - Integração preparada para eventos de Vistoria e Assinatura (serviço e UI)
- **RF-005 Gestão de Perfil** — PENDENTE
  - Ajustar campos e permissões conforme o módulo Pessoas

---

### 2.2. Vistoria (Inspeção)

**Status:** Faltando (apenas agenda de visitas existe)

#### Gestão de Vistorias
- **RF-100 Listagem de Vistorias**
  - Criar tabela `vistorias`
  - Criar API `GET /api/vistorias` (com paginação)
  - Criar UI de listagem

- **RF-101 Filtros de Vistoria**
  - Filtros: Código, Status, Cliente, Imóvel, Tipo, Vistoriadores, Pessoas, Metragem, Mobiliado
  - Implementar filtros na API e UI

- **RF-102 Visualização de Detalhes**
  - API `GET /api/vistorias/{id}`
  - UI detalhada com histórico, fotos, pessoas relacionadas

- **RF-103 Exportação de Dados**
  - API export CSV/XLS
  - Botão de exportação na UI

#### Solicitações de Vistoria
- **RF-110 Criação de Solicitação**
  - Tabela `vistoria_solicitacoes`
  - API `POST /api/vistorias/solicitacoes`
  - UI formulário multi-abas

- **RF-111 Visualização em Kanban**
  - Colunas: Solicitada, Designada, Em Andamento, Concluída, Cancelada
  - UI Kanban + endpoint por status

- **RF-112 Visualização em Calendário**
  - UI calendário + filtro por período
  - API por intervalo de datas

- **RF-113 Visualização em Lista**
  - UI lista detalhada com colunas definidas

- **RF-114 Formulário de Solicitação (Abas)**
  - **RF-114.1 Solicitação:** Cliente, Tipo (obrigatórios)
  - **RF-114.2 Observações:** Texto livre
  - **RF-114.3 Pessoas:** Vinculação a pessoas adicionais
  - **RF-114.4 Histórico:** Log/audit trail

#### Contestações
- **RF-120 Listagem de Contestações**
  - Tabela `vistoria_contestacoes`
  - API `GET /api/contestoes`
  - UI listagem

- **RF-121 Filtros + Kanban de Contestações**
  - Filtros por período
  - UI Kanban (Apontadas, Em Análise, Finalizadas)

---

### 2.3. Pessoas

**Status:** Parcial (hoje só existe leads/clientes)

- **RF-200 Listagem de Pessoas**
  - Criar tabela `pessoas`
  - Migrar/espelhar dados de leads/users quando aplicável
  - UI listagem dedicada

- **RF-201 Pesquisa de Pessoas**
  - Busca global por nome/documento/email/telefone

- **RF-202 Criação de Pessoa (formulário multi-abas)**
  - **RF-202.1 Principal:** Nome, País, Telefone/Celular, E-mail, Tipo (Física/Jurídica)
  - **RF-202.2 Pessoa Física:** CPF, RG, Órgão Expedidor, Data de Expedição, CNH, Data de Nascimento
  - **RF-202.3 Endereço:** CEP, Estado, Cidade, Bairro, Endereço, Número, Complemento
  - **RF-202.4 Contatos:** múltiplos contatos (Tipo, Contato, Descrição)

---

### 2.4. Imóveis

**Status:** Parcial (listagem e detalhes existem; criação/edição faltando)

- **RF-300 Listagem de Imóveis** — OK
- **RF-301 Pesquisa de Imóveis** — OK
  - Filtros avançados e paginação consistente (front-end)

- **RF-302 Criação de Imóvel** — PENDENTE
  - API `POST /api/imoveis`
  - UI formulário completo

- **RF-302.1 Identificação**
- **RF-302.2 Tipo do Imóvel**
- **RF-302.3 Edifício / Condomínio**
- **RF-302.4 Endereço**
- **RF-302.5 Detalhes (Metragem)**

- **Ajustes adicionais**
  - Exportação de imóveis
  - Integração com CEP

---

### 2.5. Assinatura Eletrônica

**Status:** Faltando (hoje só existe assinatura de plano/financeiro)

- **RF-400 Listagem de Documentos**
  - Tabela `documentos_assinatura`
  - API `GET /api/assinaturas/documentos`
  - UI listagem

- **RF-401 Pesquisa de Documentos**
  - Busca por texto/status

- **RF-402 Criação de Documento**
  - API `POST /api/assinaturas/documentos`
  - Upload de PDF/Documentos + configuração de assinantes

- **RF-403 Status de Assinatura**
  - Integração com provedor (Clicksign/Docusign ou serviço próprio)
  - Webhooks e atualização de status
  - UI com badges (Assinado, Pendente, etc.)

---

## 3. Requisitos Não Funcionais (RNF)

- **RNF-001 Usabilidade/Responsividade**
  - Garantir design responsivo em todos os novos módulos

- **RNF-002 Performance**
  - Paginação, índices e cache em listagens grandes

- **RNF-003 Segurança**
  - SSL/TLS (infra)
  - Revisão de permissões por módulo

- **RNF-004 Permissões**
  - Definir matriz de acesso: admin, corretor, vistoriador, cliente

- **RNF-005 Integração E-mail**
  - Notificações de Vistoria/Assinatura via e-mail

- **RNF-006 Integração CEP**
  - Auto-preenchimento de endereço em Pessoas e Imóveis

---

## 4. Dependências e Dados

- Criar novas tabelas para Vistorias, Pessoas, Contestações, Documentos de Assinatura
- Definir relacionamento entre Pessoas, Imóveis e Vistorias
- Integrar com serviço externo de Assinatura Eletrônica

---

## 5. Sugestão de Fases (Opcional)

- **Fase 1:** Estrutura base de Pessoas + Imóveis (cadastro, edição, filtros)
- **Fase 2:** Módulo Vistoria completo (solicitações, kanban, calendário, contestações)
- **Fase 3:** Módulo Assinatura Eletrônica
- **Fase 4:** Otimizações RNF (performance, permissões, integrações)

---

## 6. Observações Importantes

- O módulo de **Agenda de Visitas** já existe e pode ser integrado como parte do fluxo de Vistoria.
- O módulo de **Assinaturas** existente é apenas para **assinatura de plano (billing)**, não atende assinatura de documentos.
- A criação de **Pessoas** deve evitar duplicar o que já existe em leads e users — sugerido criar um mapeamento/migração.
