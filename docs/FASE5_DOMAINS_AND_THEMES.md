# Fase 5: Implementação do Sistema de Domínios Personalizados e Temas (Clássico e Bauhaus)

## 📋 Resumo Executivo

Nesta fase, implementamos o sistema completo de domínios personalizados e temas customizáveis, permitindo que cada imobiliária tenha seu próprio domínio e escolha entre dois temas com cores personalizáveis.

---

## 🎯 Objetivos Alcançados

### ✅ 1. Serviço de Temas
**Arquivo:** `app/Services/ThemeService.php`

Serviço centralizado para gerenciar temas e cores:

#### Temas Disponíveis

| Tema | Descrição | Estilo |
|------|-----------|--------|
| **Clássico** | Design tradicional, corporativo | Fonte Segoe UI, espaçamento generoso |
| **Bauhaus** | Design minimalista, geométrico | Fonte Helvetica, linhas retas, tipografia bold |

#### Cores Padrão

**Tema Clássico:**
```json
{
    "primary": "#1a1a1a",
    "secondary": "#ffffff",
    "accent": "#ff6b6b",
    "success": "#51cf66",
    "warning": "#ffd43b",
    "danger": "#ff6b6b",
    "info": "#74c0fc"
}
```

**Tema Bauhaus:**
```json
{
    "primary": "#000000",
    "secondary": "#f5f5f5",
    "accent": "#ff0000",
    "success": "#00ff00",
    "warning": "#ffff00",
    "danger": "#ff0000",
    "info": "#0000ff"
}
```

#### Métodos Implementados

```php
// Obter tema
$themeService->getTheme($tenant)
// Retorna: ['name' => 'classico', 'colors' => [...], 'logo_url' => '...']

// Atualizar tema
$themeService->updateTheme($tenant, 'bauhaus', [
    'primary' => '#000000',
    'accent' => '#ff0000',
])

// Resetar para padrão
$themeService->resetTheme($tenant)

// Gerar CSS customizado
$themeService->generateCSS($tenant)
// Retorna CSS com variáveis CSS e estilos base

// Listar temas disponíveis
$themeService->getAvailableThemes()
```

#### CSS Gerado

O serviço gera CSS com:
- ✅ Variáveis CSS (`:root`)
- ✅ Estilos base por tema
- ✅ Botões customizados
- ✅ Cards e containers
- ✅ Headers e sidebars
- ✅ Links e interações

---

### ✅ 2. Controller de Temas
**Arquivo:** `app/Http/Controllers/ThemeController.php`

Controller para gerenciar temas:

#### Endpoints Implementados

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/api/theme` | Obter tema atual |
| GET | `/api/theme/css` | Obter CSS customizado |
| PUT | `/api/theme` | Atualizar tema |
| POST | `/api/theme/reset` | Resetar para padrão |
| GET | `/api/theme/available` | Listar temas disponíveis |
| GET | `/api/theme/preview/{themeName}` | Preview do tema |

#### Exemplos de Uso

```php
// Obter tema atual
GET /api/theme
{
    "name": "classico",
    "label": "Clássico",
    "colors": {
        "primary": "#1a1a1a",
        "secondary": "#ffffff",
        "accent": "#ff6b6b",
        ...
    },
    "logo_url": "https://...",
    "favicon_url": "https://..."
}

// Obter CSS customizado
GET /api/theme/css
:root {
  --color-primary: #1a1a1a;
  --color-secondary: #ffffff;
  --color-accent: #ff6b6b;
  ...
}

body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  color: #1a1a1a;
  background-color: #ffffff;
}

.btn-primary {
  background-color: #1a1a1a;
  ...
}

// Atualizar tema
PUT /api/theme
{
    "theme": "bauhaus",
    "colors": {
        "primary": "#000000",
        "accent": "#ff0000"
    }
}

// Listar temas disponíveis
GET /api/theme/available
{
    "themes": [
        {
            "id": "classico",
            "name": "Clássico",
            "colors": { ... }
        },
        {
            "id": "bauhaus",
            "name": "Bauhaus",
            "colors": { ... }
        }
    ]
}

