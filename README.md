# SOCIMOB (v2) — Plataforma Imobiliária Multi‑Tenant

Plataforma SaaS imobiliária com arquitetura multi-tenant, composta por:
- Backend em **PHP (Lumen 10)**
- Frontend em **React 19 + TypeScript + Vite**
- Servidor Node/Express para servir build em produção
- Integrações operacionais (WhatsApp/Twilio, emissão fiscal NFE.io, filas, portal do cliente)

---

## 1) Visão Geral

O SOCIMOB centraliza operação imobiliária com foco em:
- CRM (leads, pessoas, corretores)
- Gestão de imóveis
- Comunicação (chat/webhooks)
- Financeiro (comissão/aluguel, emissão fiscal)
- Portal público por tenant (domínio próprio)

O sistema foi desenhado para **isolar tenants por domínio** e aplicar identidade visual/configuração por tenant.

---

## 2) Linguagens e Stack

### Backend
- **PHP 8.1+**
- **Lumen 10** (Laravel microframework)
- Eloquent ORM
- PHPUnit 10

Dependências principais (`composer.json`):
- `laravel/lumen-framework`
- `illuminate/mail`
- `guzzlehttp/guzzle`
- `league/flysystem`

### Frontend
- **TypeScript 5**
- **React 19**
- **Vite 7**
- **Tailwind CSS 4**
- React Query, Wouter, Radix UI, Framer Motion, Zustand, Zod
- Vitest + Testing Library

Dependências e scripts no `package.json` raiz (o Vite usa `client/` como root).

### Runtime/Entrega
- **Node.js** (Express) para servir o frontend buildado (`server/index.ts` → `dist/index.js`)
- Backend API Lumen separado

---

## 3) Arquitetura (alto nível)

```mermaid
flowchart LR
    U[Usuário
    Browser] --> FE[Frontend React/Vite
    client/src]
    FE -->|/api| BE[Backend Lumen
    app + routes]
    BE --> DB[(Banco de Dados)]
    BE --> EXT[Integrações Externas
    Twilio / NFE.io / etc]
    FE --> CFG[/api/portal/config
    Branding por tenant]
```

### Padrão de isolamento multi-tenant
- Resolução de tenant via middleware `resolve-tenant`
- Rotas protegidas usam `resolve-tenant` + autenticação (`simple-auth`)
- Branding e configuração por domínio (`X-Tenant-Domain`)

---

## 4) Estrutura de Pastas

```text
socimobatual/
├─ app/                     # Domínio backend (controllers, models, services, middleware)
├─ bootstrap/               # Bootstrap Lumen
├─ config/                  # Configs backend
├─ database/                # Migrations, seeders, factories
├─ routes/                  # Rotas Lumen (web/admin/super-admin/portal/...)
├─ tests/                   # Testes backend (PHPUnit)
├─ client/                  # Frontend React + TypeScript
│  ├─ src/
│  └─ index.html
├─ server/                  # Servidor Node/Express para frontend em produção
├─ public/                  # Arquivos públicos e estáticos
├─ scripts/                 # Scripts operacionais e diagnósticos
├─ docs/                    # Documentação adicional
├─ composer.json            # Dependências PHP
├─ package.json             # Dependências JS/TS e scripts frontend/build
└─ vite.config.ts           # Configuração Vite (root client/, proxy /api)
```

---

## 5) Backend — Inicialização e Fluxo

Arquivo chave: `bootstrap/app.php`

### O que é configurado
- Facades + Eloquent
- Service providers
- Middleware global (`CorsMiddleware`)
- Route middleware:
  - `resolve-tenant`
  - `simple-auth`
  - `throttle`
- Carregamento de rotas:
  - `routes/web.php`
  - `routes/admin.php`
  - `routes/super-admin.php`
  - `routes/client-portal.php`
  - `routes/subscriptions.php`
  - `routes/themes.php`
  - `routes/domains.php`
  - `routes/portal.php`

---

## 6) Frontend — Inicialização e Branding

Arquivo chave: `client/src/main.tsx`

### Ponto importante
O bootstrap aplica branding de tenant antes do primeiro render (com cache por hostname), reduzindo “flash” de tema default.

### Configuração de dev
`vite.config.ts`:
- `root: client/`
- Proxy `/api`, `/storage`, `/uploads` para backend (`VITE_DEV_API_TARGET`, default `http://127.0.0.1:8000`)

---

## 7) Como rodar localmente

## Pré-requisitos
- PHP 8.1+
- Composer
- Node 20+ (recomendado)
- npm/pnpm
- Banco de dados configurado no `.env`

## 7.1 Backend
```bash
composer install
cp .env.example .env   # se ainda não existir
php artisan migrate
php -S 127.0.0.1:8000 -t public
```

Alternativa (quando aplicável):
```bash
php artisan serve --host=127.0.0.1 --port=8000
```

## 7.2 Frontend (dev)
No diretório raiz do projeto:
```bash
npm install
npm run dev
```

Vite sobe (por padrão) em `http://localhost:3000` e proxya `/api` para o backend.

