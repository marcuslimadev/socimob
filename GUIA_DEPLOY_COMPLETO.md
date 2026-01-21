# 🚀 GUIA DE DEPLOY - React 19 + Lumen

## ✅ STATUS
- ✅ **Build compilado** (dist/public/index.html gerado)
- ✅ **Git atualizado** (commit d42388a enviado)
- ✅ **Script de deploy atualizado** (deploy.sh com build do React)

## 📦 O QUE FOI DEPLOYADO

### Frontend React 19
- 173 arquivos adicionados
- React 19 + TypeScript + Vite 7
- TailwindCSS 4 + 40+ componentes Radix UI
- Build otimizado: 922KB JavaScript, 139KB CSS (gzipped: 262KB + 20KB)
- Páginas: Dashboard, Leads (Kanban), Imóveis, Chat, Portal Cliente

### Backend Lumen
- Proxy Vite configurado (/api → backend PHP)
- API client com auth interceptors
- Multi-tenancy mantido (ResolveTenant middleware)
- Template Ethereal no portal público

### Commits
```
d42388a - chore: Atualizar script de deploy com build do frontend React
0aec6f6 - feat: Integração completa React 19 + Lumen backend
```

---

## 🔐 DEPLOY VIA SSH (MANUAL)

### Passo 1: Conectar ao servidor
```bash
ssh u738323131@145.223.105.168 -p 65002
```
**Senha**: [Use a senha do painel de controle Hostinger]

### Passo 2: Executar deploy
```bash
cd ~/domains/lojadaesquina.store/public_html
bash deploy.sh
```

O script automaticamente vai:
1. ✅ Git pull origin master
2. ✅ Instalar pnpm (se necessário)
3. ✅ Instalar dependências Node (pnpm install)
4. ✅ **BUILD DO FRONTEND REACT** (pnpm build)
5. ✅ Recarregar autoload PHP
6. ✅ Ajustar permissões

---

## 🌐 DEPLOY VIA PAINEL HOSTINGER

### Opção 1: Terminal Web (RECOMENDADO)
1. Acesse: https://hpanel.hostinger.com
2. Websites → lojadaesquina.store → Terminal
3. Cole e execute:
```bash
cd ~/domains/lojadaesquina.store/public_html
bash deploy.sh
```

### Opção 2: File Manager + Cron Job
1. File Manager → `public_html/`
2. Botão direito em `deploy.sh` → Execute
3. Ou criar Cron Job:
   - Comando: `cd ~/domains/lojadaesquina.store/public_html && bash deploy.sh`
   - Execução: Manual ou agendada

---

## 🧪 TESTAR APÓS DEPLOY

### 1. Verificar API
```bash
curl https://lojadaesquina.store/api/health
```
**Esperado**: `{"status":"ok","message":"API funcionando"}`

### 2. Verificar Build React
```bash
ls -lh ~/domains/lojadaesquina.store/public_html/dist/public/index.html
```
**Esperado**: Arquivo de ~368KB

### 3. Testar Frontend
- **Admin React**: https://lojadaesquina.store (deve carregar React app)
- **Portal Ethereal**: https://lojadaesquina.store/portal/
- **API Docs**: https://lojadaesquina.store/api/health

### 4. Verificar logs
```bash
tail -f ~/domains/lojadaesquina.store/public_html/storage/logs/lumen-$(date +%Y-%m-%d).log
```

---

## 🔧 TROUBLESHOOTING

### Build do React falhou
```bash
# Verificar Node/pnpm
node --version  # Precisa 18+
pnpm --version  # Precisa 8+

# Instalar pnpm se não tiver
npm install -g pnpm

# Build manual
cd ~/domains/lojadaesquina.store/public_html
pnpm install --frozen-lockfile
pnpm build
```

### Git pull falhou
```bash
cd ~/domains/lojadaesquina.store/public_html
git status
git reset --hard origin/master
git pull origin master
```

### Permissões incorretas
```bash
cd ~/domains/lojadaesquina.store/public_html
chmod -R 755 storage bootstrap/cache public
chmod -R 775 storage
```

### Limpar cache
```bash
rm -rf storage/framework/cache/*
rm -rf storage/framework/views/*
```

---

## 📊 ESTRUTURA PÓS-DEPLOY

```
public_html/
├── client/              # Código React (dev)
│   ├── src/
│   └── index.html
├── dist/                # Build de produção
│   ├── public/          # Arquivos compilados
│   │   ├── index.html   # Frontend React (368KB)
│   │   └── assets/      # JS + CSS otimizados
│   └── index.js         # Server opcional
├── public/              # Assets PHP/Lumen
│   ├── app/             # Admin HTML antigo (backup)
│   └── portal/          # Portal Ethereal
├── app/                 # Backend Lumen
├── routes/              # Rotas API
└── .env                 # Config produção
```

---

## 🎯 PRÓXIMOS PASSOS

### 1. Configurar servidor web
Certifique-se que o `.htaccess` ou nginx aponta para:
- **Root**: `public_html/public/` (backend Lumen)
- **Frontend React**: `public_html/dist/public/` (build estático)

### 2. Integrar autenticação
- Conectar Login.tsx → POST /api/auth/login
- Implementar proteção de rotas no React

### 3. Conectar dados reais
- Dashboard → GET /api/admin/dashboard/metrics
- Leads → GET /api/leads
- Imóveis → GET /api/imoveis

### 4. Testes E2E
- Login → Dashboard → Leads → Chat
- Portal público → Listar imóveis → Ver detalhes

---

## 📞 SUPORTE

**Repositório**: https://github.com/marcuslimadev/socimob
**Último commit**: d42388a
**Data**: 21/01/2026

Se encontrar problemas, verifique os logs em:
`storage/logs/lumen-YYYY-MM-DD.log`
