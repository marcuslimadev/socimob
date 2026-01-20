# 🚀 Deploy Imediato - Produção

**Data**: 20/01/2026
**Servidor**: lojadaesquina.store (145.223.105.168:65002)
**Commits**: 5 commits (403c5e7 → cd23e25)

## ✅ O que será deployado

### 1. Correções Críticas (ERRO 500 RESOLVIDO)
- **21 validações corrigidas** em 12 controllers
- **Endpoints críticos fixados**:
  - `/api/admin/settings/assets` (upload logo/favicon) - ERROR 500 RESOLVIDO ✅
  - `/api/portal/login` - Login portal público
  - `/api/leads` (update, updateState, updateStatus, bulkDestroy)
  - `/api/conversas` (sendMessage, bulkDestroy)
  - `/api/portal/chat/send` - Chat do portal
  
### 2. Documentação
- `CORRECOES_VALIDACAO_COMPLETAS.md` - Status de todas as correções
- `GUIA_MELHORIAS_VISUAIS_COMPLETO.md` - Roadmap de melhorias visuais (2.228 linhas)

### 3. Arquivos Modificados
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
CORRECOES_VALIDACAO_COMPLETAS.md
GUIA_MELHORIAS_VISUAIS_COMPLETO.md
```

## 🔧 Comandos de Deploy

### Opção 1: Deploy Automático via SSH
```bash
ssh u738323131@145.223.105.168 -p 65002 << 'EOF'
cd ~/domains/lojadaesquina.store/public_html
git pull origin master
composer dump-autoload
php artisan config:cache 2>/dev/null || echo "Lumen não tem artisan, OK!"
chmod -R 755 storage bootstrap/cache
EOF
```

### Opção 2: Deploy Manual (se SSH falhar)

#### 2.1 Conectar via SSH
```bash
ssh u738323131@145.223.105.168 -p 65002
```

#### 2.2 Executar comandos
```bash
cd ~/domains/lojadaesquina.store/public_html

# Fazer backup antes
cp -r app app_backup_$(date +%Y%m%d_%H%M%S)

# Puxar atualizações
git pull origin master

# Recarregar autoload
composer dump-autoload

# Ajustar permissões
chmod -R 755 storage bootstrap/cache
```

### Opção 3: Deploy via FileZilla (Emergência)
Se SSH não funcionar, usar FileZilla:
1. Host: `145.223.105.168`
2. Porta: `65002`
3. Usuário: `u738323131`
4. Copiar manualmente:
   - `app/Http/Controllers/` → sobrescrever 10 arquivos

## 🧪 Testes Pós-Deploy

### 1. Teste do Erro 500 Fixado
```bash
# Gerar token
TOKEN=$(php -r "echo base64_encode('1|' . time() . '|' . hash('sha256', '1' . time() . '8qkHvKr9j5PcWqA3xZyF2mN7bV1dS0gT'));")

# Testar upload de logo (ANTES dava erro 500)
curl -X POST https://lojadaesquina.store/api/admin/settings/assets \
  -H "Authorization: Bearer $TOKEN" \
  -F "logo=@README.md"
```

**Resultado esperado**: `422 Unprocessable Entity` com mensagem de validação (não mais 500!)

### 2. Teste de Login Portal
```bash
curl -X POST https://lojadaesquina.store/api/portal/login \
  -H "Content-Type: application/json" \
  -d '{"email":"cliente@teste.com","password":"wrong"}'
```

**Resultado esperado**: `422` com erro de validação (não mais 500!)

### 3. Teste de Atualização de Lead
```bash
curl -X PUT https://lojadaesquina.store/api/leads/1 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"nome":"João Silva"}'
```

**Resultado esperado**: `200 OK` ou `422` com validação clara

### 4. Health Check Geral
```bash
curl https://lojadaesquina.store/api/health
```

**Resultado esperado**:
```json
{
  "status": "ok",
  "database": "connected"
}
```

## 📈 Impacto Esperado

### Antes do Deploy
- ❌ 21 endpoints retornando **error 500** em validações
- ❌ Upload de logo/favicon **quebrado**
- ❌ Login do portal **instável**
- ❌ Validações sem mensagens claras

### Depois do Deploy
- ✅ 21 endpoints retornando **HTTP 422** com mensagens claras
- ✅ Upload de logo/favicon **funcional** (rejeita com erro claro)
- ✅ Login do portal **estável** com feedback de erros
- ✅ Todas as validações com mensagens estruturadas

## 🔄 Rollback (se necessário)

Se algo der errado:
```bash
cd ~/domains/lojadaesquina.store/public_html

# Voltar para commit anterior
git reset --hard 403c5e7^  # Volta 1 commit antes das correções

# OU restaurar backup
rm -rf app
cp -r app_backup_YYYYMMDD_HHMMSS app

# Recarregar
composer dump-autoload
```

## 📝 Checklist de Deploy

- [ ] Git push completado
- [ ] SSH conectado com sucesso
- [ ] Backup criado (app_backup_*)
- [ ] `git pull` executado
- [ ] `composer dump-autoload` executado
- [ ] Permissões ajustadas (755)
- [ ] Teste 1: Upload de assets (ANTES error 500)
- [ ] Teste 2: Login portal (ANTES error 500)
- [ ] Teste 3: Update lead funcional
- [ ] Teste 4: Health check OK
- [ ] Verificar logs: `tail -f storage/logs/lumen-*.log`

## 🚨 Logs em Tempo Real

Monitorar erros durante deploy:
```bash
# No servidor
tail -f ~/domains/lojadaesquina.store/public_html/storage/logs/lumen-$(date +%Y-%m-%d).log
```

## 📞 Suporte

Se encontrar problemas:
1. Verificar logs: `storage/logs/lumen-YYYY-MM-DD.log`
2. Verificar permissões: `ls -la storage bootstrap/cache`
3. Testar localmente primeiro: `http://127.0.0.1:8000`
4. Rollback se necessário (ver seção acima)

---

**READY TO DEPLOY** ✅
