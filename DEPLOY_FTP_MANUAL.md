# 🚀 Deploy Manual Simplificado - Senha SSH Não Aceita

## ⚠️ Problema Identificado
A senha SSH `MundoMelhor@10` não está sendo aceita via plink/pscp.

## ✅ Solução: Deploy Manual via FTP/FileZilla

### Opção 1: FileZilla (RECOMENDADO)

#### 1.1 Configuração FileZilla
```
Host: 145.223.105.168
Porta: 65002
Protocolo: SFTP - SSH File Transfer Protocol
Tipo de Login: Normal
Usuário: u738323131
Senha: [Inserir senha no FileZilla]
```

#### 1.2 Arquivos para Upload
Navegue até: `~/domains/lojadaesquina.store/public_html/app/Http/Controllers/`

**Enviar estes 10 arquivos** (sobrescrever):
```
app/Http/Controllers/Admin/TenantSettingsController.php
app/Http/Controllers/PortalController.php
app/Http/Controllers/LeadsController.php
app/Http/Controllers/LeadDocumentsController.php
app/Http/Controllers/ConversasController.php
app/Http/Controllers/Portal/ChatController.php
app/Http/Controllers/Portal/PortalController.php
app/Http/Controllers/Portal/ProfileController.php
app/Http/Controllers/Portal/VisitasController.php
app/Http/Controllers/Portal/ClientAuthController.php
```

**Enviar também** (novos arquivos CSS):
```
public/css/design-system.css
public/css/utilities.css
public/css/grid.css
public/css/buttons.css
public/css/cards.css
public/css/forms.css
public/css/tables.css
public/css/badges.css
public/css/modals.css
public/css/pagination.css
public/css/accessibility.css
```

**Atualizar** (arquivos modificados):
```
public/css/sidebar.css
public/css/glow.css
public/app/dashboard.html
public/app/leads.html
public/app/clientes.html
public/app/usuarios.html
```

---

### Opção 2: Git Pull Direto (via Painel Hostinger)

#### 2.1 Acessar Painel Hostinger
1. Login: https://hpanel.hostinger.com
2. Ir em **Files** → **File Manager**
3. Navegar até: `domains/lojadaesquina.store/public_html`

#### 2.2 Abrir Terminal no Painel
1. Clicar em **Terminal** (ícone no topo)
2. Executar comandos:

```bash
cd ~/domains/lojadaesquina.store/public_html

# Verificar status
git status
git log --oneline -3

# Puxar atualizações
git pull origin master

# Recarregar autoload (se composer disponível)
composer dump-autoload --optimize

# Ajustar permissões
chmod -R 755 storage bootstrap/cache public

# Verificar deploy
ls -la app/Http/Controllers/Admin/TenantSettingsController.php
```

---

### Opção 3: WinSCP

#### 3.1 Configuração WinSCP
```
Protocolo: SFTP
Host: 145.223.105.168
Porta: 65002
Usuário: u738323131
Senha: [Inserir senha]
```

#### 3.2 Sincronização
1. Conectar ao servidor
2. Navegar local: `C:\Projetos\socimobatual\`
3. Navegar remoto: `~/domains/lojadaesquina.store/public_html/`
4. Selecionar pastas:
   - `app/Http/Controllers/`
   - `public/css/`
   - `public/app/`
5. Clicar em **Upload** → **Sobrescrever**

---

## 🧪 Teste Pós-Deploy

Após upload dos arquivos, testar endpoints:

### 1. Health Check
```bash
curl https://lojadaesquina.store/api/health
```

**Esperado**: `{"status":"ok","database":"connected"}`

### 2. Teste Endpoint Corrigido (ANTES error 500)
```bash
# No navegador ou Postman
POST https://lojadaesquina.store/api/admin/settings/assets
Header: Authorization: Bearer [token]
Body: form-data com arquivo qualquer
```

**Resultado esperado**: `HTTP 422` (não mais 500!)

### 3. Verificar Logs
Via painel Hostinger ou FileZilla, abrir:
```
storage/logs/lumen-2026-01-20.log
```

**Procurar**: Não deve haver linhas com "500" recentes

---

## 📋 Checklist de Deploy Manual

- [ ] Conectado via FileZilla/WinSCP/Painel
- [ ] 10 controllers enviados (app/Http/Controllers/)
- [ ] 11 arquivos CSS enviados (public/css/)
- [ ] 4 arquivos HTML atualizados (public/app/)
- [ ] Permissões ajustadas (755)
- [ ] Health check OK
- [ ] Teste upload assets retorna 422
- [ ] Logs sem erro 500
- [ ] Site acessível

---

## 🔄 Alternativa: Resetar Senha SSH

Se precisar resetar a senha SSH:

1. **Via Painel Hostinger**:
   - Login → **Hosting** → **Advanced** → **SSH Access**
   - Clicar em **Reset Password**
   - Definir nova senha
   - Tentar novamente com plink

2. **Comando plink atualizado**:
```powershell
plink -ssh -P 65002 -batch u738323131@145.223.105.168 -pw NOVA_SENHA "cd ~/domains/lojadaesquina.store/public_html && git pull origin master"
```

---

## 📞 Status Atual

✅ **Git Push Completo** - 6 commits no GitHub  
❌ **SSH via plink** - Senha não aceita  
⏳ **Deploy Pendente** - Aguardando upload manual  

**Tempo estimado**: 10-15 minutos (FileZilla/WinSCP)  
**Arquivos**: 31 arquivos para enviar

---

**PRÓXIMO PASSO**: Usar FileZilla ou painel Hostinger para completar deploy
