#!/bin/bash

# ============================================================================
# VERIFICAÇÃO PÓS-DEPLOY - Exclusiva SaaS  
# ============================================================================
# Script para verificar se o deploy foi executado corretamente
# Testa banco, seeders, usuários e configurações básicas

echo "🔍 Verificação Pós-Deploy - Exclusiva SaaS"
echo "========================================="

# Carregar variáveis de ambiente se disponível
if [ -f ".env" ]; then
    export $(cat .env | grep -v '^#' | xargs)
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
        echo "  ✅ $file"
    else
        echo "  ❌ $file - FALTANDO"
        ((ERRORS++))
    fi
done

# ============================================================================
# 2. VERIFICAR CONEXÃO COM BANCO
# ============================================================================
echo ""
echo "🗄️  Verificando conexão com banco..."

DB_TEST=$(php -r "
try {
    \$pdo = new PDO(
        'mysql:host=' . (\$_ENV['DB_HOST'] ?? 'localhost') . ';dbname=' . (\$_ENV['DB_DATABASE'] ?? 'exclusiva'),
        \$_ENV['DB_USERNAME'] ?? 'root',
        \$_ENV['DB_PASSWORD'] ?? ''
    );
    echo 'OK';
} catch (Exception \$e) {
    echo 'ERRO: ' . \$e->getMessage();
}
" 2>/dev/null)

if [[ $DB_TEST == "OK" ]]; then
    echo "  ✅ Conexão com banco estabelecida"
else
    echo "  ❌ Erro na conexão: $DB_TEST"
    ((ERRORS++))
fi

# ============================================================================
# 3. VERIFICAR SE SEEDERS FORAM EXECUTADOS
# ============================================================================
echo ""
echo "🌱 Verificando execução dos seeders..."

if [ -f ".first-deploy-done" ]; then
    echo "  ✅ Marcador de primeiro deploy encontrado"
    echo "  📅 $(cat .first-deploy-done)"
else
    echo "  ⚠️  Marcador de primeiro deploy não encontrado"
    echo "      Seeders podem não ter sido executados"
fi

# Verificar se usuários foram criados
USER_COUNT=$(php -r "
try {
    \$pdo = new PDO(
        'mysql:host=' . (\$_ENV['DB_HOST'] ?? 'localhost') . ';dbname=' . (\$_ENV['DB_DATABASE'] ?? 'exclusiva'),
        \$_ENV['DB_USERNAME'] ?? 'root',
        \$_ENV['DB_PASSWORD'] ?? ''
    );
    \$stmt = \$pdo->query('SELECT COUNT(*) FROM users WHERE email LIKE \"%exclusiva.com%\"');
    echo \$stmt->fetchColumn();
} catch (Exception \$e) {
    echo '0';
}
" 2>/dev/null)

if [ "$USER_COUNT" -gt 0 ]; then
    echo "  ✅ $USER_COUNT usuários Exclusiva encontrados no banco"
else
    echo "  ❌ Nenhum usuário Exclusiva encontrado"
    ((ERRORS++))
fi

# Verificar tenant
TENANT_COUNT=$(php -r "
try {
    \$pdo = new PDO(
        'mysql:host=' . (\$_ENV['DB_HOST'] ?? 'localhost') . ';dbname=' . (\$_ENV['DB_DATABASE'] ?? 'exclusiva'),
        \$_ENV['DB_USERNAME'] ?? 'root',
        \$_ENV['DB_PASSWORD'] ?? ''
    );
    \$stmt = \$pdo->query('SELECT COUNT(*) FROM tenants WHERE slug = \"exclusiva\"');
    echo \$stmt->fetchColumn();
} catch (Exception \$e) {
    echo '0';
}
" 2>/dev/null)

if [ "$TENANT_COUNT" -gt 0 ]; then
    echo "  ✅ Tenant Exclusiva encontrado no banco"
else
    echo "  ❌ Tenant Exclusiva não encontrado"
    ((ERRORS++))
fi

# ============================================================================
# 4. VERIFICAR PERMISSÕES
# ============================================================================
echo ""
echo "🔐 Verificando permissões..."

if [ -d "storage" ]; then
    if [ -w "storage" ]; then
        echo "  ✅ Diretório storage gravável"
    else
        echo "  ⚠️  Diretório storage não gravável"
        echo "      Execute: chmod -R 775 storage"
    fi
else
    echo "  ⚠️  Diretório storage não encontrado"
fi

if [ -d "bootstrap/cache" ]; then
    if [ -w "bootstrap/cache" ]; then
        echo "  ✅ Cache do bootstrap gravável"
    else
        echo "  ⚠️  Cache do bootstrap não gravável"
    fi
fi

# ============================================================================
# 5. VERIFICAR SERVIDOR WEB (se possível)
# ============================================================================
echo ""
echo "🌐 Verificando servidor web..."

# Tentar fazer request HTTP básico
if command -v curl >/dev/null 2>&1; then
    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8000 2>/dev/null || echo "000")
    
    if [ "$HTTP_STATUS" = "200" ]; then
        echo "  ✅ Servidor respondendo (HTTP $HTTP_STATUS)"
    else
        echo "  ⚠️  Servidor não responde ou não está rodando (HTTP $HTTP_STATUS)"
        echo "      Execute: php -S 127.0.0.1:8000 -t public"
    fi
else
    echo "  ℹ️  Curl não disponível - não foi possível testar servidor"
fi

# ============================================================================
# 6. RESUMO FINAL
# ============================================================================
echo ""
echo "📊 RESUMO DA VERIFICAÇÃO"
echo "========================"

if [ $ERRORS -eq 0 ]; then
    echo "✅ SUCESSO! Deploy verificado com sucesso"
    echo ""
    echo "🎯 Sistema pronto para uso:"
    echo "  🌐 URL: http://localhost:8000/app/"
    echo "  👤 Super Admin: admin@exclusiva.com / password"
    echo "  👤 Admin: contato@exclusiva.com.br / Teste@123"
    echo ""
    echo "🚀 Próximos passos:"
    echo "  1. Acessar o sistema via browser"
    echo "  2. Fazer login com as credenciais"
    echo "  3. Configurar domínio (se necessário)"
    echo "  4. Personalizar tema/logo"
else
    echo "❌ ATENÇÃO: $ERRORS erro(s) encontrado(s)"
    echo ""
    echo "🔧 Ações necessárias:"
    echo "  1. Corrigir os erros listados acima"
    echo "  2. Executar novamente este script"
    echo "  3. Verificar logs em storage/logs/"
    exit 1
fi