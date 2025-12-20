#!/bin/bash

echo "🌱 Executando Seeders da Exclusiva..."
echo "======================================"

# Verificar se estamos no diretório correto
if [ ! -f "composer.json" ]; then
    echo "❌ Execute este script na raiz do projeto!"
    exit 1
fi

# Verificar se vendor existe
if [ ! -d "vendor" ]; then
    echo "📦 Instalando dependências..."
    composer install
fi

# Executar seeders
echo "🚀 Populando banco de dados..."
php database/seeders/DatabaseSeeder.php

echo ""
echo "✅ Seeders executados!"
echo ""
echo "🎯 Para iniciar o servidor:"
echo "   ./START.bat (Windows)"
echo "   php -S 127.0.0.1:8000 -t public (Manual)"