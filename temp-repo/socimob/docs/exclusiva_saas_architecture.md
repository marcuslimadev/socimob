# Arquitetura SaaS - Exclusiva Lar

## Diagrama de Infraestrutura AWS

```mermaid
graph TB
    subgraph "Internet"
        Users["👥 Usuários Finais"]
        AdminImob["👨‍💼 Admin Imobiliária"]
        SuperAdmin["👑 Super Admin"]
    end

    subgraph "AWS CloudFront & Route 53"
        CF["CloudFront<br/>(CDN)"]
        R53["Route 53<br/>(DNS)"]
    end

    subgraph "Frontend - S3 + CloudFront"
        S3Frontend["S3 Bucket<br/>Frontend Estático<br/>(HTML/CSS/JS)"]
        CF -->|Distribui| S3Frontend
    end

    subgraph "Domínios Personalizados"
        Domain1["imobiliariajoao.com.br"]
        Domain2["imobiliariamarcos.com.br"]
        DomainSuper["exclusiva-admin.com.br"]
        R53 -->|Aponta para| Domain1
        R53 -->|Aponta para| Domain2
        R53 -->|Aponta para| DomainSuper
    end

    subgraph "Application Layer - EC2"
        EC2["EC2 Instance(s)<br/>Laravel Lumen Backend<br/>(PHP 8.1+)"]
        LB["Load Balancer<br/>(ALB/NLB)"]
        LB -->|Roteia| EC2
    end

    subgraph "Database Layer - RDS"
        RDS["RDS PostgreSQL<br/>Multi-Tenant Database<br/>(tenant_id em todas tabelas)"]
    end

    subgraph "Cache & Queue"
        Redis["ElastiCache Redis<br/>(Cache + Queues)"]
        SQS["SQS<br/>(Job Queue)"]
    end

    subgraph "Storage"
        S3Storage["S3 Bucket<br/>Imagens & Documentos<br/>(Leads, Imóveis)"]
    end

    subgraph "External Services"
        PagarMe["Pagar.me API<br/>(Pagamentos)"]
        Notif["SNS/SES<br/>(Notificações)"]
    end

    subgraph "Monitoring & Logging"
        CloudWatch["CloudWatch<br/>(Logs & Metrics)"]
        APM["X-Ray<br/>(Performance)"]
    end

    Users -->|Acessa via domínio| CF
    AdminImob -->|Acessa via domínio| CF
    SuperAdmin -->|Acessa via domínio| CF
    
    CF -->|Requisições API| LB
    EC2 -->|Consulta/Escreve| RDS
    EC2 -->|Cache| Redis
    EC2 -->|Jobs| SQS
    EC2 -->|Upload/Download| S3Storage
    EC2 -->|Pagamentos| PagarMe
    EC2 -->|Notificações| Notif
    EC2 -->|Logs| CloudWatch
    EC2 -->|Tracing| APM

    style Users fill:#e1f5ff
    style AdminImob fill:#f3e5f5
    style SuperAdmin fill:#fff3e0
    style EC2 fill:#c8e6c9
    style RDS fill:#ffccbc
    style S3Frontend fill:#b3e5fc
    style S3Storage fill:#b3e5fc
    style PagarMe fill:#f8bbd0
```

---

## Arquitetura Multi-Tenant

```mermaid
graph LR
    subgraph "Identificação de Tenant"
        Domain["Domínio da Requisição<br/>imobiliariajoao.com.br"]
        Middleware["Middleware<br/>TenantResolver"]
        Domain -->|Extrai| Middleware
    end

    subgraph "Banco de Dados Único"
        Tenants["tenants<br/>id | domain | name | theme | status"]
        Users["users<br/>id | tenant_id | name | email | role"]
        Properties["imo_properties<br/>id | tenant_id | titulo | preco"]
        Leads["leads<br/>id | tenant_id | nome | email"]
        Subscriptions["subscriptions<br/>id | tenant_id | plan | status | expires_at"]
        Configs["tenant_configs<br/>id | tenant_id | api_key | api_secret"]
    end

    subgraph "Isolamento de Dados"
        Scope["Global Scope<br/>->where('tenant_id', $tenantId)"]
    end

    Middleware -->|Carrega| Tenants
    Middleware -->|Define Context| Scope
    Scope -->|Filtra| Users
    Scope -->|Filtra| Properties
    Scope -->|Filtra| Leads
    Scope -->|Filtra| Subscriptions
    Scope -->|Filtra| Configs

    style Middleware fill:#fff9c4
    style Scope fill:#c8e6c9
    style Tenants fill:#ffccbc
```