## 7.3 Build produção
```bash
npm run build
```
Isso gera:
- Frontend em `dist/public`
- Servidor Node bundle em `dist/index.js`

Subir frontend buildado:
```bash
npm start
```

---

## 8) Variáveis de Ambiente (principais)

> Use seu `.env` atual como fonte de verdade. Abaixo estão grupos funcionais importantes.

### Aplicação
- `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_TIMEZONE`

### Banco
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

### Tenant / domínio
- Configuração de domínio/tenant no banco e middlewares

### Frontend (dev)
- `VITE_DEV_API_TARGET` (default: `http://127.0.0.1:8000`)

### Fiscal (NFE.io)
- `NFE_IO_BASE_URL`
- `NFE_IO_API_KEY`
- `NFE_IO_COMPANY_ID`
- `NFE_IO_SERVICE_CODE`

### Mensageria/WhatsApp (quando usado)
- Chaves e endpoints de Twilio/WhatsApp conforme ambiente

---

## 9) Módulos funcionais (resumo)

### Portal público
- Config do tenant
- Lista e detalhe de imóveis
- Captura de lead/chat lead

### CRM
- Leads
- Pessoas (PF/PJ)
- Interações/documentos/relacionamentos

### Financeiro
- Lançamentos de corretagem/aluguel
- Emissão fiscal (NFS-e)
- Fluxos de status financeiro/fiscal

### Operação
- Vistorias
- Importação e sincronização
- Logs/suporte administrativo

---

## 10) Testes e Qualidade

### Backend
```bash
vendor/bin/phpunit
```

### Frontend
```bash
npm run test
npm run check
npm run lint
```

---

## 11) Endpoints úteis (referência rápida)

### Saúde
- `GET /api/health`

### Auth
- `POST /api/auth/login`
- `GET /api/auth/me` (autenticado)

### Tenant/Portal
- `GET /api/tenant/config`
- `GET /api/portal/config`

### Pessoas
- `GET /api/pessoas`
- `POST /api/pessoas`
- `PUT /api/pessoas/{id}`

### Financeiro/Admin
- Endpoints em `routes/admin.php` (incluindo emissão e listagem financeira)

---

## 12) Deploy (visão prática)

O repositório possui múltiplos scripts de deploy (`.sh`, `.ps1`, `.bat`).

Fluxo padrão recomendado:
1. Build frontend (`npm run build`)
2. Publicar backend + `dist/public`
3. Ajustar `.env` de produção
4. Rodar migrations necessárias
5. Reiniciar processos (PHP/queue/node)

Consulte scripts específicos no diretório raiz para seu ambiente alvo.

---

## 13) Segurança e Boas Práticas

- Nunca commitar `.env`, tokens e credenciais
- Sempre validar isolamento de tenant por domínio
- Usar rate limit em endpoints sensíveis (`throttle`)
- Revisar logs e webhooks em ambiente de produção

---

## 14) Troubleshooting

### “Frontend sobe, mas API não responde”
- Verifique backend em `127.0.0.1:8000`
- Confira `VITE_DEV_API_TARGET`

### “Tenant errado / branding errado”
- Verifique `window.location.hostname`
- Limpe cache local do browser
- Confira resposta de `/api/portal/config`

### “Rotas SPA quebrando em produção”
- Confirme servidor está entregando `index.html` para rotas não-API
- Verifique `server/index.ts` e configuração de reverse proxy

### “Fila/webhook não processa”
- Validar worker ativo e variáveis de integração

---

## 15) Arquivos-chave para onboarding técnico

- `bootstrap/app.php` — bootstrap e middlewares
- `routes/web.php` — rotas principais
- `routes/admin.php` — administração e financeiro
- `app/Services/` — integrações e regras de domínio
- `client/src/main.tsx` — bootstrap frontend/tenant branding
- `client/src/App.tsx` — roteamento e providers
- `vite.config.ts` — build/dev proxy
- `server/index.ts` — runtime express do build frontend

---

## 16) Licença

Projeto com `license: MIT` no `package.json` (frontend/tooling). Validar política interna da organização para distribuição do backend e ativos proprietários.

---

## 17) Arquitetura funcional plugável (Portal x Admin x Operação)

Esta seção define a separação de responsabilidades para evolução do SOCIMOB sem acoplamento indevido.

### 17.1 Domínios de negócio

### A) Portal da Pessoa (locador/locatário)
Pessoa autenticada vê apenas seu recorte:
- Meus imóveis (com base no papel no contrato)
- Meu financeiro (cobranças e histórico ligados ao contrato)
- Minhas notas fiscais (quando for tomador/destinatário)
- Chamados (abertura, acompanhamento, anexos)

Regra central:
- Portal não é contas a pagar/receber genérico.
- Portal mostra somente cobrança/financeiro do contrato da pessoa.

### B) Admin da Imobiliária (financeiro interno)
Módulo interno para caixa e obrigações do tenant:
- Contas a receber (taxa adm, comissão, serviços, acordos, repasses)
- Contas a pagar (fornecedor, manutenção, impostos, comissão, repasses)
- Baixa/conciliação, anexos, categoria e centro de custo