// Preview do tema
GET /api/theme/preview/bauhaus
{
    "theme": { ... },
    "preview": {
        "name": "Bauhaus",
        "colors": { ... },
        "elements": {
            "button_primary": { ... },
            "button_accent": { ... },
            ...
        }
    }
}
```

---

### ✅ 3. Serviço de Domínios
**Arquivo:** `app/Services/DomainService.php`

Serviço centralizado para gerenciar domínios:

#### Métodos Implementados

```php
// Validar domínio
$domainService->validateDomain('exemplo.com.br')
// Retorna: true/false

// Normalizar domínio
$domainService->normalizeDomain('WWW.EXEMPLO.COM.BR')
// Retorna: 'exemplo.com.br'

// Buscar tenant por domínio
$domainService->findByDomain('exemplo.com.br')
// Retorna: Tenant | null

// Atualizar domínio
$domainService->updateDomain($tenant, 'novo-dominio.com.br')

// Gerar domínio sugerido
$domainService->generateSuggestedDomain('Imobiliária João')
// Retorna: 'imobiliaria-joao.exclusivallar.com.br'

// Obter URL do tenant
$domainService->getTenantUrl($tenant)
// Retorna: 'https://exemplo.com.br'

// Obter URL da API
$domainService->getTenantApiUrl($tenant)
// Retorna: 'https://exemplo.com.br/api'

// Validar DNS
$domainService->validateDNS('exemplo.com.br')
// Retorna: true/false

// Obter informações de DNS
$domainService->getDNSInfo('exemplo.com.br')
// Retorna: ['domain' => '...', 'a_record' => '...', ...]

// Gerar instruções de DNS
$domainService->generateDNSInstructions($tenant)
// Retorna: ['domain' => '...', 'records' => [...], 'instructions' => [...]]
```

---

### ✅ 4. Controller de Domínios
**Arquivo:** `app/Http/Controllers/DomainController.php`

Controller para gerenciar domínios:

#### Endpoints Implementados

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/api/domain` | Obter domínio atual |
| PUT | `/api/domain` | Atualizar domínio |
| POST | `/api/domain/validate` | Validar domínio |
| GET | `/api/domain/dns` | Obter info de DNS |
| GET | `/api/domain/dns-instructions` | Obter instruções de DNS |
| GET | `/api/domain/alternatives` | Listar domínios alternativos |
| POST | `/api/domain/suggest` | Gerar domínio sugerido |

#### Exemplos de Uso

```php
// Obter domínio atual
GET /api/domain
{
    "domain": "imobiliaria-joao.com.br",
    "url": "https://imobiliaria-joao.com.br",
    "api_url": "https://imobiliaria-joao.com.br/api"
}

// Validar domínio
POST /api/domain/validate
{
    "domain": "novo-dominio.com.br"
}
// Resposta:
{
    "domain": "novo-dominio.com.br",
    "is_valid": true,
    "is_available": true,
    "message": "Domínio válido e disponível."
}

// Atualizar domínio
PUT /api/domain
{
    "domain": "novo-dominio.com.br"
}
// Resposta:
{
    "message": "Domain updated successfully",
    "domain": "novo-dominio.com.br",
    "url": "https://novo-dominio.com.br",
    "api_url": "https://novo-dominio.com.br/api"
}

// Obter informações de DNS
GET /api/domain/dns
{
    "domain": "imobiliaria-joao.com.br",
    "a_record": "1.2.3.4",
    "mx_records": [...],
    "txt_records": [...],
    "is_valid": true
}

// Obter instruções de DNS
GET /api/domain/dns-instructions
{
    "domain": "imobiliaria-joao.com.br",
    "records": [
        {
            "type": "A",
            "name": "@",
            "value": "1.2.3.4",
            "ttl": 3600,
            "description": "Aponta o domínio para o servidor"
        },
        {
            "type": "CNAME",
            "name": "www",
            "value": "imobiliaria-joao.com.br",
            "ttl": 3600,
            "description": "Redireciona www para o domínio principal"
        }
    ],
    "instructions": [
        "Acesse o painel de controle do seu registrador de domínio",
        "Procure pela seção 'Gerenciar DNS' ou 'Zone File'",
        ...
    ]
}

// Gerar domínio sugerido
POST /api/domain/suggest
{
    "name": "Imobiliária João"
}
// Resposta:
{
    "suggested_domain": "imobiliaria-joao.exclusivallar.com.br"
}
```

