#!/bin/bash

set -e

echo "🚀 Iniciando Exclusiva SaaS..."

# Aguardar banco de dados estar pronto
echo "⏳ Aguardando banco de dados..."
for i in {1..30}; do
  if nc -zv db 3306 &>/dev/null; then
    echo "✅ Banco de dados disponível!"
    break
  fi
  echo "Banco de dados indisponível, aguardando... ($i/30)"
  sleep 2
done

# Gerar chave da aplicação se não existir
if [ ! -f .env ]; then
    echo "📝 Criando arquivo .env..."
    cp .env.example .env
fi

# Gerar APP_KEY se não existir
if ! grep -q "APP_KEY=base64:" .env; then
    echo "🔑 Gerando APP_KEY..."
    php artisan key:generate --force
fi

# Executar migrations
echo "🗄️  Executando migrations..."
php artisan migrate --force

# Limpar cache
echo "💾 Limpando cache..."
php artisan cache:clear
php artisan view:clear
php artisan config:clear

# Gerar cache de produção
echo "💾 Gerando cache..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Instalar permissões corretas
echo "🔐 Configurando permissões..."
chown -R www-data:www-data /var/www/exclusiva
chmod -R 755 /var/www/exclusiva
chmod -R 775 /var/www/exclusiva/storage
chmod -R 775 /var/www/exclusiva/bootstrap/cache

# Iniciar PHP-FPM
echo "🚀 Iniciando PHP-FPM..."
php-fpm &

# Iniciar Nginx
echo "🚀 Iniciando Nginx..."
nginx -g "daemon off;"