---

## Fluxo de Autenticação Multi-Tenant

```mermaid
sequenceDiagram
    participant User as Usuário
    participant Browser as Browser
    participant R53 as Route 53
    participant LB as Load Balancer
    participant Middleware as TenantMiddleware
    participant Auth as AuthController
    participant DB as RDS

    User->>Browser: Acessa imobiliariajoao.com.br
    Browser->>R53: Resolve domínio
    R53->>LB: Aponta para ALB
    LB->>Middleware: Requisição chega
    
    Middleware->>Middleware: Extrai domínio<br/>imobiliariajoao.com.br
    Middleware->>DB: Busca tenant_id
    DB-->>Middleware: tenant_id = 5
    Middleware->>Middleware: Define Context<br/>app('tenant')->id = 5
    
    Middleware->>Auth: Passa requisição
    Auth->>DB: Valida credenciais<br/>WHERE tenant_id = 5
    DB-->>Auth: Usuário encontrado
    Auth-->>Browser: Token JWT + tenant_id
    
    Browser->>Browser: Armazena token
    User->>Browser: Próxima requisição
    Browser->>LB: Envia token
    LB->>Middleware: Valida token
    Middleware->>DB: Verifica tenant_id
    DB-->>Middleware: ✓ Autorizado
    Middleware->>Auth: Requisição válida
```

---

## Estrutura de Tabelas Multi-Tenant

### Tabela: tenants
```sql
CREATE TABLE tenants (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    domain VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    theme ENUM('classico', 'bauhaus') DEFAULT 'classico',
    subscription_status ENUM('active', 'inactive', 'suspended') DEFAULT 'inactive',
    subscription_plan VARCHAR(50),
    subscription_expires_at TIMESTAMP,
    pagar_me_customer_id VARCHAR(255),
    pagar_me_subscription_id VARCHAR(255),
    api_key VARCHAR(255),
    api_secret VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Tabela: users (modificada)
```sql
ALTER TABLE users ADD COLUMN tenant_id BIGINT NOT NULL AFTER id;
ALTER TABLE users ADD FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;
ALTER TABLE users ADD COLUMN role ENUM('super_admin', 'admin', 'corretor', 'cliente') DEFAULT 'cliente';
ALTER TABLE users ADD UNIQUE KEY unique_email_tenant (email, tenant_id);
```

### Tabela: imo_properties (modificada)
```sql
ALTER TABLE imo_properties ADD COLUMN tenant_id BIGINT NOT NULL AFTER id;
ALTER TABLE imo_properties ADD FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;
ALTER TABLE imo_properties ADD INDEX idx_tenant_id (tenant_id);
```

### Tabela: leads (modificada)
```sql
ALTER TABLE leads ADD COLUMN tenant_id BIGINT NOT NULL AFTER id;
ALTER TABLE leads ADD FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;
ALTER TABLE leads ADD INDEX idx_tenant_id (tenant_id);
```

### Tabela: subscriptions (nova)
```sql
CREATE TABLE subscriptions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT NOT NULL,
    plan_id VARCHAR(50) NOT NULL,
    status ENUM('active', 'past_due', 'canceled') DEFAULT 'active',
    current_period_start TIMESTAMP,
    current_period_end TIMESTAMP,
    pagar_me_subscription_id VARCHAR(255),
    pagar_me_customer_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tenant_plan (tenant_id)
);
```

### Tabela: tenant_configs (nova)
```sql
CREATE TABLE tenant_configs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT NOT NULL UNIQUE,
    api_key_pagar_me VARCHAR(255),
    api_key_apm_imoveis VARCHAR(255),
    api_key_neca VARCHAR(255),
    logo_url VARCHAR(500),
    primary_color VARCHAR(7),
    secondary_color VARCHAR(7),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

