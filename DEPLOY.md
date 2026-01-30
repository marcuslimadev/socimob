# 🚀 Deploy Automático - SOCIMOB v2

Scripts para build, commit e deploy automático do frontend e backend.

## 📋 Pré-requisitos

- **Git** instalado e configurado
- **Node.js** e **npm** instalados
- **plink** (PuTTY) ou **ssh** para conexão com servidor

### Instalar plink (Windows)

1. Baixe PuTTY: https://www.putty.org/
2. Adicione a pasta do PuTTY ao PATH do sistema
3. Ou copie `plink.exe` para `C:\Windows\System32\`

## 🎯 Scripts Disponíveis

### 1. `deploy.ps1` (PowerShell - Recomendado)

Script PowerShell completo com interface colorida e verificação de erros.

**Uso:**
```powershell
# Deploy com mensagem padrão
.\deploy.ps1

# Deploy com mensagem customizada
.\deploy.ps1 "feat: adicionar novo módulo de relatórios"
```

**Permissões:**
Se receber erro de execução, rode uma vez:
```powershell
Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned
```

### 2. `deploy.cmd` (CMD - Alternativa)

Script CMD tradicional para quem não pode executar PowerShell.

**Uso:**
```cmd
# Deploy com mensagem padrão
deploy.cmd

