# 🔧 Resolver Conflito Git - Servidor Produção

**Problema:** Arquivo não rastreado impede merge
```
database/migrations/2026_02_01_000001_add_stage_to_conversas_table.php
```

---

## ✅ SOLUÇÃO (Execute no SSH)

### Opção 1: Backup e Pull (RECOMENDADO)

```bash
# 1. Fazer backup do arquivo conflitante
cp database/migrations/2026_02_01_000001_add_stage_to_conversas_table.php \
   database/migrations/2026_02_01_000001_add_stage_to_conversas_table.php.backup

# 2. Remover o arquivo não rastreado
rm database/migrations/2026_02_01_000001_add_stage_to_conversas_table.php

# 3. Fazer pull normalmente
git pull

# 4. Verificar status
git status

# 5. Se necessário, comparar arquivos
diff database/migrations/2026_02_01_000001_add_stage_to_conversas_table.php.backup \
     database/migrations/2026_02_01_000001_add_stage_to_conversas_table.php
```

---

### Opção 2: Stash e Pull (Alternativa)

```bash
# 1. Adicionar arquivo ao git temporariamente
git add database/migrations/2026_02_01_000001_add_stage_to_conversas_table.php

# 2. Fazer stash
git stash

# 3. Pull
git pull

# 4. Aplicar stash (se necessário)
git stash pop

# 5. Resolver conflitos se houver
git status
```

---

### Opção 3: Force Pull (PERIGOSO - Último Recurso)

```bash
# ⚠️ CUIDADO: Isso apaga mudanças locais!
git fetch --all
git reset --hard origin/master
```

---

## 🔍 Investigar o Arquivo

Antes de remover, veja o conteúdo:

```bash
cat database/migrations/2026_02_01_000001_add_stage_to_conversas_table.php
```

**Se for importante:**
- Copie o conteúdo
- Salve em outro lugar
- Compare com a versão do repositório após pull

---

## 🎯 Comandos Sequenciais (Copy-Paste)

Execute linha por linha no SSH:

```bash
# Navegar para diretório correto
cd ~/domains/lojadaesquina.store/public_html

# Verificar status atual
git status

# Backup do arquivo conflitante
cp database/migrations/2026_02_01_000001_add_stage_to_conversas_table.php database/migrations/BACKUP_stage_conversas.php 2>/dev/null || echo "Arquivo já foi movido"

# Remover arquivo não rastreado
rm -f database/migrations/2026_02_01_000001_add_stage_to_conversas_table.php

# Pull do repositório
git pull

# Verificar resultado
git status

# Listar migrations
ls -la database/migrations/ | grep stage

# Verificar se sistema está ok
php -v
```

---

## ✅ Após o Pull

### 1. Rodar Migrations (Se Necessário)

```bash
# Verificar migrations pendentes
/opt/alt/php83/usr/bin/php artisan migrate:status

# Rodar migrations
/opt/alt/php83/usr/bin/php artisan migrate --force
```

### 2. Limpar Cache

```bash
# Cache do Laravel/Lumen
/opt/alt/php83/usr/bin/php artisan cache:clear
/opt/alt/php83/usr/bin/php artisan config:clear
/opt/alt/php83/usr/bin/php artisan route:clear

# Cache do OPcache
echo "<?php opcache_reset(); echo 'OPcache cleared';" > opcache_clear.php
curl https://lojadaesquina.store/opcache_clear.php
rm opcache_clear.php
```

### 3. Verificar Permissões

```bash
chmod -R 775 storage bootstrap/cache
```

### 4. Testar API

```bash
curl https://lojadaesquina.store/api/health
```

---

## 📋 Troubleshooting

### Se git pull continuar falhando:

```bash
# Ver todos os arquivos não rastreados
git status --untracked-files=all

# Remover TODOS os arquivos não rastreados (CUIDADO!)
git clean -fd

# Ou apenas ver o que seria removido
git clean -fdn
```

### Se houver conflitos após pull:

```bash
# Ver arquivos em conflito
git diff --name-only --diff-filter=U

# Aceitar versão do repositório
git checkout --theirs database/migrations/filename.php
git add .
git commit -m "Resolver conflito - aceitar versão remota"
```

---

## 🎯 RESUMO RÁPIDO

**Cole isso no SSH:**

```bash
cd ~/domains/lojadaesquina.store/public_html && \
rm -f database/migrations/2026_02_01_000001_add_stage_to_conversas_table.php && \
git pull && \
/opt/alt/php83/usr/bin/php artisan migrate --force && \
/opt/alt/php83/usr/bin/php artisan cache:clear && \
chmod -R 775 storage bootstrap/cache && \
curl https://lojadaesquina.store/api/health
```

**Saída esperada:**
```
Updating 507482c..8678eb2
...
{"app":"SOCIMOB","version":"...","status":"online"}
```

---

## ✅ Após Resolver

Teste no navegador:
- https://lojadaesquina.store
- https://lojadaesquina.store/api/health

Se tudo funcionar: ✅ Deploy concluído!

---

**Data:** 19/01/2026  
**Status:** Pronto para executar no SSH