---

## Níveis de Acesso e Permissões

### Super Admin (você)
- ✅ Criar/editar/deletar imobiliárias (tenants)
- ✅ Visualizar dashboard global
- ✅ Gerenciar planos de assinatura
- ✅ Acessar logs de todas as imobiliárias
- ✅ Configurar chaves de API globais
- ✅ Gerenciar temas disponíveis

### Admin da Imobiliária
- ✅ Gerenciar seus corretores
- ✅ Visualizar seus imóveis
- ✅ Visualizar seus leads
- ✅ Configurar seu domínio
- ✅ Escolher tema (Clássico ou Bauhaus)
- ✅ Adicionar chaves de API (Pagar.me, APM, NECA)
- ✅ Gerenciar sua assinatura
- ❌ Acessar dados de outras imobiliárias

### Corretor
- ✅ Gerenciar seus imóveis
- ✅ Gerenciar seus leads
- ✅ Visualizar conversas
- ❌ Acessar dados de outros corretores
- ❌ Gerenciar imobiliária

### Cliente Final
- ✅ Se cadastrar
- ✅ Salvar "intenções" de imóveis
- ✅ Favoritar imóveis
- ✅ Receber notificações
- ✅ Visualizar imóveis públicos
- ❌ Acessar painel administrativo

---

## Fluxo de Assinatura (Pagar.me)

```mermaid
sequenceDiagram
    participant Admin as Admin Imobiliária
    participant Frontend as Frontend
    participant Backend as Backend Laravel
    participant PagarMe as Pagar.me API
    participant DB as RDS

    Admin->>Frontend: Clica em "Ativar Assinatura"
    Frontend->>Frontend: Abre formulário de pagamento
    Admin->>Frontend: Insere dados do cartão
    Frontend->>Backend: POST /api/subscriptions/create
    
    Backend->>PagarMe: Cria cliente + assinatura
    PagarMe-->>Backend: subscription_id + status
    
    Backend->>DB: Salva subscription
    DB-->>Backend: ✓ Salvo
    
    Backend-->>Frontend: ✓ Assinatura criada
    Frontend-->>Admin: Exibe confirmação
    
    Note over PagarMe: Webhook: payment.success
    PagarMe->>Backend: POST /webhooks/pagar-me
    Backend->>DB: Atualiza status para 'active'
    Backend->>DB: Define expires_at
    
    Note over Backend: Cron Job Diário
    Backend->>PagarMe: Verifica assinaturas vencidas
    PagarMe-->>Backend: Lista de assinaturas
    Backend->>DB: Atualiza status expiradas
```

---

## Fluxo de Domínio Personalizado

```mermaid
graph LR
    A["Admin Imobiliária<br/>Cadastra domínio<br/>imobiliariajoao.com.br"]
    B["Backend salva<br/>em tenants.domain"]
    C["Admin aponta CNAME<br/>para exclusiva.com"]
    D["Route 53<br/>Resolve domínio"]
    E["CloudFront<br/>Distribui conteúdo"]
    F["Frontend carrega<br/>tema escolhido"]
    G["Usuário vê site<br/>personalizado"]

    A -->|POST /api/tenants/domain| B
    B -->|Instrui| C
    C -->|Aponta para| D
    D -->|Roteia para| E
    E -->|Carrega| F
    F -->|Renderiza| G
```

---

## Fluxo de Notificação para Cliente Final

```mermaid
sequenceDiagram
    participant Client as Cliente Final
    participant Frontend as Frontend
    participant Backend as Backend
    participant DB as RDS
    participant Queue as SQS
    participant SNS as SNS/SES

    Client->>Frontend: Cadastra intenção<br/>3 quartos, Bairro X
    Frontend->>Backend: POST /api/client-intentions
    Backend->>DB: Salva intenção
    DB-->>Backend: ✓ Salvo

    Note over Backend: Cron Job: A cada 1 hora
    Backend->>DB: Busca novas intenções
    Backend->>DB: Busca imóveis novos
    Backend->>DB: Faz matching
    
    alt Encontrou imóvel
        Backend->>DB: Cria notificação
        Backend->>Queue: Enfileira job
        Queue->>SNS: Envia notificação
        SNS-->>Client: Notificação via email/SMS
        Client->>Frontend: Clica em notificação
        Frontend->>Backend: GET /api/property/:id
        Backend-->>Frontend: Dados do imóvel
        Frontend-->>Client: Exibe imóvel
    end
```

