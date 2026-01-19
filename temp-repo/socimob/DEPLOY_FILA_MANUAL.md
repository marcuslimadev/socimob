# 🚀 DEPLOY MANUAL - Sistema de Fila

## ✅ COMMIT E PUSH CONCLUÍDOS

O código foi enviado para o GitHub com sucesso:
- ✅ Commit: `ec492bf` - Sistema de fila completo
- ✅ Commit: `0fc1b1a` - Script de deploy HTTP
- ✅ Push concluído em: January 8, 2026

## 📋 Arquivos Modificados

1. **app/Http/Controllers/Admin/ConversasController.php** (389 linhas modificadas)
   - Métodos: pegarProxima(), devolverParaFila(), estatisticasFila()
   - Lógica FIFO, tenant isolation, logging

2. **public/app/chat.html** (+ interface completa)
   - Botão "Pegar Próximo Cliente"
   - Modal de estatísticas
   - Badges FILA/MINHA
   - Auto-reload e polling

3. **routes/web.php** (3 rotas novas)
   - GET /api/admin/conversas/fila/estatisticas
   - POST /api/admin/conversas/fila/pegar-proxima
   - POST /api/admin/conversas/{id}/devolver-fila

4. **deploy_queue.php** (novo)
   - Script de deploy via HTTP

## 🔧 COMO FAZER DEPLOY NO SERVIDOR

### Opção 1: Via cPanel File Manager

1. Acesse: https://srv1005.hstgr.io:2083
2. Login: u815655858
3. Senha: [sua senha]
4. Vá em "File Manager"
5. Navegue até: `~/domains/lojadaesquina.store/public_html`
6. Clique em "Terminal" (ou use Git Version Control)
7. Execute:
   ```bash
   git pull origin master
   ```

### Opção 2: Via Terminal SSH (se disponível)

```bash
ssh u815655858@srv1005.hstgr.io
cd ~/domains/lojadaesquina.store/public_html
git pull origin master
curl -s "https://lojadaesquina.store/opcache_clear.php" > /dev/null
exit
```

### Opção 3: Via Script HTTP (RECOMENDADO)

Após fazer git pull manualmente uma vez:

1. Acesse: https://lojadaesquina.store/deploy_queue.php?key=exclusiva2025
2. Veja o output do deploy
3. Confirme que os 3 arquivos foram atualizados

## 🧪 COMO TESTAR EM PRODUÇÃO

### 1. Verificar API

```powershell
# Pegar token (substitua pelo token real do admin)
$token = "SEU_TOKEN_AQUI"

# Testar estatísticas
Invoke-RestMethod -Uri "https://lojadaesquina.store/api/admin/conversas/fila/estatisticas" -Headers @{"Authorization"="Bearer $token"}
```

### 2. Testar PWA Chat

1. Abra: https://lojadaesquina.store/app/chat.html
2. Faça login como **corretor** (não admin)
3. Procure o botão verde "Pegar Próximo Cliente da Fila"
4. Clique no botão 📊 para ver estatísticas
5. Se houver conversas na fila, clique em "Pegar Próximo"

### 3. Criar Conversa de Teste

Para testar, você pode:

A) Via API criar uma conversa sem corretor:
```sql
INSERT INTO conversas (tenant_id, lead_id, corretor_id, status, created_at, updated_at)
VALUES (1, 1, NULL, 'ativa', NOW(), NOW());
```

B) Ou usar o endpoint de teste (se criado)

## 📊 FEATURES IMPLEMENTADAS

### Backend
- ✅ FIFO (First In First Out) - ordem de chegada
- ✅ Tenant isolation - cada imobiliária vê só seus dados
- ✅ Role-based access - corretor vs admin
- ✅ Logging completo em system_logs
- ✅ Validações de permissão

### Frontend
- ✅ Botão "Pegar Próximo" com contador
- ✅ Badge mostrando quantidade em fila
- ✅ Modal de estatísticas com breakdown
- ✅ Badges FILA (verde) e MINHA (azul)
- ✅ Nome do corretor nas conversas atribuídas
- ✅ Auto-reload a cada 10s
- ✅ CSS modal overlay

### API Endpoints
- ✅ GET /api/admin/conversas - lista com lógica de fila
- ✅ GET /api/admin/conversas/fila/estatisticas
- ✅ POST /api/admin/conversas/fila/pegar-proxima
- ✅ POST /api/admin/conversas/{id}/devolver-fila

## 🔍 VERIFICAÇÃO PÓS-DEPLOY

Após fazer git pull, verifique:

1. **Arquivo ConversasController.php existe:**
   ```bash
   ls -lh app/Http/Controllers/Admin/ConversasController.php
   ```

2. **Chat.html atualizado:**
   ```bash
   grep -n "Pegar Próximo Cliente" public/app/chat.html
   ```

3. **Rotas registradas:**
   ```bash
   grep -n "fila/estatisticas" routes/web.php
   ```

4. **OPcache limpo:**
   ```bash
   curl https://lojadaesquina.store/opcache_clear.php
   ```

## 📞 PRÓXIMOS PASSOS

Depois de fazer deploy:

1. ✅ Teste login como corretor
2. ✅ Verifique botão "Pegar Próximo" aparece
3. ✅ Clique em stats para ver métricas
4. ✅ Crie conversa de teste sem corretor_id
5. ✅ Pegue da fila e veja conversa abrir
6. ✅ Verifique log em system_logs

## 🆘 TROUBLESHOOTING

**Botão não aparece:**
- Verifique role do usuário: deve ser 'corretor', não 'admin'
- Admin vê todas conversas mas não tem botão de fila

**Erro 404 nas rotas:**
- Verifique se routes/web.php foi atualizado
- Limpe OPcache
- Verifique permissões dos arquivos

**Fila vazia:**
- API retorna 404 quando não há conversas disponíveis
- Crie conversa com corretor_id = NULL
- Verifique tenant_id está correto

**Badges não aparecem:**
- Verifique se chat.html foi atualizado
- Force refresh (Ctrl+F5)
- Limpe cache do navegador

---

**Data do Deploy:** January 8, 2026
**Versão:** Sistema de Fila v1.0
**Commits:** ec492bf, 0fc1b1a