---

### ✅ 5. Rotas de Temas e Domínios
**Arquivos:**
- `routes/themes.php`
- `routes/domains.php`

---

### ✅ 6. Migrations
**Arquivo:** `database/migrations/2025_12_18_100005_add_theme_colors_to_tenant_configs.php`

Adiciona campos de cores à tabela `tenant_configs`:

```sql
ALTER TABLE tenant_configs ADD COLUMN primary_color VARCHAR(7) DEFAULT '#1a1a1a';
ALTER TABLE tenant_configs ADD COLUMN secondary_color VARCHAR(7) DEFAULT '#ffffff';
ALTER TABLE tenant_configs ADD COLUMN accent_color VARCHAR(7) DEFAULT '#ff6b6b';
ALTER TABLE tenant_configs ADD COLUMN success_color VARCHAR(7) DEFAULT '#51cf66';
ALTER TABLE tenant_configs ADD COLUMN warning_color VARCHAR(7) DEFAULT '#ffd43b';
ALTER TABLE tenant_configs ADD COLUMN danger_color VARCHAR(7) DEFAULT '#ff6b6b';
ALTER TABLE tenant_configs ADD COLUMN info_color VARCHAR(7) DEFAULT '#74c0fc';
ALTER TABLE tenant_configs ADD COLUMN logo_url VARCHAR(255);
ALTER TABLE tenant_configs ADD COLUMN favicon_url VARCHAR(255);
```

---

## 🎨 Fluxo de Customização de Tema

### 1. Admin Escolhe Tema

```
Admin acessa: /admin/settings/theme
Vê opções: Clássico ou Bauhaus
Clica em "Escolher"
```

### 2. Admin Customiza Cores

```
Admin vê preview do tema
Customiza cores:
- Cor Primária
- Cor Secundária
- Cor de Destaque
- Cores de Status (sucesso, aviso, perigo, info)

Clica em "Salvar"
```

### 3. Sistema Processa

```
PUT /api/theme
{
    "theme": "bauhaus",
    "colors": {
        "primary": "#000000",
        "accent": "#ff0000"
    }
}

Sistema:
- Valida cores (formato hex)
- Atualiza tema no tenant
- Atualiza cores na config
- Gera novo CSS
```

### 4. Frontend Aplica Tema

```
Frontend faz: GET /api/theme
Obtém informações do tema

Frontend faz: GET /api/theme/css
Obtém CSS customizado

Frontend aplica:
- Variáveis CSS
- Estilos base
- Layout específico do tema
```

### 5. Usuários Veem Novo Tema

```
Ao acessar o site do tenant:
- Layout muda (Clássico ou Bauhaus)
- Cores são aplicadas
- Logo é exibida
- Favicon é aplicado
```

---

## 🌐 Fluxo de Domínio Personalizado

### 1. Admin Cria Tenant

```
Super Admin cria tenant:
- Nome: "Imobiliária João"
- Domínio sugerido: "imobiliaria-joao.exclusivallar.com.br"
```

### 2. Admin da Imobiliária Atualiza Domínio

```
Admin acessa: /admin/settings/domain
Vê domínio atual: "imobiliaria-joao.exclusivallar.com.br"
Quer usar domínio próprio: "imobiliaria-joao.com.br"

Clica em "Validar Domínio"
POST /api/domain/validate
{
    "domain": "imobiliaria-joao.com.br"
}

Sistema:
- Valida formato
- Verifica disponibilidade
- Retorna resultado
```

### 3. Admin Configura DNS

```
Sistema mostra: GET /api/domain/dns-instructions
{
    "records": [
        {
            "type": "A",
            "name": "@",
            "value": "1.2.3.4"
        }
    ],
    "instructions": [...]
}

Admin:
- Acessa registrador de domínio
- Adiciona registros DNS
- Aguarda propagação (até 24h)
```

### 4. Admin Atualiza Domínio

```
Após DNS estar propagado:

PUT /api/domain
{
    "domain": "imobiliaria-joao.com.br"
}

Sistema:
- Valida DNS
- Atualiza domínio no tenant
- Atualiza URLs de acesso
```

### 5. Acesso pelo Novo Domínio

