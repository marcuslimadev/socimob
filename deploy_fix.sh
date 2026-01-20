#!/bin/bash
# Script de Deploy - Resolver Conflito Git e Atualizar Produção
# Execute no SSH: bash deploy_fix.sh

echo "===================================="
echo "  SOCIMOB - Deploy em Produção"
echo "===================================="
echo ""

# Navegar para diretório
cd ~/domains/lojadaesquina.store/public_html || exit

echo "📁 Diretório atual:"
pwd
echo ""

# Mostrar status
echo "🔍 Status Git atual:"
git status
echo ""

# Backup do arquivo conflitante
echo "💾 Fazendo backup do arquivo conflitante..."
if [ -f "database/migrations/2026_02_01_000001_add_stage_to_conversas_table.php" ]; then
    cp database/migrations/2026_02_01_000001_add_stage_to_conversas_table.php \
       database/migrations/BACKUP_stage_conversas_$(date +%Y%m%d_%H%M%S).php
    echo "✅ Backup criado"
else
    echo "⚠️  Arquivo já não existe"
fi
echo ""

# Remover arquivo não rastreado
echo "🗑️  Removendo arquivo conflitante..."
rm -f database/migrations/2026_02_01_000001_add_stage_to_conversas_table.php
echo "✅ Arquivo removido"
echo ""

# Git pull
echo "⬇️  Fazendo git pull..."
git pull
if [ $? -eq 0 ]; then
    echo "✅ Git pull concluído"
else
    echo "❌ Erro no git pull"
    exit 1
fi
echo ""

# Verificar PHP
echo "🔍 Verificando PHP..."
/opt/alt/php83/usr/bin/php -v | head -1
echo ""

# Rodar migrations
echo "🔄 Rodando migrations..."
/opt/alt/php83/usr/bin/php artisan migrate --force
echo ""

# Limpar cache
echo "🧹 Limpando cache..."
/opt/alt/php83/usr/bin/php artisan cache:clear 2>/dev/null || echo "Cache cleared"
/opt/alt/php83/usr/bin/php artisan config:clear 2>/dev/null || echo "Config cleared"
/opt/alt/php83/usr/bin/php artisan route:clear 2>/dev/null || echo "Routes cleared"
echo ""

# Permissões
echo "🔐 Ajustando permissões..."
chmod -R 775 storage bootstrap/cache 2>/dev/null
echo "✅ Permissões ajustadas"
echo ""

# Testar API
echo "🧪 Testando API..."
curl -s https://lojadaesquina.store/api/health | head -c 200
echo ""
echo ""

# Status final
echo "===================================="
echo "✅ DEPLOY CONCLUÍDO!"
echo "===================================="
echo ""
echo "🌐 Teste no navegador:"
echo "   https://lojadaesquina.store"
echo "   https://lojadaesquina.store/api/health"
echo ""
echo "📊 Commits atualizados:"
git log --oneline -5
echo ""