---

## Estrutura de Temas

### Tema Clássico
```
├── resources/views/themes/classico/
│   ├── layouts/
│   │   ├── app.blade.php
│   │   └── auth.blade.php
│   ├── pages/
│   │   ├── home.blade.php
│   │   ├── properties.blade.php
│   │   └── property-detail.blade.php
│   ├── components/
│   │   ├── header.blade.php
│   │   ├── footer.blade.php
│   │   └── property-card.blade.php
│   └── css/
│       └── theme.css
```

### Tema Bauhaus
```
├── resources/views/themes/bauhaus/
│   ├── layouts/
│   │   ├── app.blade.php
│   │   └── auth.blade.php
│   ├── pages/
│   │   ├── home.blade.php
│   │   ├── properties.blade.php
│   │   └── property-detail.blade.php
│   ├── components/
│   │   ├── header.blade.php
│   │   ├── footer.blade.php
│   │   └── property-card.blade.php
│   └── css/
│       └── theme.css (Minimalista, geométrico)
```

---

## Resumo da Análise do Código Existente

### Backend (Laravel Lumen)
- **Linguagem:** PHP 8.1+
- **Framework:** Laravel Lumen 10.0
- **Banco de Dados:** PostgreSQL
- **Linhas de Código:** ~6.800 linhas
- **Modelos:** User, Property, Lead, Conversa, Atividade, LeadDocument, LeadPropertyMatch, AppSetting
- **Controllers:** 12 controllers (Auth, Dashboard, Leads, Properties, etc.)
- **Rotas:** 992 linhas de rotas
- **Migrations:** 9 migrations existentes

### Frontend (Vue 3 + TypeScript)
- **Linguagem:** TypeScript + Vue 3
- **Build Tool:** Vite
- **Styling:** Tailwind CSS
- **Linhas de Código:** ~6.170 linhas
- **Componentes:** PropertyMap, Imoveis, Dashboard, etc.
- **Estado:** Pinia (gerenciamento de estado)

### Funcionalidades Existentes
- ✅ Autenticação de usuários
- ✅ Gerenciamento de imóveis
- ✅ Gerenciamento de leads
- ✅ Mapa interativo com Leaflet
- ✅ Clustering de marcadores
- ✅ Desenho de áreas (polígono, retângulo, círculo)
- ✅ Conversas entre corretores e clientes
- ✅ Dashboard com estatísticas
- ✅ Importação de imóveis via API
- ✅ Sincronização com Zillow/Realtor.com

---

## Próximas Fases de Desenvolvimento

### Fase 2: Estrutura Multi-Tenant
- Criar tabela `tenants`
- Adicionar `tenant_id` a todas as tabelas
- Implementar Global Scopes
- Criar Middleware de identificação de tenant

### Fase 3: Super Admin Panel
- Dashboard global
- Gerenciamento de imobiliárias
- Gerenciamento de planos

### Fase 4: Integração Pagar.me
- Criar tabela `subscriptions`
- Integrar API do Pagar.me
- Implementar webhooks

### Fase 5: Domínios e Temas
- Sistema de domínios personalizados
- Temas Clássico e Bauhaus
- Configuração de cores

### Fase 6: Portal Cliente Final
- Cadastro de clientes
- Sistema de intenções
- Notificações

### Fase 7: Infraestrutura AWS
- Configurar EC2
- Configurar RDS
- Configurar S3 + CloudFront
- Configurar Route 53

---

**Data:** 2025-12-18
**Status:** Análise Completa ✅
**Próximo Passo:** Iniciar Fase 2 - Implementação Multi-Tenant
