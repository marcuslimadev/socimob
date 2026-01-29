# 🏠 SOCIMOB - Sistema de Gestão Imobiliária

## 🎯 Visão Geral
SOCIMOB combina um backend Lumen com um frontend HTML/jQuery leve para entregar um SaaS imobiliário pronto para rodar localmente e na Hostinger.

## 🚀 Início Rápido (3 passos)
1. **Iniciar o servidor PHP**
   ```bash
   START.bat      # Windows
   php -S 127.0.0.1:8000 -t public  # alternativo
   ```
2. **Acessar**
   - Homepage: `http://127.0.0.1:8000/`
   - Área do corretor: `http://127.0.0.1:8000/app/`
   - Portal do cliente: `http://127.0.0.1:8000/portal/`
3. **Login**
   - Admin/corretor: `admin@exclusiva.com` / `password`
   - Cliente: cadastre via Google OAuth ou use um cadastro existente

## 📦 Deploy Automático

Para fazer deploy completo (build + commit + push + servidor SSH):

```cmd
# Windows CMD (duplo clique ou terminal)
deploy.cmd

# PowerShell (recomendado)
.\deploy.ps1

# Com mensagem customizada
.\deploy.ps1 "feat: adicionar nova funcionalidade"
```

**O script automatiza:**
- ✅ Build do frontend React (`npm run build`)
- ✅ Commit e push para GitHub
- ✅ Deploy no servidor SSH (pull + copy)
- ✅ Verificação de saúde da API

📖 Ver documentação completa: [DEPLOY.md](DEPLOY.md)

## 📁 Estrutura do projeto
- `app/`, `routes/`, `database/`, `config/` – Lógica PHP, rotas, migrações e configurações
- `public/` – Frontend público, assets e ponto de entrada HTTP
- `bootstrap/`, `artisan` – Bootstrap do Lumen
- `storage/`, `tests/`, `vendor/` – Logs, testes e dependências
- `scripts/` – Utilitários auxiliares
- `.env`, `composer.json`, `composer.lock` – Ambiente e dependências

## ✨ Funcionalidades
- Autenticação com Google OAuth + login por e-mail/senha
- Portal do cliente com catálogo responsivo, filtros e modais detalhados
- Área administrativa com dashboard, leads, imóveis, chats e configurações
- Multi-tenancy com domínios personalizados e isolamento por tenant_id
- Integrações com WhatsApp/Twilio, Pagar.me e OpenAI para automatização
- Sistema de notificações, relatórios e chat em tempo real

## 🔐 Autenticação
- Tipos de usuário: Cliente, Corretor, Admin e Super Admin
- Guardas `auth` + middleware `ResolveTenant`
- Configure `GOOGLE_CLIENT_ID` no `.env` e atualize `public/index.html` para habilitar o login com Google

## 📋 API Endpoints
- `POST /api/auth/login`, `POST /api/auth/google`, `GET /api/auth/me`
- `/api/portal/properties`, `/api/portal/interesse`
- `/webhook/whatsapp`, `/api/webhooks/pagar-me`, `/github/webhook` (webhooks públicos)

## 🛠️ Tecnologias
- Backend: Lumen 10 (PHP 8.1+)
- Frontend: HTML5 + jQuery 3.7.1 + TailwindCSS
- Banco: MySQL
- Auth: Google OAuth + tokens Bearer
- Deploy: GitHub Actions → Hostinger

## 🔒 Segurança (IMPORTANTE)
1. Valide o token Google em produção
2. Configure HTTPS obrigatório
3. Aplique rate limiting e CORS
4. Garanta permissões de escrita em `storage/` e `bootstrap/cache`

## 📦 Deploy
- **Local:** `php -S 127.0.0.1:8000 -t public`
- **Produção:** siga [docs/DEPLOY_HOSTINGER.md](docs/DEPLOY_HOSTINGER.md); o workflow oficial copia o projeto para a Hostinger e roda `composer install --no-dev --prefer-dist`, `php artisan migrate --force` e comandos de cache.

## 📚 Documentação
- [docs/DEPLOY_HOSTINGER.md](docs/DEPLOY_HOSTINGER.md) – Deploy automático na Hostinger
- [SERVIDOR_UNICO.md](SERVIDOR_UNICO.md) – Guia do servidor único
- [CONSOLIDACAO_COMPLETA.md](CONSOLIDACAO_COMPLETA.md) – Histórico de mudanças
- [TESTE_RAPIDO.md](TESTE_RAPIDO.md) – Checklist de testes

## 📞 Suporte
- GitHub: [marcuslimadev/socimob](https://github.com/marcuslimadev/socimob)
- Issues: use o repositório oficial para reportar problemas