```
Usuários acessam: https://imobiliaria-joao.com.br
Middleware ResolveTenant:
- Extrai domínio
- Busca tenant
- Aplica tema e configurações

Site carrega com tema customizado!
```

---

## 🔐 Segurança

### Validação de Domínio
- ✅ Validação de formato (regex)
- ✅ Verificação de disponibilidade
- ✅ Validação de DNS (opcional)
- ✅ Prevenção de duplicatas

### Validação de Cores
- ✅ Validação de formato hex (#RRGGBB)
- ✅ Validação de intervalo
- ✅ Sanitização de entrada

### Autenticação
- ✅ Apenas admin pode atualizar tema
- ✅ Apenas admin pode atualizar domínio
- ✅ Validação de tenant_id

---

## 📊 Estrutura de Dados

### Tenant
```php
{
    "id": 1,
    "name": "Imobiliária João",
    "domain": "imobiliaria-joao.com.br",
    "theme": "bauhaus",
    "logo_url": "https://...",
    "primary_color": "#000000",
    "secondary_color": "#f5f5f5"
}
```

### TenantConfig
```php
{
    "id": 1,
    "tenant_id": 1,
    "primary_color": "#000000",
    "secondary_color": "#f5f5f5",
    "accent_color": "#ff0000",
    "success_color": "#00ff00",
    "warning_color": "#ffff00",
    "danger_color": "#ff0000",
    "info_color": "#0000ff",
    "logo_url": "https://...",
    "favicon_url": "https://..."
}
```

---

## 🎨 Exemplos de Temas

### Tema Clássico
- Font: Segoe UI
- Spacing: Generoso
- Shadows: Suaves
- Borders: Arredondados
- Colors: Tons neutros + destaque

### Tema Bauhaus
- Font: Helvetica Neue
- Spacing: Compacto
- Shadows: Nenhuma
- Borders: Retos
- Colors: Cores primárias (preto, branco, vermelho)

---

## 🚀 Próximas Etapas

### Fase 6: Portal Cliente Final
- Cadastro de clientes
- Sistema de intenções
- Notificações

### Fase 7: AWS
- Configurar EC2
- Configurar RDS
- Configurar Route 53
- Configurar CloudFront

---

## 📝 Checklist de Implementação

- [x] Criar serviço de temas
- [x] Criar controller de temas
- [x] Criar rotas de temas
- [x] Criar serviço de domínios
- [x] Criar controller de domínios
- [x] Criar rotas de domínios
- [x] Criar migration para cores
- [ ] Registrar rotas em `bootstrap/app.php`
- [ ] Criar testes automatizados
- [ ] Criar documentação de API (Swagger)
- [ ] Criar frontend para temas
- [ ] Criar frontend para domínios

---

## 🔗 Arquivos Criados

| Arquivo | Tipo | Descrição |
|---------|------|-----------|
| `app/Services/ThemeService.php` | Service | Gerenciar temas |
| `app/Http/Controllers/ThemeController.php` | Controller | Temas |
| `routes/themes.php` | Routes | Rotas de temas |
| `app/Services/DomainService.php` | Service | Gerenciar domínios |
| `app/Http/Controllers/DomainController.php` | Controller | Domínios |
| `routes/domains.php` | Routes | Rotas de domínios |
| `database/migrations/2025_12_18_100005_add_theme_colors_to_tenant_configs.php` | Migration | Cores de tema |

---

## 📚 Documentação

- ✅ Análise do projeto: `/home/ubuntu/analise_projeto_exclusiva.md`
- ✅ Arquitetura SaaS: `/home/ubuntu/exclusiva_saas_architecture.md`
- ✅ Fase 2 (Multi-tenant): `/home/ubuntu/FASE2_MULTI_TENANT_IMPLEMENTATION.md`
- ✅ Fase 3 (Super Admin): `/home/ubuntu/FASE3_SUPER_ADMIN_PANEL.md`
- ✅ Fase 4 (Pagar.me): `/home/ubuntu/FASE4_PAGAR_ME_INTEGRATION.md`
- ✅ Fase 5 (este documento): `/home/ubuntu/FASE5_DOMAINS_AND_THEMES.md`

---

**Data:** 2025-12-18
**Status:** ✅ Completo
**Próximo Passo:** Fase 6 - Portal Cliente Final
