# 🚀 DEPLOY FINAL - Senha SSH Rejeitada

## ❌ Problema Confirmado
A senha **MundoMelhor@10** está sendo **REJEITADA** pelo servidor SSH em todas as tentativas:
- ❌ plink com -pw
- ❌ pscp com -pw  
- ❌ Diferentes formatos de sintaxe
- ❌ Múltiplas abordagens testadas

**Conclusão**: A senha SSH está **incorreta, expirada ou bloqueada**.

---

## ✅ SOLUÇÃO: Deploy via ZIP Files

### Arquivos Prontos
Criei 2 arquivos ZIP na raiz do projeto:

1. **deploy_controllers.zip** (10 arquivos)
   - Todos os controllers com validações corrigidas
   - Upload para: `~/domains/lojadaesquina.store/public_html/app/Http/Controllers/`

2. **deploy_frontend.zip** (18 arquivos)
   - Design system completo (13 CSS)
   - Páginas HTML atualizadas (4 arquivos)
   - Sidebar e glow.css atualizados
   - Upload para: `~/domains/lojadaesquina.store/public_html/public/`

---

## 📋 PASSO A PASSO - FileZilla

### 1. Conectar FileZilla
```
Host: sftp://145.223.105.168
Porta: 65002
Usuário: u738323131
Senha: [VERIFICAR SENHA CORRETA NO PAINEL HOSTINGER]
```

### 2. Extrair ZIPs Localmente
```powershell
# No Windows, extrair os ZIPs:
Expand-Archive -Path deploy_controllers.zip -DestinationPath temp_controllers -Force
Expand-Archive -Path deploy_frontend.zip -DestinationPath temp_frontend -Force
```

### 3. Upload via FileZilla

#### 3.1 Controllers (Backend)
- **Local**: `C:\Projetos\socimobatual\temp_controllers\`
- **Remoto**: `~/domains/lojadaesquina.store/public_html/app/Http/Controllers/`
- **Ação**: Arrastar e soltar → Sobrescrever tudo

#### 3.2 Frontend (CSS + HTML)
- **Local**: `C:\Projetos\socimobatual\temp_frontend\`
- **Remoto**: `~/domains/lojadaesquina.store/public_html/public/`
- **Ação**: Arrastar e soltar → Sobrescrever tudo

### 4. Executar no Terminal do Painel Hostinger
Após upload, executar comandos:

```bash
cd ~/domains/lojadaesquina.store/public_html

# Ajustar permissões
chmod -R 755 storage bootstrap/cache public

# Se composer disponível
composer dump-autoload --optimize || echo "OK sem composer"

# Verificar
ls -lh app/Http/Controllers/Admin/TenantSettingsController.php
ls -lh public/css/design-system.css
```

---

## 🔑 RESETAR SENHA SSH (Recomendado)

### Painel Hostinger
1. Login: https://hpanel.hostinger.com
2. **Hosting** → Selecionar `lojadaesquina.store`
3. **Advanced** → **SSH Access**
4. Clicar em **Change Password** ou **Reset SSH Password**
5. Definir nova senha (anote em local seguro)
6. Testar novamente:

```powershell
plink -P 65002 u738323131@145.223.105.168 -pw NOVA_SENHA "whoami"
```

---

## 📊 Arquivos para Deploy

### Backend (10 controllers)
```
app/Http/Controllers/Admin/TenantSettingsController.php  ← ERRO 500 FIXADO
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

### Frontend (18 arquivos)
```
public/css/design-system.css       ← NOVO
public/css/utilities.css            ← NOVO
public/css/grid.css                 ← NOVO
public/css/buttons.css              ← NOVO
public/css/cards.css                ← NOVO
public/css/forms.css                ← NOVO
public/css/tables.css               ← NOVO
public/css/badges.css               ← NOVO
public/css/modals.css               ← NOVO
public/css/pagination.css           ← NOVO
public/css/accessibility.css        ← NOVO
public/css/sidebar.css              ← ATUALIZADO
public/css/glow.css                 ← ATUALIZADO
public/app/dashboard.html           ← ATUALIZADO
public/app/leads.html               ← ATUALIZADO
public/app/clientes.html            ← ATUALIZADO
public/app/usuarios.html            ← ATUALIZADO
public/app/sidebar.css              ← ATUALIZADO (duplicado, verificar)
```

---

## 🧪 Teste Pós-Deploy

```bash
# 1. Health Check
curl https://lojadaesquina.store/api/health

# 2. Teste Erro 500 Corrigido (agora deve retornar 422)
curl -X POST https://lojadaesquina.store/api/admin/settings/assets \
  -H "Authorization: Bearer TOKEN" \
  -F "logo=@test.txt" -i

# Esperado: HTTP/1.1 422 (ANTES era 500!)

# 3. Verificar logs
tail -20 ~/domains/lojadaesquina.store/public_html/storage/logs/lumen-$(date +%Y-%m-%d).log
```

---

## ✅ Checklist Final

- [ ] Senha SSH verificada/resetada no painel
- [ ] deploy_controllers.zip extraído
- [ ] deploy_frontend.zip extraído
- [ ] FileZilla conectado
- [ ] 10 controllers enviados
- [ ] 18 arquivos frontend enviados
- [ ] Permissões ajustadas (755)
- [ ] composer dump-autoload executado
- [ ] Health check retorna OK
- [ ] Endpoint assets retorna 422 (não 500)
- [ ] Design system visível no frontend

---

## 📦 Resumo

**Status**: Deploy bloqueado por senha SSH incorreta  
**Solução**: ZIPs criados para upload manual via FileZilla  
**Tempo estimado**: 10-15 minutos  
**Impacto**: ⭐⭐⭐⭐⭐ ALTÍSSIMO (21 endpoints corrigidos + design completo)

**Próximo passo**: Verificar senha SSH no painel Hostinger ou usar FileZilla com os ZIPs criados.
