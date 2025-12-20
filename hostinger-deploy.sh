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
$COMPOSER_BIN install --no-dev --optimize-autoloader

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
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache 2>/dev/null || echo "   Route cache não disponível"

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
echo "📝 Para recriar dados: ./hostinger-deploy.sh fresh"