# Deploy com mensagem customizada
deploy.cmd "feat: adicionar novo módulo de relatórios"
```

Basta dar duplo clique ou executar no terminal.

## ⚙️ O que os scripts fazem

1. **🔨 Build do Frontend**
   - Executa `npm run build` no diretório `client/`
   - Gera arquivos otimizados em `dist/public/`
   - Verifica se o build foi bem-sucedido

2. **📦 Commit e Push**
   - Adiciona `dist/public/` ao staging
   - Cria commit com mensagem informada
   - Faz push para `origin/master`

3. **🌐 Deploy no Servidor SSH**
   - Conecta ao servidor via SSH
   - Executa `git pull origin master`
   - Copia build do `dist/public/*` para `public/`
   - Verifica arquivos copiados

4. **✅ Verificação**
   - Testa endpoint `/api/health`
   - Exibe status da aplicação

## 📂 Estrutura de Deploy

```
Local                           Servidor SSH
├── client/                     ~/domains/lojadaesquina.store/public_html/
│   └── npm run build           ├── dist/public/
│       └── dist/public/        │   ├── index.html
│           ├── index.html      │   └── assets/
│           └── assets/         │       ├── *.js
│                               │       └── *.css
├── git add dist/public/        └── public/
├── git commit                      ├── index.html (← copiado de dist/)
├── git push origin master          └── assets/ (← copiado de dist/)
│
└── SSH Deploy →
    cd ~/domains/.../public_html
    git pull origin master
    cp -rf dist/public/* public/
```

## 🔐 Configurações SSH

**Servidor:** 145.223.105.168:65002
**Usuário:** u815655858
**Caminho:** ~/domains/lojadaesquina.store/public_html

As credenciais estão hardcoded nos scripts. Para alterar, edite as variáveis no início do arquivo.

## 🐛 Troubleshooting

### "plink não encontrado"

Instale PuTTY ou use SSH nativo do Windows:
```powershell
# Windows 10/11 já vem com OpenSSH
winget install Microsoft.OpenSSH
```

### "Build falhou"

Verifique se as dependências estão instaladas:
```bash
cd client
npm install
npm run build
```

### "Push falhou"

Verifique se tem permissão para push:
```bash
git remote -v
git pull origin master
```

### "Connection timed out"

O servidor pode estar temporariamente indisponível. Aguarde alguns minutos e tente novamente.

### Deploy manual (fallback)

Se os scripts falharem, execute manualmente:

```bash
# 1. Local
cd client
npm run build
cd ..
git add dist/public
git commit -m "build: update frontend"
git push origin master

# 2. No servidor SSH
ssh -p 65002 u815655858@145.223.105.168
cd ~/domains/lojadaesquina.store/public_html
git pull origin master
cp -rf dist/public/* public/
```

## 🎨 Exemplo de Output

```
╔════════════════════════════════════════════════════════════╗
║          DEPLOY AUTOMÁTICO - SOCIMOB v2                    ║
╚════════════════════════════════════════════════════════════╝

=== BUILD DO FRONTEND REACT ===
✓ Build do frontend concluído

=== VERIFICAR MUDANÇAS NO BUILD ===
✓ Mudanças detectadas no build:
 M dist/public/index.html
 A dist/public/assets/index-D3a6yRZM.css
 A dist/public/assets/index-oxQ-bwAz.js

=== COMMIT E PUSH ===
✓ Commit criado com sucesso
✓ Push para origin/master concluído

=== DEPLOY NO SERVIDOR SSH ===
Servidor: u815655858@145.223.105.168:65002
Caminho: ~/domains/lojadaesquina.store/public_html

=== GIT PULL ===
Already up to date.

=== COPIAR BUILD ===
✓ Build React copiado para public/

=== VERIFICAR BUILD ===
-rw-r--r-- 1 u815655858 o72837214 360K Jan 29 10:50 public/index.html

=== DEPLOY CONCLUÍDO ===
✓ Deploy SSH concluído com sucesso

=== VERIFICAR SITE ONLINE ===
✓ API Health: online
  App: SOCIMOB
  Version: Lumen (10.0.4)

╔════════════════════════════════════════════════════════════╗
║              DEPLOY CONCLUÍDO COM SUCESSO!                 ║
╚════════════════════════════════════════════════════════════╝

🌐 Frontend: https://lojadaesquina.store
🔌 API:      https://lojadaesquina.store/api/health
```

## 📝 Notas

- Os scripts fazem commit automático do build
- O build é commitado no branch `master`
- Certifique-se de ter as mudanças locais commitadas antes
- O deploy sobrescreve arquivos no servidor (usa `cp -rf`)

## 🔗 Links Úteis

- Frontend: https://lojadaesquina.store
- API Health: https://lojadaesquina.store/api/health
- hPanel (Hostinger): https://hpanel.hostinger.com/
- Repositório: https://github.com/marcuslimadev/socimob

---

## ✅ Status Atual do Deploy

### Última Atualização: 30/01/2026 - 19:41 UTC

**Frontend Build:**
- ✅ React 19 com Vite funcionando
- ✅ Build otimizado em `dist/public/`
- ✅ Assets: `index-D2oTfvb9.css` (154KB), `index-Ube58Nnf.js` (1.3MB)
- ✅ Deploy automático via `deploy.ps1 -Force`

**Backend (PHP/Lumen):**
- ✅ API rodando em produção
- ✅ LeadAutomationService corrigido (Tenant 1 força envio de template exato)
- ✅ SystemLogsController implementado para admin
- ✅ Rotas `/api/admin/system-logs` ativas

**Funcionalidades Recentes:**
- ✅ Painel de Logs do Sistema (acesso admin/super_admin)
- ✅ Correção do envio de WhatsApp com texto exato de template "Boas Vindas"
- ✅ Bypass de verificação de `ContentSid` para Tenant 1
- ✅ Proteção de autenticação em todas as rotas admin

**Servidor de Produção:**
- Host: 145.223.105.168:65002
- Path: ~/domains/lojadaesquina.store/public_html
- Status: ✅ Online e funcional
- Último deploy: 30/01/2026 19:41 UTC

**Próximos Passos:**
- Testar envio de WhatsApp em produção com novo lead
- Validar logs no painel `/system-logs`
- Monitorar erros no SystemLog

---

## 🎯 Checklist de Deploy Completo

- [x] Build do frontend React sem erros
- [x] Commit e push para GitHub
- [x] Deploy SSH no servidor Hostinger
- [x] Verificação de arquivos copiados
- [x] API health check funcionando
- [x] Backend com correções aplicadas
- [x] Logs do sistema implementados
- [x] Proteção de rotas admin ativa
- [x] WhatsApp template fix deployado
