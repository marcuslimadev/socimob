# 🛠️ Instruções para Agentes de Codificação AI

## Visão Geral do Projeto
Plataforma SaaS multi-tenant "Exclusiva" para gerenciamento de imobiliárias. Stack: **Lumen 10** (backend API) + **HTML/jQuery** (frontend) + **MySQL** + **Docker**.

### Componentes Principais
- **Backend**: Lumen 10 (não Laravel!) em `backend/` - API REST com autenticação token-based
- **Frontend**: HTML/jQuery em `backend/public/app/` - Interface simples com TailwindCSS CDN
- **Banco**: MySQL local via XAMPP ou Docker
- **Docs**: Arquitetura detalhada em `docs/`

## 🚀 Início Rápido (SERVIDOR ÚNICO)

### Pré-requisitos
- PHP 8.1+ com extensões MySQL
- MySQL rodando (XAMPP ou standalone)
- Banco `exclusiva` criado
- **Node.js NÃO é necessário** ✅

### Configuração e Execução

#### 1. Configurar Backend
```bash
cd backend
composer install
cp .env.example .env  # Configure DB_*
```

#### 2. Iniciar Servidor (Opção 1 - Recomendada)
**Windows:**
```bash
# Clique duplo no arquivo ou:
backend\START.bat
```

#### 2. Iniciar Servidor (Opção 2 - Manual)
```bash
cd backend
php -S 127.0.0.1:8000 -t public
```

#### 3. Acessar Sistema
Abra o navegador em:
```
http://127.0.0.1:8000/app/
```

#### 4. Credenciais
- **Super Admin**: `admin@exclusiva.com` / `password`
- **Admin Imobiliária**: `contato@exclusivalarimoveis.com.br` / (verificar no banco)

### Docker (alternativa)
```bash
docker-compose -f docker/docker-compose.yml up -d
```

## Convenções e Padrões

### Multi-Tenancy
- **BelongsToTenant trait**: Adiciona `tenant_id` automaticamente a models
- **ResolveTenant middleware**: Resolve tenant por domínio/subdomínio
- **Global Scopes**: Filtram queries automaticamente por tenant
- Exemplo:
  ```php
  class Property extends Model {
      use BelongsToTenant; // Auto-adiciona tenant_id
  }
  ```

### Backend (Lumen)
- **Estrutura**:
  - `app/Http/Controllers/` - Controllers (não use namespaces aninhados desnecessários)
  - `app/Services/` - Lógica de negócio (TenantService, PagarMeService, etc.)
  - `app/Models/` - Models Eloquent com traits
  - `app/Http/Middleware/` - Auth, CORS, ResolveTenant
  - `routes/web.php` - Rotas principais
  - `routes/super-admin.php`, `routes/admin.php` - Rotas específicas

- **Autenticação**: Token simples base64 (não JWT completo)
  ```php
  // Gera: base64(user_id|timestamp|secret)
  $token = base64_encode($user->id . '|' . time() . '|' . $secret);
  ```

- **Middleware**: Use `simple-auth` para rotas protegidas
  ```php
  $router->group(['middleware' => 'simple-auth'], function () use ($router) {
      // rotas protegidas
  });
  ```

### Frontend (HTML/jQuery)
- **Localização**: `backend/public/app/` - Tudo no mesmo servidor!
- **API**: Usa caminhos relativos (`/api`) - sem configuração de proxy
- **Autenticação**: localStorage com token Bearer
- **Estrutura**:
  - `index.html` - Redirecionamento inteligente (login ou dashboard)
  - `login.html` - Página de login com credenciais pré-preenchidas
  - `dashboard.html` - Dashboard principal com cards e menu
  - `leads.html` - Gestão de leads com tabela e filtros
  - `imoveis.html` - Gestão de imóveis com grid de cards
  - `conversas.html` - Sistema de chat estilo WhatsApp
  - `configuracoes.html` - Configurações com abas (perfil, empresa, integrações, segurança)

- **Dependências**: Apenas CDNs (jQuery 3.7.1 + TailwindCSS)
- **Vantagens**: 
  - ✅ Zero build process
  - ✅ Servidor único (porta 8000)
  - ✅ Sem Node.js/npm
  - ✅ Deploy extremamente simples

### Testes
```bash
# Backend
cd backend && vendor/bin/phpunit
```

### Deploy
Ver `docker/GUIA_DOCKER_AWS.md` para AWS deployment completo

## Integrações e Dependências
- **Pagar.me**: Integração para gerenciamento de pagamentos.
- **Docker**: Configuração completa para desenvolvimento e produção.
- **AWS**: Infraestrutura de deploy.

## Padrões de Código
- **Backend**:
  - Siga as práticas recomendadas do Laravel.
  - Utilize migrations para alterações no banco de dados.
- **Frontend**:
  - Siga o padrão de projeto definido em `frontend/ARCHITECTURE_DIAGRAM.md`.

## 🔧 Troubleshooting Comum

### Backend não responde
1. **Verificar MySQL**: `Get-Service mysql` (deve estar Running)
2. **Criar banco se não existe**: `mysql -u root -e "CREATE DATABASE exclusiva"`
3. **Verificar .env**: Confirme `DB_CONNECTION=mysql`, `DB_DATABASE=exclusiva`
4. **Logs**: `backend/storage/logs/lumen-YYYY-MM-DD.log`
5. **Reiniciar servidor**:
   ```bash
   # Matar processos PHP
   Get-Process php | Stop-Process -Force
   # Reiniciar
   cd backend; php -S 127.0.0.1:8000 -t public
   ```

### Frontend não carrega
1. **Verificar backend**: `http://127.0.0.1:8000` deve retornar JSON com app info
2. **Acessar URL correta**: `http://127.0.0.1:8000/app/` (com `/app/`)
3. **CORS não é problema**: Frontend e API no mesmo domínio!
4. **Token**: Limpar localStorage se necessário: `localStorage.clear()`
5. **Debug login**: Abra DevTools (F12) → Console para ver logs detalhados
6. **Verificar arquivos**: Confirme que `backend/public/app/*.html` existem

### Sistema de Autenticação
- **Simples e direto**: localStorage + Bearer token
- **Login aceita**: `senha` ou `password` (backend suporta ambos)
- **Redireciona automaticamente**: Se não autenticado, vai para login
- **Persiste sessão**: Token fica salvo entre reloads

### Credenciais de teste
- **Super Admin**: `admin@exclusiva.com` / `password`
- Para criar novos users: `backend/create_superadmin.php` como exemplo

## 📚 Arquivos-Chave

### Arquitetura
- `docs/exclusiva_saas_architecture.md` - Diagrama e visão geral
- `docs/FASE2_MULTI_TENANT_IMPLEMENTATION.md` - Implementação multi-tenant
- `docs/INDICE_DOCUMENTACAO.md` - Índice completo

### Backend
- `backend/bootstrap/app.php` - Configuração principal do Lumen
- `backend/routes/web.php` - Rotas principais (auth, dashboard)
- `backend/app/Http/Middleware/ResolveTenant.php` - Lógica de tenant resolution
- `backend/app/Services/TenantService.php` - Gerenciamento de tenants

### Frontend
- `backend/public/app/*.html` - Páginas HTML/jQuery (servidor único)
- `backend/START.bat` - Script de inicialização simples
- `SERVIDOR_UNICO.md` - Documentação completa do novo setup

---

**Dica**: Para desenvolvimento rápido, use os scripts em `backend/` como `create_superadmin.php`, `check_db.php`, `test_login.php`