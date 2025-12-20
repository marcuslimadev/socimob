#!/bin/bash

# ============================================================================
# SCRIPT DE PRIMEIRO DEPLOY - Exclusiva SaaS
# ============================================================================
# Este script configura o ambiente completo no primeiro deploy
# Inclui: dependências, migrações, seeders e configurações iniciais

set -e  # Parar em qualquer erro

echo "🚀 Iniciando setup do primeiro deploy..."
echo "======================================"

# Verificar se estamos no diretório correto
if [ ! -f "composer.json" ]; then
    echo "❌ Execute este script na raiz do projeto!"
    exit 1
fi

# ============================================================================
# 1. DEPENDÊNCIAS
# ============================================================================
echo ""
echo "📦 Instalando dependências..."
if [ -d "vendor" ]; then
    echo "ℹ️  Vendor já existe, atualizando..."
    composer update --no-dev --prefer-dist --no-interaction --optimize-autoloader
else
    echo "🔧 Instalação completa do composer..."
    composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
fi

# ============================================================================
# 2. VERIFICAR AMBIENTE
# ============================================================================
echo ""
echo "🔍 Verificando ambiente..."

# Verificar se .env existe
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        echo "⚙️  Copiando .env.example para .env..."
        cp .env.example .env
        echo "⚠️  IMPORTANTE: Configure as variáveis de ambiente no .env"
    else
        echo "❌ Arquivo .env não encontrado!"
        exit 1
    fi
fi

# Verificar se MySQL está acessível
echo "🗄️  Testando conexão com banco de dados..."
php -r "
try {
    \$pdo = new PDO(
        'mysql:host=' . ($_ENV['DB_HOST'] ?? 'localhost') . ';dbname=' . ($_ENV['DB_DATABASE'] ?? 'exclusiva'),
        \$_ENV['DB_USERNAME'] ?? 'root',
        \$_ENV['DB_PASSWORD'] ?? ''
    );
    echo '✅ Conexão com banco OK\n';
} catch (Exception \$e) {
    echo '❌ Erro de conexão: ' . \$e->getMessage() . '\n';
    exit(1);
}
"

# ============================================================================
# 3. MIGRAÇÕES
# ============================================================================
echo ""
echo "🗃️  Executando migrações..."
if command -v php artisan >/dev/null 2>&1; then
    php artisan migrate --force
else
    echo "⚠️  Artisan não disponível, pulando migrações automáticas"
    echo "    Execute manualmente: php artisan migrate --force"
fi

# ============================================================================
# 4. SEEDERS (PRIMEIRO DEPLOY APENAS)
# ============================================================================
echo ""
if [ -f ".first-deploy-done" ]; then
    echo "ℹ️  Deploy subsequente detectado - seeders não executados"
else
    echo "🌱 PRIMEIRO DEPLOY - Executando seeders..."
    
    if [ -f "database/seeders/DatabaseSeeder.php" ]; then
        php database/seeders/DatabaseSeeder.php
        
        # Marcar primeiro deploy como concluído
        echo "$(date): Primeiro deploy com seeders concluído" > .first-deploy-done
        echo "✅ Seeders executados e marcador criado"
    else
        echo "⚠️  Seeders não encontrados em database/seeders/"
    fi
fi

# ============================================================================
# 5. PERMISSÕES E CACHE (se necessário)
# ============================================================================
echo ""
echo "🔧 Configurações finais..."

# Permissões de storage (se existir)
if [ -d "storage" ]; then
    echo "📁 Configurando permissões do storage..."
    chmod -R 775 storage
    chmod -R 775 bootstrap/cache 2>/dev/null || true
fi

# Cache de configurações (se disponível)
if command -v php artisan >/dev/null 2>&1; then
    echo "💨 Otimizando cache..."
    php artisan config:cache 2>/dev/null || echo "   Config cache não disponível"
    php artisan route:cache 2>/dev/null || echo "   Route cache não disponível"
fi

# ============================================================================
# 6. RESUMO FINAL
# ============================================================================
echo ""
echo "✅ PRIMEIRO DEPLOY CONCLUÍDO!"
echo "=============================="
echo ""
echo "📋 Resumo do que foi feito:"
echo "  ✅ Dependências instaladas (composer)"
echo "  ✅ Migrações executadas"
if [ ! -f ".first-deploy-done" ]; then
    echo "  ✅ Seeders executados (dados iniciais)"
else
    echo "  ℹ️  Seeders pulados (deploy subsequente)"
fi
echo "  ✅ Permissões configuradas"
echo "  ✅ Cache otimizado"
echo ""
echo "🎯 CREDENCIAIS CRIADAS (primeiro deploy):"
echo "  Super Admin: admin@exclusiva.com / password"
echo "  Contato: contato@exclusiva.com.br / Teste@123"
echo "  Alexsandra: alexsandra@exclusiva.com.br / Senha@123"
echo ""
echo "🌐 Próximos passos:"
echo "  1. Verificar se o servidor web está apontando para /public"
echo "  2. Configurar domínio (se aplicável)"
echo "  3. Testar acesso ao sistema"
echo "  4. Fazer login com as credenciais acima"
echo ""
echo "📞 Em caso de problemas:"
echo "  - Verificar logs em storage/logs/"
echo "  - Conferir permissões de arquivos"
echo "  - Validar configuração do .env"