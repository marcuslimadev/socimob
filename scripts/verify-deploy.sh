#!/bin/bash

# ============================================================================
# VERIFICAÇÃO PÓS-DEPLOY - Exclusiva SaaS
# ============================================================================
# Script para verificar se o deploy foi executado corretamente
# Testa banco, seeders, usuários e configurações básicas

set -euo pipefail

PHP_BIN="${PHP_BIN:-/opt/alt/php83/usr/bin/php}"

if [ ! -x "$PHP_BIN" ]; then
    echo "❌ Binário do PHP não encontrado em $PHP_BIN"
    exit 1
fi

echo "🧪 Verificação Pós-Deploy - Exclusiva SaaS"
echo "========================================="

# Carregar variáveis de ambiente se disponível
if [ -f ".env" ]; then
    export $(grep -v '^#' .env | xargs)
fi

ERRORS=0

# ============================================================================
# 1. VERIFICAR ARQUIVOS ESSENCIAIS
# ============================================================================
echo ""
echo "📁 Verificando arquivos essenciais..."

essential_files=(
    "composer.json"
    "bootstrap/app.php"
    "public/index.php"
    "database/seeders/DatabaseSeeder.php"
    ".env"
)

for file in "${essential_files[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✓ $file"
    else
        echo "  ✗ $file - FALTANDO"
        ((ERRORS++))
    fi
done

# ============================================================================
# 2. VERIFICAR CONEXÃO COM BANCO
# ============================================================================
echo ""
echo "🔌 Verificando conexão com banco..."

DB_TEST=$($PHP_BIN -r "
try {
    \$pdo = new PDO(
        'mysql:host=' . (\$_ENV['DB_HOST'] ?? 'localhost') . ';dbname=' . (\$_ENV['DB_DATABASE'] ?? 'exclusiva'),
        \$_ENV['DB_USERNAME'] ?? 'root',
        \$_ENV['DB_PASSWORD'] ?? ''
    );
    echo 'OK';
} catch (Exception \$e) {
    echo 'ERROR: ' . \$e->getMessage();
}
" 2>/dev/null)

if [[ "$DB_TEST" == "OK" ]]; then
    echo "  ✓ Conexão com banco estabelecida"
else
    echo "  ✗ Erro na conexão: $DB_TEST"
    ((ERRORS++))
fi

# ============================================================================
# 3. VERIFICAR FUNCIONALIDADE DOS SEEDERS
# ============================================================================
echo ""
echo "🌱 Verificando seeders..."

if [ -f ".first-deploy-done" ]; then
    echo "  ✓ Marker de primeiro deploy existente"
    echo "  ▪ $(cat .first-deploy-done)"
else
    echo "  ✗ Marker de primeiro deploy ausente"
    ((ERRORS++))
fi

for query in \
"SELECT COUNT(*) FROM users WHERE email LIKE '%@exclusiva.com%';" \
"SELECT COUNT(*) FROM tenants WHERE slug = 'exclusiva';"; do
    VALUE=$($PHP_BIN -r "
try {
    \$pdo = new PDO(
        'mysql:host=' . (\$_ENV['DB_HOST'] ?? 'localhost') . ';dbname=' . (\$_ENV['DB_DATABASE'] ?? 'exclusiva'),
        \$_ENV['DB_USERNAME'] ?? 'root',
        \$_ENV['DB_PASSWORD'] ?? ''
    );
    \$stmt = \$pdo->query(\"$query\");
    echo \$stmt->fetchColumn();
} catch (Exception \$e) {
    echo '0';
}
" 2>/dev/null)

    if [[ "$VALUE" -gt 0 ]]; then
        echo "  ✓ Query '$query' retornou $VALUE registros"
    else
        echo "  ✗ Query '$query' não encontrou registros"
        ((ERRORS++))
    fi
done

# ============================================================================
# 4. PERMISSÕES
# ============================================================================
echo ""
echo "🔐 Verificando permissões..."

for dir in storage bootstrap/cache; do
    if [ -d "$dir" ] && [ -w "$dir" ]; then
        echo "  ✓ Diretório $dir gravável"
    else
        echo "  ✗ Diretório $dir sem permissão de escrita"
        ((ERRORS++))
    fi
done

# ============================================================================
# 5. STATUS FINAL
# ============================================================================
echo ""
echo "📊 Resumo da verificação"
echo "========================"

if [ $ERRORS -eq 0 ]; then
    echo "✅ SUCESSO! Deploy verificado."
else
    echo "⚠️  $ERRORS item(ns) localizados."
    exit 1
fi
