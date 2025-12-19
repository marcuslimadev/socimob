#!/bin/bash
set -e

echo "🚀 Iniciando SOCIMOB SaaS..."

# Aguardar banco de dados
echo "⏳ Aguardando banco de dados..."
for i in {1..30}; do
  if nc -zv db 3306 &>/dev/null; then
    echo "✅ Banco disponível!"
    break
  fi
  sleep 2
done

# Configurar permissões
chown -R www-data:www-data /var/www/exclusiva 2>/dev/null || true

# Iniciar serviços
echo "🎉 Iniciando PHP-FPM + Nginx..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
