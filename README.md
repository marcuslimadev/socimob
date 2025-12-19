# 🏠 SOCIMOB - Sistema de Gestão Imobiliária

## 🎯 Visão Geral

Sistema completo de gestão imobiliária com **servidor único PHP** (Lumen 10) + **HTML/jQuery**.

## 🚀 Início Rápido (3 Passos)

### 1. Iniciar Servidor
```bash
# Windows: Duplo clique ou execute:
backend\START.bat

# Ou manualmente:
cd backend
php -S 127.0.0.1:8000 -t public
```

### 2. Acessar Sistema
- **Homepage Pública:** `http://127.0.0.1:8000/`
- **Área do Corretor:** `http://127.0.0.1:8000/app/`
- **Portal do Cliente:** `http://127.0.0.1:8000/portal/`

### 3. Login
**Corretor/Admin:**
- Email: `admin@exclusiva.com`
- Senha: `password`

**Cliente:**
- Criar conta via Google OAuth na homepage
- Ou usar email/senha (se já tiver cadastro)

## 📁 Estrutura do Projeto

```
socimob/
├── backend/                    # Backend Lumen + Frontend HTML
│   ├── app/                    # Código PHP
│   │   └── Http/Controllers/
│   │       ├── AuthController.php       # Login + Google OAuth
│   │       └── PortalController.php     # API para clientes
│   ├── public/                 # Frontend público
│   │   ├── index.html          # 🆕 Homepage com login Google
│   │   ├── app/                # Área administrativa
│   │   └── portal/             # 🆕 Portal do cliente
│   ├── routes/web.php          # Rotas da API
│   └── START.bat               # Script de inicialização
├── docker/                     # Configurações Docker
└── docs/                       # Documentação técnica
```

## ✨ Funcionalidades

### 🏠 Homepage Pública (`/`)
- Login com Google OAuth (criar conta automaticamente)
- Login com Email/Senha para clientes
- Design moderno com gradiente
- Redirecionamento automático por role

### 👤 Portal do Cliente (`/portal/`)
- Catálogo de Imóveis com grid responsivo
- Filtros avançados (tipo, finalidade, localização)
- Detalhes completos em modal
- Botão "Tenho Interesse" para contato
- Compartilhamento de imóveis

### 💼 Área Administrativa (`/app/`)
- Dashboard com estatísticas
- Gestão de Leads e Imóveis
- Sistema de conversas (chat)
- Configurações completas

## 🔐 Autenticação

### Tipos de Usuário
- **Cliente** → Acessa `/portal/`, vê catálogo, demonstra interesse
- **Corretor** → Acessa `/app/`, gerencia leads e imóveis
- **Admin/Super Admin** → Acesso total ao sistema

### Google OAuth - Configuração

1. **Google Cloud Console:**
   - Criar projeto em [console.cloud.google.com](https://console.cloud.google.com/)
   - Ativar "Google Sign-In API"
   - Criar credenciais OAuth 2.0

2. **Configurar Client ID:**
   ```env
   # backend/.env
   GOOGLE_CLIENT_ID=seu-client-id.apps.googleusercontent.com
   ```

3. **Atualizar HTML:**
   ```html
   <!-- backend/public/index.html -->
   <div id="g_id_onload" data-client_id="seu-client-id...">
   ```

## 📋 API Endpoints

### Autenticação
- `POST /api/auth/login` - Login email/senha
- `POST /api/auth/google` - Login Google OAuth
- `GET /api/auth/me` - Dados do usuário

### Portal Cliente
- `GET /api/portal/properties` - Listar imóveis
- `POST /api/portal/interesse` - Registrar interesse

## 🛠️ Tecnologias

- **Backend:** Lumen 10 (PHP 8.1+)
- **Frontend:** HTML5 + jQuery 3.7.1 + TailwindCSS
- **Banco:** MySQL
- **Auth:** Google OAuth + Token Bearer

## 🔒 Segurança (IMPORTANTE)

⚠️ **Antes de produção:**
1. Implementar verificação REAL do token Google
2. Configurar HTTPS obrigatório
3. Adicionar rate limiting
4. Configurar CORS adequadamente

O código atual tem verificação **simulada** do Google. Ver comentários em `AuthController::googleLogin()` para implementação real.

## 📦 Deploy

### Local
```bash
cd backend
php -S 127.0.0.1:8000 -t public
```

### Produção
1. Copiar `backend/` para servidor
2. Configurar `.env`
3. Executar `composer install --no-dev`
4. Apontar domínio para `public/`
5. Configurar SSL

### Docker
```bash
docker-compose -f docker/docker-compose.yml up -d
```

## 📚 Documentação

- [SERVIDOR_UNICO.md](SERVIDOR_UNICO.md) - Guia do servidor único
- [CONSOLIDACAO_COMPLETA.md](CONSOLIDACAO_COMPLETA.md) - Histórico de mudanças
- [TESTE_RAPIDO.md](TESTE_RAPIDO.md) - Checklist de testes
- [docs/](docs/) - Documentação técnica

## 📞 Suporte

- **GitHub:** [marcuslimadev/socimob](https://github.com/marcuslimadev/socimob)
- **Issues:** Use GitHub Issues para reportar problemas

---

**SOCIMOB v2.0** - Servidor Único + Google OAuth  
Desenvolvido com ❤️ - Dezembro 2024
