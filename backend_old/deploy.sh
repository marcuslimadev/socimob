#!/bin/bash

# Script de deploy para Render.com
set -e

echo "🚀 Iniciando deploy no Render..."

# Instalar dependências
echo "📦 Instalando dependências..."
composer install --no-dev --optimize-autoloader --no-interaction

# Gerar chave de aplicação se não existir
if [ -z "$APP_KEY" ]; then
    echo "🔑 Gerando APP_KEY..."
    php artisan key:generate --no-interaction
fi

# Cache de configuração
echo "⚡ Otimizando configurações..."
php artisan config:cache
php artisan route:cache

# Verificar conexão com banco
echo "🗄️ Verificando banco de dados..."
php artisan migrate --force || echo "⚠️ Migrations falharam - verifique se o banco existe"

echo "✅ Deploy concluído!"
