#!/bin/bash

# ============================================================================
# DEPLOY HOSTINGER - Exclusiva SaaS
# ============================================================================
# Script otimizado para deploy na Hostinger com PHP 8.3

export PHP_BIN=/opt/alt/php83/usr/bin/php
export COMPOSER_BIN=composer

echo "🚀 Deploy Hostinger - Exclusiva SaaS"
echo "====================================="
echo "📍 Diretório: $(pwd)"
echo "🔧 PHP: $PHP_BIN"

# Verificar se PHP está disponível
if [ ! -x "$PHP_BIN" ]; then
    echo "❌ PHP não encontrado em $PHP_BIN"
    echo "💡 Tente: export PHP_BIN=/opt/alt/php83/usr/bin/php"
    exit 1
fi

# Verificar versão do PHP
PHP_VERSION=$($PHP_BIN --version | head -n1)
echo "📊 Versão: $PHP_VERSION"

# Verificar se estamos no diretório correto
if [ ! -f "composer.json" ]; then
    echo "❌ Execute este script na raiz do projeto!"
    exit 1
fi

# Dar permissões aos scripts
echo "🔐 Configurando permissões..."
chmod +x *.sh scripts/*.sh 2>/dev/null || true

# ============================================================================
# 1. DEPENDÊNCIAS
# ============================================================================
echo ""
echo "📦 Instalando dependências..."

# Limpar cache do composer se houver problemas
if [ "$1" = "clean" ] || [ "$2" = "clean" ]; then
    echo "🧹 Limpando cache do composer..."
    $COMPOSER_BIN clear-cache
    rm -rf vendor/ composer.lock
fi

# Instalar com PHP correto
COMPOSER_DISABLE_XDEBUG_WARN=1 $PHP_BIN $(which composer) install --no-dev --optimize-autoloader

# ============================================================================
# 2. MIGRAÇÕES E SEEDERS
# ============================================================================
echo ""
echo "🗃️  Configurando banco de dados..."

# Opção 1: Migrate fresh + seed (APAGA TUDO)
if [ "$1" = "fresh" ]; then
    echo "⚠️  ATENÇÃO: Executando migrate:fresh (apaga todos os dados)"
    $PHP_BIN artisan migrate:fresh --seed --force
    echo "✅ Banco recriado com seeders"
# Opção 2: Deploy normal
else
    echo "🔄 Executando migrações..."
    $PHP_BIN artisan migrate --force
    
    if [ -f ".first-deploy-done" ]; then
        echo "ℹ️  Deploy subsequente - seeders preservados"
    else
        echo "🌱 Primeiro deploy - executando seeders..."
        $PHP_BIN database/seeders/DatabaseSeeder.php
        echo "$(date): Primeiro deploy concluído" > .first-deploy-done
        echo "✅ Dados iniciais criados!"
    fi
fi

# ============================================================================
# 3. CACHE E PERMISSÕES
# ============================================================================
echo ""
echo "⚙️  Configurações finais..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Lumen não tem todos os comandos artisan do Laravel
echo "💨 Configurando cache..."
$PHP_BIN artisan route:cache 2>/dev/null || echo "   Route cache não disponível (normal no Lumen)"

# Lumen não tem config:cache, mas podemos otimizar autoloader
$COMPOSER_BIN dump-autoload --optimize --no-dev 2>/dev/null || true

# ============================================================================
# 4. RESULTADO
# ============================================================================
echo ""
echo "✅ DEPLOY CONCLUÍDO!"
echo "==================="
echo ""
echo "🎯 Sistema pronto:"
echo "  📧 Admin: contato@exclusiva.com.br / Teste@123"
echo "  📧 Super: admin@exclusiva.com / password"
echo "  📧 Alexsandra: alexsandra@exclusiva.com.br / Senha@123"
echo ""
echo "🌐 Acesse: https://lojadaesquina.store/app/"
echo ""
echo "� Opções do script:"
echo "  ./hostinger-deploy.sh        - Deploy normal"
echo "  ./hostinger-deploy.sh fresh  - Recriar tudo"
echo "  ./hostinger-deploy.sh clean  - Limpar e reinstalar"