Regra central:
- Admin enxerga o financeiro completo do tenant.
- Portal enxerga apenas o recorte da pessoa/contrato.

### C) Operação (chamados)
Domínio transversal:
- Chamado nasce no Portal
- Triagem e execução no Admin
- Pode gerar custo (conta a pagar) e/ou cobrança (contrato)

---

## 18) Modelagem mínima recomendada (MVP evolutivo)

Objetivo: destravar Portal financeiro consistente + contas a pagar/receber no Admin.

### 18.1 Entidades do Portal

### 1) Contrato de locação
Campos mínimos:
- `tenant_id`
- `imovel_id`
- `locador_pessoa_id`
- `locatario_pessoa_id`
- `status` (rascunho, ativo, encerrado...)
- `inicio`, `fim`, `dia_vencimento`
- valores base (aluguel, condomínio, iptu, taxa, seguro)
- regra de reajuste (índice, periodicidade, próxima data)

### 2) Cobrança do contrato (fatura mensal)
Campos mínimos:
- `contrato_id`, `competencia` (YYYY-MM), `vencimento`
- `itens` (json: aluguel, cond, iptu, desconto, multa, juros)
- `valor_total`
- `status` (gerada, enviada, paga, vencida, cancelada)
- integração (`gateway`, `gateway_ref`, `linha_digitavel`, `pix`, `url`)

### 3) Documento fiscal (NFS-e)
Vínculo por cobrança/serviço:
- `tenant_id`, `cobranca_id` (ou `referencia_id`)
- `status` (processando, emitida, cancelada, erro)
- `pdf_url`, `xml_url`, chaves e referências NFE.io

Resultado no Portal:
- extrato por contrato
- lista de cobranças
- 2ª via/pix/comprovante
- notas fiscais relacionadas

### 18.2 Entidades do Financeiro Admin

### 4) Lançamento financeiro interno
Campos mínimos:
- `tenant_id`
- `tipo` (pagar, receber)
- `pessoa_id` (cliente/fornecedor/corretor)
- `origem` (manual, manutencao, comissao, taxa_adm, imposto, repasse, contrato)
- `competencia`, `vencimento`, `valor`
- `status` (aberto, baixado, vencido, cancelado)
- `categoria_id`, `centro_custo_id`
- `anexos` (comprovante, NF, orçamento)
- `referencias` (`chamado_id`, `cobranca_id`, `nota_fiscal_id`)

### 5) Baixa/conciliação
Registro de movimento:
- `lancamento_id`
- `data_baixa`, `valor_baixado`, `forma_pagamento`
- `usuario_id`, `observacao`
- suporte a estorno e renegociação com trilha de auditoria

---

## 19) Chamados com anexos (Portal + Admin)

Estrutura sugerida:
- `chamados` (titulo, categoria, prioridade, status, imovel_id, contrato_id, pessoa_id)
- `chamado_mensagens` (timeline/chat por autor pessoa/user/sistema)
- `chamado_anexos` (imagem/video, storage_path, mime_type, size, thumbnail)

Regras de visibilidade:
- mensagem pública: Portal + Admin
- mensagem interna: apenas Admin

Boas práticas para vídeo:
- limite de tamanho por arquivo
- URL assinada/expirável
- thumbnail obrigatória
- compressão opcional no client

---

## 20) Fluxos de UX recomendados

### 20.1 Portal da Pessoa

Menu sugerido:
- Visão Geral
- Meus Imóveis
- Financeiro
- Boletos
- Notas Fiscais
- Chamados
- Documentos

Visão Geral:
- próximos vencimentos
- pendências (vencido/doc/chamado)
- últimas atividades

Meus Imóveis:
- locador: carteira de imóveis e contratos
- locatário: contrato ativo e histórico

Financeiro/Boletos/NF:
- tela baseada em cobrança (não em contas genéricas)
- ações: 2ª via, pix, comprovante, histórico e NF vinculada

### 20.2 Admin Financeiro

Contas a Receber:
- filtros por status, período, pessoa, categoria, origem
- ações: criar, editar, baixar, anexar, exportar
- visões: vencimentos, aging, centro de custo

Contas a Pagar:
- mesmo padrão
- fluxo opcional de aprovação (rascunho → aprovado → pago)

### 20.3 Fluxo de chamado com impacto financeiro

1. Pessoa abre chamado no Portal com anexos
2. Admin faz triagem e direciona responsável/prestador
3. Se houver custo:
  - anexa orçamento
  - aprovação do locador (quando aplicável)
  - gera conta a pagar vinculada ao chamado
4. Encerramento:
  - evidências de execução
  - fechamento + satisfação opcional

---

## 21) Roadmap MVP recomendado

Para destravar 80% de valor com menor risco:

1. Contrato + Cobrança + vínculo pessoa/imóvel
2. Chamados com anexos e timeline
3. Admin contas a pagar/receber com referência cruzada (chamado/cobrança)

Incrementos seguintes:
- aprovação financeira por perfil
- conciliação avançada
- dashboards gerenciais por categoria e centro de custo
