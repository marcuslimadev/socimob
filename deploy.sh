#!/bin/bash

# ============================================================================
# DEPLOY SIMPLES - Hostinger/Manual
# ============================================================================
# Script para deploy direto no servidor sem GitHub Actions
# Execute este script via SSH no servidor após upload dos arquivos

echo "🚀 Deploy Manual - Exclusiva SaaS"
echo "================================="

# Verificar se estamos no diretório correto
if [ ! -f "composer.json" ]; then
    echo "❌ Execute este script na raiz do projeto!"
    exit 1
fi

# ============================================================================
# 1. DEPENDÊNCIAS
# ============================================================================
echo ""
echo "📦 Instalando/atualizando dependências..."
composer install --no-dev --optimize-autoloader

# ============================================================================
# 2. MIGRAÇÕES  
# ============================================================================
echo ""
echo "🗃️  Executando migrações..."
php artisan migrate --force

# ============================================================================
# 3. SEEDERS (apenas primeiro deploy)
# ============================================================================
echo ""
if [ -f ".first-deploy-done" ]; then
    echo "ℹ️  Deploy subsequente - seeders não executados"
    echo "   Dados existentes preservados"
else
    echo "🌱 PRIMEIRO DEPLOY - Executando seeders..."
    php database/seeders/DatabaseSeeder.php
    echo "$(date): Primeiro deploy concluído" > .first-deploy-done
    echo "✅ Dados iniciais criados!"
fi

# ============================================================================
# 4. CACHE E PERMISSÕES
# ============================================================================
echo ""
echo "⚙️  Configurações finais..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
php artisan config:cache 2>/dev/null || echo "   Config cache não disponível"

# ============================================================================
# 5. RESULTADO
# ============================================================================
echo ""
echo "✅ DEPLOY CONCLUÍDO!"
echo "==================="
echo ""
echo "🎯 Sistema pronto para uso:"
echo "  📧 Admin: contato@exclusiva.com.br / Teste@123"
echo "  📧 Super: admin@exclusiva.com / password"
echo ""
echo "🌐 Próximo passo:"
echo "  Acesse: https://seu-dominio.com/app/"
echo ""