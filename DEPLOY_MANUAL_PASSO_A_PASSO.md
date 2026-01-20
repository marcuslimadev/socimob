# 🚀 Deploy Manual - Passo a Passo

## Instruções para Executar no Terminal SSH já Aberto

Você mencionou que **o SSH já está aberto**. Cole estes comandos diretamente no terminal:

### 1️⃣ Navegar para o diretório do projeto
```bash
cd ~/domains/lojadaesquina.store/public_html
pwd
```

### 2️⃣ Remover arquivo de migração conflitante
```bash
rm -f database/migrations/2026_02_01_000001_add_stage_to_conversas_table.php
ls -lh database/migrations/ | tail -5
```

### 3️⃣ Fazer git pull
```bash
git pull
```

### 4️⃣ Executar migrações
```bash
/opt/alt/php83/usr/bin/php artisan migrate --force
```

### 5️⃣ Limpar cache
```bash
/opt/alt/php83/usr/bin/php artisan cache:clear
/opt/alt/php83/usr/bin/php artisan config:clear
/opt/alt/php83/usr/bin/php artisan route:clear
```

### 6️⃣ Ajustar permissões
```bash
chmod -R 775 storage bootstrap/cache
chown -R $(whoami):$(whoami) storage bootstrap/cache
```

### 7️⃣ Testar API
```bash
curl -s https://lojadaesquina.store/api/health
```

## ⚠️ Erros Conhecidos e Soluções

### Erro 500 em /api/admin/settings/assets
**Causa**: Lumen não suporta `$this->validate()` nativamente
**Correção**: Substituído por `Validator::make()` no TenantSettingsController
**Status**: ✅ CORRIGIDO localmente, pendente push para produção

### Git Conflict
**Arquivo**: `database/migrations/2026_02_01_000001_add_stage_to_conversas_table.php`
**Solução**: Passo 2 acima remove o arquivo antes do git pull

## 📊 Melhorias Implementadas (Pendentes Deploy)

1. ✅ Validações com `Validator::make()` em todos os controllers
2. ✅ Eager loading em ConversasController (N+1 prevention)
3. ✅ Rate limiting (5 tentativas/minuto) em rotas de login
4. ✅ Timeout OpenAI reduzido para 30s
5. ✅ Loading states no frontend (loading.js + loading.css)
6. ✅ Postman collection com 20+ endpoints
7. ✅ Diretório `public/uploads/` criado

## 🔍 Verificações Pós-Deploy

Execute após o deploy:

```bash
# Verificar logs de erro
tail -50 storage/logs/lumen-$(date +%Y-%m-%d).log

# Testar endpoints críticos
curl -s https://lojadaesquina.store/api/health | jq
curl -s https://lojadaesquina.store/app/login.html -I | head -5

# Verificar permissões
ls -la storage/ | grep -E "logs|framework|uploads"
```

## 🆘 Se der erro no git pull

Se aparecer erro de merge conflict:

```bash
# Resetar mudanças locais e forçar pull
git fetch origin
git reset --hard origin/master
```

## 📝 Depois do Deploy

1. Teste upload de logo em https://lojadaesquina.store/app/configuracoes.html
2. Verifique se erro 500 em `/api/admin/settings/assets` foi resolvido
3. Teste login e autenticação
4. Confirme que rate limiting está ativo (5 tentativas/minuto)

---

**Última atualização**: 20/01/2026 09:50
**Commits pendentes**: 1 (403c5e7 - Fix validação TenantSettingsController)
